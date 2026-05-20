<?php
/**
 * PESO Balayan – Application Model
 * File: app/models/ApplicationModel.php
 *
 * Handles all database operations for the application workflow:
 *   - Application CRUD
 *   - Duplicate detection
 *   - Status transitions + history log
 *   - Timeline (status_history) read/write
 *   - Matching queue management
 *   - Employer and applicant filtered views
 *
 * All queries use PDO prepared statements via the base Model.
 */

namespace App\Models;

use App\Core\Model;

class ApplicationModel extends Model
{
    protected string $table      = 'applications';
    protected string $primaryKey = 'id';

    // ── Valid status transition map ───────────────────────────
    // Maps current status → allowed next statuses
    private const STATUS_TRANSITIONS = [
        'submitted'            => ['under_review', 'rejected', 'withdrawn'],
        'under_review'         => ['shortlisted', 'matched', 'rejected', 'withdrawn'],
        'shortlisted'          => ['matched', 'rejected', 'withdrawn'],
        'matched'              => ['referred', 'rejected'],
        'referred'             => ['interview_scheduled', 'rejected'],
        'interview_scheduled'  => ['hired', 'rejected'],
        'hired'                => [],
        'rejected'             => ['submitted'],  // re-open edge case (admin only)
        'withdrawn'            => [],
    ];

    // ── SUBMIT ────────────────────────────────────────────────

    /**
     * Submit a new job application.
     *
     * @param  array $data  Keys: applicant_id, job_id, cover_letter, resume_path, additional_docs
     * @return int          New application ID
     */
    public function submitApplication(array $data): int
    {
        $payload = [
            'applicant_id'    => (int) $data['applicant_id'],
            'job_id'          => (int) $data['job_id'],
            'cover_letter'    => $data['cover_letter']    ?? null,
            'resume_path'     => $data['resume_path']     ?? null,
            'additional_docs' => isset($data['additional_docs'])
                ? json_encode($data['additional_docs'])
                : null,
            'status'          => 'submitted',
            'applied_at'      => date('Y-m-d H:i:s'),
            'matching_queued' => 0,
        ];

        $id = $this->create($payload);

        // Record initial status in history
        $this->addTimeline($id, null, 'submitted', $data['applicant_id'] ?? null, 'Application submitted.');

        return $id;
    }

    // ── WITHDRAW ──────────────────────────────────────────────

    /**
     * Withdraw an application (applicant-initiated soft cancel).
     * Only allowed from: submitted, under_review, shortlisted.
     *
     * @param  int    $applicationId
     * @param  int    $applicantId    Must own the application
     * @param  string $reason         Optional reason
     * @return bool
     */
    public function withdrawApplication(int $applicationId, int $applicantId, string $reason = ''): bool
    {
        $app = $this->findById($applicationId);

        if (!$app || (int) $app['applicant_id'] !== $applicantId) {
            return false;
        }

        $nonWithdrawable = ['hired', 'rejected', 'withdrawn'];
        if (in_array($app['status'], $nonWithdrawable, true)) {
            return false;
        }

        $updated = $this->update($applicationId, [
            'status'     => 'withdrawn',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($updated) {
            $this->addTimeline(
                $applicationId,
                $app['status'],
                'withdrawn',
                $applicantId,
                $reason ?: 'Application withdrawn by applicant.'
            );
        }

        return $updated;
    }

    // ── DUPLICATE CHECK ───────────────────────────────────────

    /**
     * Check whether an applicant has already applied to a job.
     * Ignores withdrawn applications (allows re-apply after withdrawal).
     */
    public function hasDuplicateApplication(int $applicantId, int $jobId): bool
    {
        $count = (int) $this->rawQuery(
            "SELECT COUNT(*) FROM `applications`
             WHERE `applicant_id` = ? AND `job_id` = ?
               AND `status` != 'withdrawn'",
            [$applicantId, $jobId]
        )->fetchColumn();

        return $count > 0;
    }

    // ── APPLICANT VIEWS ───────────────────────────────────────

    /**
     * Get all applications submitted by an applicant, with job and employer info.
     *
     * @param  int    $applicantId
     * @param  array  $filters     Keys: status, search, order_by, direction
     * @param  int    $page
     * @param  int    $perPage
     */
    public function getApplicantApplications(
        int   $applicantId,
        array $filters  = [],
        int   $page     = 1,
        int   $perPage  = 10
    ): array {
        $where  = ['a.applicant_id = ?'];
        $params = [$applicantId];

        if (!empty($filters['status'])) {
            $where[]  = 'a.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $like     = '%' . $filters['search'] . '%';
            $where[]  = '(j.title LIKE ? OR e.company_name LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }

        $whereStr  = implode(' AND ', $where);
        $offset    = ($page - 1) * $perPage;
        $orderBy   = in_array($filters['order_by'] ?? '', ['applied_at', 'status', 'updated_at'], true)
            ? $filters['order_by'] : 'applied_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $rows = $this->rawQuery(
            "SELECT a.*,
                    j.title AS job_title, j.job_type, j.work_setup, j.slug AS job_slug,
                    j.salary_min, j.salary_max, j.salary_type, j.status AS job_status,
                    j.expires_at,
                    e.company_name, e.logo_path AS employer_logo,
                    l.barangay, l.municipality,
                    c.name AS category_name, c.icon AS category_icon
             FROM `applications` a
             JOIN `jobs` j         ON j.id  = a.job_id
             JOIN `employers` e    ON e.id  = j.employer_id
             LEFT JOIN `locations` l ON l.id = j.location_id
             LEFT JOIN `job_categories` c ON c.id = j.category_id
             WHERE {$whereStr}
             ORDER BY a.{$orderBy} {$direction}
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        )->fetchAll();

        $total = (int) $this->rawQuery(
            "SELECT COUNT(*) FROM `applications` a
             JOIN `jobs` j      ON j.id = a.job_id
             JOIN `employers` e ON e.id = j.employer_id
             WHERE {$whereStr}",
            $params
        )->fetchColumn();

        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Status summary counts for an applicant's dashboard widget.
     */
    public function getApplicantStatusCounts(int $applicantId): array
    {
        $rows = $this->rawQuery(
            "SELECT status, COUNT(*) AS cnt
             FROM `applications`
             WHERE applicant_id = ?
             GROUP BY status",
            [$applicantId]
        )->fetchAll();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }

        return $counts;
    }

    // ── EMPLOYER VIEWS ────────────────────────────────────────

    /**
     * Get all applications for a specific employer's jobs.
     *
     * @param  int   $employerId
     * @param  array $filters    Keys: job_id, status, search, order_by, direction
     * @param  int   $page
     * @param  int   $perPage
     */
    public function getEmployerApplications(
        int   $employerId,
        array $filters  = [],
        int   $page     = 1,
        int   $perPage  = 15
    ): array {
        $where  = ['e.id = ?'];
        $params = [$employerId];

        if (!empty($filters['job_id'])) {
            $where[]  = 'a.job_id = ?';
            $params[] = (int) $filters['job_id'];
        }

        if (!empty($filters['status'])) {
            $where[]  = 'a.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $like     = '%' . $filters['search'] . '%';
            $where[]  = "(ap.first_name LIKE ? OR ap.last_name LIKE ?
                          OR ap.applicant_code LIKE ? OR j.title LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereStr  = implode(' AND ', $where);
        $offset    = ($page - 1) * $perPage;
        $orderBy   = in_array($filters['order_by'] ?? '', ['applied_at', 'status', 'match_score'], true)
            ? $filters['order_by'] : 'applied_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $rows = $this->rawQuery(
            "SELECT a.*,
                    ap.first_name, ap.last_name, ap.applicant_code,
                    ap.education_level, ap.experience_years, ap.photo_path,
                    ap.resume_path AS profile_resume,
                    j.title AS job_title, j.job_type, j.slug AS job_slug,
                    j.education_required, j.experience_required,
                    u.email AS applicant_email
             FROM `applications` a
             JOIN `applicants` ap  ON ap.id  = a.applicant_id
             JOIN `users` u        ON u.id   = ap.user_id
             JOIN `jobs` j         ON j.id   = a.job_id
             JOIN `employers` e    ON e.id   = j.employer_id
             WHERE {$whereStr}
             ORDER BY a.{$orderBy} {$direction}
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        )->fetchAll();

        $total = (int) $this->rawQuery(
            "SELECT COUNT(*) FROM `applications` a
             JOIN `applicants` ap ON ap.id = a.applicant_id
             JOIN `jobs` j        ON j.id  = a.job_id
             JOIN `employers` e   ON e.id  = j.employer_id
             WHERE {$whereStr}",
            $params
        )->fetchColumn();

        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Application summary counts per job for employer dashboard.
     */
    public function getEmployerJobCounts(int $employerId): array
    {
        return $this->rawQuery(
            "SELECT j.id AS job_id, j.title,
                    COUNT(a.id) AS total,
                    SUM(a.status = 'submitted')   AS new_count,
                    SUM(a.status = 'shortlisted') AS shortlisted,
                    SUM(a.status = 'referred')    AS referred,
                    SUM(a.status = 'hired')        AS hired
             FROM `jobs` j
             LEFT JOIN `applications` a ON a.job_id = j.id
             WHERE j.employer_id = ? AND j.deleted_at IS NULL
             GROUP BY j.id, j.title
             ORDER BY total DESC",
            [$employerId]
        )->fetchAll();
    }

    // ── ADMIN VIEWS ───────────────────────────────────────────

    /**
     * Admin: get all applications with full details, filterable.
     */
    public function getAdminApplications(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'a.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['job_id'])) {
            $where[]  = 'a.job_id = ?';
            $params[] = (int) $filters['job_id'];
        }

        if (!empty($filters['employer_id'])) {
            $where[]  = 'j.employer_id = ?';
            $params[] = (int) $filters['employer_id'];
        }

        if (!empty($filters['search'])) {
            $like     = '%' . $filters['search'] . '%';
            $where[]  = "(ap.first_name LIKE ? OR ap.last_name LIKE ?
                          OR ap.applicant_code LIKE ? OR j.title LIKE ?
                          OR e.company_name LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereStr  = implode(' AND ', $where);
        $offset    = ($page - 1) * $perPage;

        $rows = $this->rawQuery(
            "SELECT a.*,
                    ap.first_name, ap.last_name, ap.applicant_code,
                    ap.education_level, ap.experience_years, ap.photo_path,
                    j.title AS job_title, j.job_type, j.slug AS job_slug,
                    e.company_name,
                    u.email AS applicant_email
             FROM `applications` a
             JOIN `applicants` ap ON ap.id = a.applicant_id
             JOIN `users` u       ON u.id  = ap.user_id
             JOIN `jobs` j        ON j.id  = a.job_id
             JOIN `employers` e   ON e.id  = j.employer_id
             WHERE {$whereStr}
             ORDER BY a.applied_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        )->fetchAll();

        $total = (int) $this->rawQuery(
            "SELECT COUNT(*) FROM `applications` a
             JOIN `applicants` ap ON ap.id = a.applicant_id
             JOIN `jobs` j        ON j.id  = a.job_id
             JOIN `employers` e   ON e.id  = j.employer_id
             WHERE {$whereStr}",
            $params
        )->fetchColumn();

        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ];
    }

    // ── STATUS UPDATE ─────────────────────────────────────────

    /**
     * Transition an application to a new status.
     *
     * Validates the transition against the status map unless $force = true.
     * Automatically records the transition in application_status_history.
     *
     * @param  int         $applicationId
     * @param  string      $newStatus
     * @param  int|null    $changedBy      user_id of actor (admin / employer)
     * @param  string|null $note           Optional free-text note
     * @param  bool        $force          Skip transition validation (admin override)
     * @return array       ['success' => bool, 'error' => string|null]
     */
    public function updateStatus(
        int    $applicationId,
        string $newStatus,
        ?int   $changedBy = null,
        string $note      = '',
        bool   $force     = false
    ): array {
        $app = $this->findById($applicationId);

        if (!$app) {
            return ['success' => false, 'error' => 'Application not found.'];
        }

        $currentStatus = $app['status'];

        // Validate transition
        if (!$force) {
            $allowed = self::STATUS_TRANSITIONS[$currentStatus] ?? [];
            if (!in_array($newStatus, $allowed, true)) {
                return [
                    'success' => false,
                    'error'   => "Invalid transition: {$currentStatus} → {$newStatus}.",
                ];
            }
        }

        // Build update payload
        $updateData = [
            'status'     => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Stamp review timestamp when first reviewed
        if ($newStatus === 'under_review' && empty($app['reviewed_at'])) {
            $updateData['reviewed_at'] = date('Y-m-d H:i:s');
            $updateData['reviewed_by'] = $changedBy;
        }

        if ($newStatus === 'referred') {
            $updateData['referred_at'] = date('Y-m-d H:i:s');
            $updateData['referred_by'] = $changedBy;
        }

        if (in_array($newStatus, ['hired', 'rejected'], true)) {
            $updateData['decided_at'] = date('Y-m-d H:i:s');
            $updateData['decided_by'] = $changedBy;
        }

        $this->update($applicationId, $updateData);

        // Record in timeline
        $this->addTimeline($applicationId, $currentStatus, $newStatus, $changedBy, $note);

        return ['success' => true, 'error' => null];
    }

    /**
     * Return allowed next statuses for a current status.
     * Useful for building UI dropdowns.
     */
    public function getAllowedTransitions(string $currentStatus): array
    {
        return self::STATUS_TRANSITIONS[$currentStatus] ?? [];
    }

    // ── TIMELINE ──────────────────────────────────────────────

    /**
     * Insert a status history record.
     *
     * @param int         $applicationId
     * @param string|null $oldStatus
     * @param string      $newStatus
     * @param int|null    $changedBy   user_id
     * @param string      $note
     */
    public function addTimeline(
        int    $applicationId,
        ?string $oldStatus,
        string  $newStatus,
        ?int    $changedBy = null,
        string  $note      = ''
    ): void {
        $this->rawQuery(
            "INSERT INTO `application_status_history`
               (`application_id`, `old_status`, `new_status`, `changed_by`, `note`, `changed_at`)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$applicationId, $oldStatus, $newStatus, $changedBy, $note ?: null]
        );
    }

    /**
     * Retrieve the full timeline for an application, newest first.
     */
    public function getTimeline(int $applicationId): array
    {
        return $this->rawQuery(
            "SELECT h.*,
                    u.email      AS changed_by_email,
                    u.role       AS changed_by_role,
                    ap.first_name, ap.last_name
             FROM `application_status_history` h
             LEFT JOIN `users` u      ON u.id  = h.changed_by
             LEFT JOIN `applicants` ap ON ap.user_id = h.changed_by
             WHERE h.application_id = ?
             ORDER BY h.changed_at ASC",
            [$applicationId]
        )->fetchAll();
    }

    // ── MATCHING QUEUE ────────────────────────────────────────

    /**
     * Push an application into the matching queue.
     * Silently skips if already queued.
     *
     * @param int $applicationId
     * @param int $applicantId
     * @param int $jobId
     */
    public function queueForMatching(int $applicationId, int $applicantId, int $jobId): void
    {
        // Mark the application as queued
        $this->rawQuery(
            "UPDATE `applications`
             SET `matching_queued` = 1, `updated_at` = NOW()
             WHERE `id` = ?",
            [$applicationId]
        );

        // Insert into queue — ignore duplicate (UNIQUE on application_id)
        $this->rawQuery(
            "INSERT IGNORE INTO `matching_queue`
               (`application_id`, `applicant_id`, `job_id`, `status`, `queued_at`)
             VALUES (?, ?, ?, 'queued', NOW())",
            [$applicationId, $applicantId, $jobId]
        );
    }

    /**
     * Get pending matching queue items (for cron/background processor).
     */
    public function getPendingMatchQueue(int $limit = 50): array
    {
        return $this->rawQuery(
            "SELECT mq.*,
                    a.cover_letter, a.resume_path,
                    ap.education_level, ap.experience_years, ap.location_id,
                    j.education_required, j.experience_required,
                    j.location_id AS job_location_id
             FROM `matching_queue` mq
             JOIN `applications` a  ON a.id  = mq.application_id
             JOIN `applicants` ap   ON ap.id = mq.applicant_id
             JOIN `jobs` j          ON j.id  = mq.job_id
             WHERE mq.status = 'queued' AND mq.attempts < 3
             ORDER BY mq.queued_at ASC
             LIMIT ?",
            [$limit]
        )->fetchAll();
    }

    /**
     * Mark a queue item as completed or failed.
     *
     * @param int    $queueId
     * @param string $status    'completed' | 'failed'
     * @param string $error     Optional error message
     */
    public function resolveQueueItem(int $queueId, string $status, string $error = ''): void
    {
        $this->rawQuery(
            "UPDATE `matching_queue`
             SET `status`       = ?,
                 `last_error`   = ?,
                 `processed_at` = NOW(),
                 `attempts`     = `attempts` + 1
             WHERE `id` = ?",
            [$status, $error ?: null, $queueId]
        );
    }

    // ── SINGLE FETCH ──────────────────────────────────────────

    /**
     * Find a single application with full joins.
     */
    public function findWithDetails(int $id): array|false
    {
        return $this->rawQuery(
            "SELECT a.*,
                    ap.first_name, ap.last_name, ap.applicant_code,
                    ap.education_level, ap.experience_years, ap.photo_path,
                    ap.resume_path AS profile_resume, ap.location_id AS applicant_location,
                    j.title AS job_title, j.job_type, j.work_setup,
                    j.slug AS job_slug, j.education_required,
                    j.experience_required, j.salary_min, j.salary_max,
                    j.salary_type, j.employer_id,
                    e.company_name, e.logo_path AS employer_logo,
                    e.contact_person, e.contact_number AS employer_contact,
                    u.email AS applicant_email
             FROM `applications` a
             JOIN `applicants` ap ON ap.id = a.applicant_id
             JOIN `users` u       ON u.id  = ap.user_id
             JOIN `jobs` j        ON j.id  = a.job_id
             JOIN `employers` e   ON e.id  = j.employer_id
             WHERE a.id = ?
             LIMIT 1",
            [$id]
        )->fetch();
    }

    /**
     * Verify ownership — returns true if the applicant owns the application.
     */
    public function isOwner(int $applicationId, int $applicantId): bool
    {
        return (int) $this->rawQuery(
            "SELECT COUNT(*) FROM `applications`
             WHERE `id` = ? AND `applicant_id` = ?",
            [$applicationId, $applicantId]
        )->fetchColumn() > 0;
    }

    /**
     * Verify employer access — returns true if the job belongs to the employer.
     */
    public function employerHasAccess(int $applicationId, int $employerId): bool
    {
        return (int) $this->rawQuery(
            "SELECT COUNT(*) FROM `applications` a
             JOIN `jobs` j ON j.id = a.job_id
             WHERE a.id = ? AND j.employer_id = ?",
            [$applicationId, $employerId]
        )->fetchColumn() > 0;
    }
}
