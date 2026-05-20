<?php
/**
 * FILE: /app/models/JobModel.php
 * PURPOSE: All database operations for job vacancies —
 *          CRUD, filtering, pagination, bookmarks, stats, and expiration.
 */
class JobModel extends BaseModel
{
    protected string $table = 'jobs';

    private const PER_PAGE_DEFAULT = 12;

    // ─── Public Listings (Applicant / Guest) ─────────────────────────────

    /**
     * Paginated, filtered job list for the public job board.
     *
     * @param  array $filters  Keys: keyword, category, province_id, city_id,
     *                         employment_type, salary_min, salary_max,
     *                         skills[], sort, status
     * @param  int   $page
     * @param  int   $perPage
     */
    public function getFiltered(array $filters = [], int $page = 1, int $perPage = self::PER_PAGE_DEFAULT): array
    {
        [$where, $params] = $this->buildFilterClauses($filters);

        $offset    = ($page - 1) * $perPage;
        $orderBy   = $this->resolveSortOrder($filters['sort'] ?? 'recent');

        $sql = "
            SELECT
                j.*,
                e.company_name,
                e.company_logo,
                l_city.name     AS city_name,
                l_prov.name     AS province_name,
                COUNT(DISTINCT a.id) AS application_count
            FROM jobs j
            LEFT JOIN employers e
                ON e.id = j.employer_id
            LEFT JOIN locations l_city
                ON l_city.id = j.city_id
            LEFT JOIN locations l_prov
                ON l_prov.id = j.province_id
            LEFT JOIN applications a
                ON a.job_id = j.id
            WHERE {$where}
            GROUP BY j.id
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        $jobs  = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = $this->countFiltered($filters);

        return [
            'data'        => $jobs,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Total count of filtered jobs (for pagination header).
     */
    public function countFiltered(array $filters = []): int
    {
        [$where, $params] = $this->buildFilterClauses($filters);

        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT j.id) FROM jobs j WHERE {$where}"
        );
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Single job with employer and location details.
     */
    public function getById(int $id, bool $includeRelations = true): ?array
    {
        $sql = $includeRelations
            ? "SELECT j.*, e.company_name, e.company_logo, e.company_website, e.company_description,
                      l_city.name AS city_name, l_prov.name AS province_name
               FROM jobs j
               LEFT JOIN employers e ON e.id = j.employer_id
               LEFT JOIN locations l_city ON l_city.id = j.city_id
               LEFT JOIN locations l_prov ON l_prov.id = j.province_id
               WHERE j.id = :id"
            : "SELECT * FROM jobs WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Featured open jobs (for dashboard / highlights).
     */
    public function getFeatured(int $limit = 6): array
    {
        $stmt = $this->db->prepare("
            SELECT j.*, e.company_name, e.company_logo,
                   l_city.name AS city_name, l_prov.name AS province_name
            FROM jobs j
            LEFT JOIN employers e ON e.id = j.employer_id
            LEFT JOIN locations l_city ON l_city.id = j.city_id
            LEFT JOIN locations l_prov ON l_prov.id = j.province_id
            WHERE j.status = 'open'
              AND j.is_featured = 1
              AND (j.expires_at IS NULL OR j.expires_at >= CURDATE())
            ORDER BY j.created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Related/similar jobs (same category, excluding current).
     */
    public function getSimilar(int $jobId, string $category, int $limit = 4): array
    {
        $stmt = $this->db->prepare("
            SELECT j.id, j.title, j.employment_type, j.salary_from, j.salary_to,
                   e.company_name, l_city.name AS city_name
            FROM jobs j
            LEFT JOIN employers e ON e.id = j.employer_id
            LEFT JOIN locations l_city ON l_city.id = j.city_id
            WHERE j.id     != :job_id
              AND j.category = :cat
              AND j.status   = 'open'
              AND (j.expires_at IS NULL OR j.expires_at >= CURDATE())
            ORDER BY j.created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
        $stmt->bindValue(':cat',    $category);
        $stmt->bindValue(':lim',    $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ─── Employer Job Management ──────────────────────────────────────────

    /**
     * All jobs by an employer, with optional status filter.
     */
    public function getByEmployer(int $employerId, string $status = ''): array
    {
        $params = [':eid' => $employerId];
        $statusClause = '';

        if (!empty($status)) {
            $statusClause      = 'AND j.status = :status';
            $params[':status'] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT j.*,
                   COUNT(DISTINCT a.id) AS application_count
            FROM jobs j
            LEFT JOIN applications a ON a.job_id = j.id
            WHERE j.employer_id = :eid {$statusClause}
            GROUP BY j.id
            ORDER BY j.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Employer statistics.
     */
    public function getEmployerStats(int $employerId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_jobs,
                SUM(status = 'open')    AS open_jobs,
                SUM(status = 'closed')  AS closed_jobs,
                SUM(status = 'draft')   AS draft_jobs,
                SUM(status = 'expired') AS expired_jobs,
                SUM(j.vacancies)        AS total_vacancies
            FROM jobs j
            WHERE j.employer_id = :eid
        ");
        $stmt->execute([':eid' => $employerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO jobs (
                employer_id, title, description, requirements,
                skills_required, education_required, experience_years,
                salary_from, salary_to, location, city_id, province_id,
                employment_type, category, vacancies,
                status, is_featured, expires_at, created_at, updated_at
            ) VALUES (
                :employer_id, :title, :description, :requirements,
                :skills_required, :education_required, :experience_years,
                :salary_from, :salary_to, :location, :city_id, :province_id,
                :employment_type, :category, :vacancies,
                :status, :is_featured, :expires_at, NOW(), NOW()
            )
        ");
        $stmt->execute([
            ':employer_id'       => $data['employer_id'],
            ':title'             => $data['title'],
            ':description'       => $data['description'],
            ':requirements'      => $data['requirements'] ?? '',
            ':skills_required'   => json_encode($data['skills_required'] ?? []),
            ':education_required'=> $data['education_required'] ?? null,
            ':experience_years'  => (int) ($data['experience_years'] ?? 0),
            ':salary_from'       => (int) ($data['salary_from'] ?? 0) ?: null,
            ':salary_to'         => (int) ($data['salary_to'] ?? 0) ?: null,
            ':location'          => $data['location'] ?? '',
            ':city_id'           => (int) ($data['city_id'] ?? 0) ?: null,
            ':province_id'       => (int) ($data['province_id'] ?? 0) ?: null,
            ':employment_type'   => $data['employment_type'] ?? 'full_time',
            ':category'          => $data['category'] ?? '',
            ':vacancies'         => (int) ($data['vacancies'] ?? 1),
            ':status'            => $data['status'] ?? 'draft',
            ':is_featured'       => (int) ($data['is_featured'] ?? 0),
            ':expires_at'        => $data['expires_at'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE jobs SET
                title              = :title,
                description        = :description,
                requirements       = :requirements,
                skills_required    = :skills_required,
                education_required = :education_required,
                experience_years   = :experience_years,
                salary_from        = :salary_from,
                salary_to          = :salary_to,
                location           = :location,
                city_id            = :city_id,
                province_id        = :province_id,
                employment_type    = :employment_type,
                category           = :category,
                vacancies          = :vacancies,
                status             = :status,
                is_featured        = :is_featured,
                expires_at         = :expires_at,
                updated_at         = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            ':title'             => $data['title'],
            ':description'       => $data['description'],
            ':requirements'      => $data['requirements'] ?? '',
            ':skills_required'   => json_encode($data['skills_required'] ?? []),
            ':education_required'=> $data['education_required'] ?? null,
            ':experience_years'  => (int) ($data['experience_years'] ?? 0),
            ':salary_from'       => (int) ($data['salary_from'] ?? 0) ?: null,
            ':salary_to'         => (int) ($data['salary_to'] ?? 0) ?: null,
            ':location'          => $data['location'] ?? '',
            ':city_id'           => (int) ($data['city_id'] ?? 0) ?: null,
            ':province_id'       => (int) ($data['province_id'] ?? 0) ?: null,
            ':employment_type'   => $data['employment_type'] ?? 'full_time',
            ':category'          => $data['category'] ?? '',
            ':vacancies'         => (int) ($data['vacancies'] ?? 1),
            ':status'            => $data['status'] ?? 'draft',
            ':is_featured'       => (int) ($data['is_featured'] ?? 0),
            ':expires_at'        => $data['expires_at'] ?? null,
            ':id'                => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM jobs WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE jobs SET status = :status, updated_at = NOW() WHERE id = :id"
        );
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }

    // ─── Ownership ───────────────────────────────────────────────────────

    public function isOwnedBy(int $jobId, int $employerId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM jobs WHERE id = :id AND employer_id = :eid"
        );
        $stmt->execute([':id' => $jobId, ':eid' => $employerId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    // ─── Bookmarks ────────────────────────────────────────────────────────

    public function isBookmarked(int $jobId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM job_bookmarks WHERE job_id = :jid AND user_id = :uid"
        );
        $stmt->execute([':jid' => $jobId, ':uid' => $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function toggleBookmark(int $jobId, int $userId): string
    {
        if ($this->isBookmarked($jobId, $userId)) {
            $this->db->prepare("DELETE FROM job_bookmarks WHERE job_id = :jid AND user_id = :uid")
                     ->execute([':jid' => $jobId, ':uid' => $userId]);
            return 'removed';
        }

        $this->db->prepare("INSERT INTO job_bookmarks (job_id, user_id, created_at) VALUES (:jid, :uid, NOW())")
                 ->execute([':jid' => $jobId, ':uid' => $userId]);
        return 'added';
    }

    public function getBookmarkedJobs(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT j.*, e.company_name, l_city.name AS city_name
            FROM jobs j
            INNER JOIN job_bookmarks jb ON jb.job_id = j.id AND jb.user_id = :uid
            LEFT JOIN employers e ON e.id = j.employer_id
            LEFT JOIN locations l_city ON l_city.id = j.city_id
            WHERE j.status = 'open'
            ORDER BY jb.created_at DESC
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ─── Expiration ───────────────────────────────────────────────────────

    /**
     * Auto-expire jobs past their expiration date.
     * Returns the count of jobs marked expired.
     */
    public function expireOverdue(): int
    {
        $stmt = $this->db->prepare("
            UPDATE jobs
            SET status = 'expired', updated_at = NOW()
            WHERE status = 'open'
              AND expires_at IS NOT NULL
              AND expires_at < CURDATE()
        ");
        $stmt->execute();
        return (int) $stmt->rowCount();
    }

    // ─── Filter Helpers ───────────────────────────────────────────────────

    public function getCategories(): array
    {
        $stmt = $this->db->query(
            "SELECT DISTINCT category FROM jobs WHERE category != '' AND status = 'open' ORDER BY category ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function getEmploymentTypes(): array
    {
        return [
            'full_time'   => 'Full-Time',
            'part_time'   => 'Part-Time',
            'contractual' => 'Contractual',
            'seasonal'    => 'Seasonal',
        ];
    }

    // ─── Private: Query Builder ───────────────────────────────────────────

    private function buildFilterClauses(array $filters): array
    {
        $conditions = ['1 = 1'];
        $params     = [];

        // Default to open jobs for public view
        $status = $filters['status'] ?? 'open';
        if (!empty($status) && $status !== 'all') {
            $conditions[]      = 'j.status = :status';
            $params[':status'] = $status;
        }

        // Only non-expired
        if (empty($filters['include_expired'])) {
            $conditions[] = '(j.expires_at IS NULL OR j.expires_at >= CURDATE())';
        }

        if (!empty($filters['keyword'])) {
            $conditions[]         = '(j.title LIKE :kw OR j.description LIKE :kw OR j.category LIKE :kw)';
            $params[':kw']        = '%' . $filters['keyword'] . '%';
        }

        if (!empty($filters['category'])) {
            $conditions[]         = 'j.category = :cat';
            $params[':cat']       = $filters['category'];
        }

        if (!empty($filters['employment_type'])) {
            $conditions[]         = 'j.employment_type = :emp_type';
            $params[':emp_type']  = $filters['employment_type'];
        }

        if (!empty($filters['province_id'])) {
            $conditions[]         = 'j.province_id = :prov_id';
            $params[':prov_id']   = (int) $filters['province_id'];
        }

        if (!empty($filters['city_id'])) {
            $conditions[]         = 'j.city_id = :city_id';
            $params[':city_id']   = (int) $filters['city_id'];
        }

        if (!empty($filters['salary_min'])) {
            $conditions[]         = 'j.salary_to >= :sal_min';
            $params[':sal_min']   = (int) $filters['salary_min'];
        }

        if (!empty($filters['salary_max'])) {
            $conditions[]         = 'j.salary_from <= :sal_max';
            $params[':sal_max']   = (int) $filters['salary_max'];
        }

        if (!empty($filters['employer_id'])) {
            $conditions[]         = 'j.employer_id = :eid';
            $params[':eid']       = (int) $filters['employer_id'];
        }

        if (!empty($filters['skill'])) {
            $conditions[]         = 'j.skills_required LIKE :skill';
            $params[':skill']     = '%' . $filters['skill'] . '%';
        }

        if (!empty($filters['featured'])) {
            $conditions[]         = 'j.is_featured = 1';
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function resolveSortOrder(string $sort): string
    {
        $map = [
            'recent'      => 'j.created_at DESC',
            'salary_desc' => 'j.salary_to DESC',
            'salary_asc'  => 'j.salary_from ASC',
            'deadline'    => 'j.expires_at ASC',
            'popular'     => 'application_count DESC',
        ];
        return $map[$sort] ?? 'j.is_featured DESC, j.created_at DESC';
    }
}
