<?php

namespace App\Models;

use App\Core\Model;

/**
 * EmploymentModel
 *
 * Manages the hiring pipeline: application status updates,
 * placement recording, interview linkage, and employer-facing
 * applicant pipeline queries.
 */
class EmploymentModel extends Model
{
    protected string $table = 'employment_records';

    /** All valid application statuses in pipeline order */
    public const STATUSES = [
        'submitted',
        'under_review',
        'shortlisted',
        'referred',
        'interview_scheduled',
        'interviewed',
        'hired',
        'rejected',
    ];

    // ── APPLICATION STATUS MANAGEMENT ───────────────────────────

    /**
     * Update an application's hiring status.
     * Also records a status_history entry via the applications table.
     *
     * @param int    $applicationId
     * @param string $newStatus
     * @param int    $updatedBy      user_id
     * @param string $note
     * @return bool
     */
    public function updateApplicationStatus(
        int $applicationId,
        string $newStatus,
        int $updatedBy,
        string $note = ''
    ): bool {
        if (!in_array($newStatus, self::STATUSES, true)) {
            return false;
        }

        // Fetch current status for history
        $stmt = $this->db->prepare(
            'SELECT status FROM applications WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$applicationId]);
        $current = $stmt->fetchColumn();

        if ($current === false) return false;

        // Update application
        $stmt = $this->db->prepare(
            'UPDATE applications
             SET status = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$newStatus, $applicationId]);

        // Write status history
        $this->writeStatusHistory($applicationId, $updatedBy, $current, $newStatus, $note);

        return true;
    }

    /**
     * Get the full applicant pipeline for an employer.
     *
     * @param int    $employerId
     * @param string $status      Optional filter
     * @param string $search      Optional name/email search
     * @param int    $limit
     * @param int    $offset
     * @return array
     */
    public function getEmployerPipeline(
        int $employerId,
        string $status = '',
        string $search = '',
        int $limit = 20,
        int $offset = 0
    ): array {
        $where  = 'j.employer_id = ?';
        $params = [$employerId];

        if ($status !== '') {
            $where   .= ' AND app.status = ?';
            $params[] = $status;
        }

        if ($search !== '') {
            $where   .= " AND (a.first_name LIKE ? OR a.last_name LIKE ? OR u.email LIKE ?)";
            $term     = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $stmt = $this->db->prepare(
            "SELECT
                app.id                                        AS application_id,
                app.status,
                app.created_at                               AS applied_at,
                app.updated_at                               AS last_updated,
                CONCAT(a.first_name, ' ', a.last_name)       AS applicant_name,
                u.email                                       AS applicant_email,
                a.contact_number,
                a.id                                         AS applicant_id,
                j.title                                      AS job_title,
                j.id                                         AS job_id,
                (SELECT COUNT(*) FROM interviews i
                 WHERE i.application_id = app.id)            AS interview_count,
                (SELECT MAX(i.interview_date) FROM interviews i
                 WHERE i.application_id = app.id
                   AND i.status = 'scheduled')               AS next_interview
             FROM applications app
             JOIN applicants a ON a.id = app.applicant_id
             JOIN users      u ON u.id = a.user_id
             JOIN jobs       j ON j.id = app.job_id
             WHERE {$where}
             ORDER BY app.updated_at DESC
             LIMIT ? OFFSET ?"
        );
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Count pipeline applicants for an employer (for pagination).
     *
     * @param int    $employerId
     * @param string $status
     * @param string $search
     * @return int
     */
    public function countEmployerPipeline(int $employerId, string $status = '', string $search = ''): int
    {
        $where  = 'j.employer_id = ?';
        $params = [$employerId];

        if ($status !== '') {
            $where   .= ' AND app.status = ?';
            $params[] = $status;
        }

        if ($search !== '') {
            $where   .= ' AND (a.first_name LIKE ? OR a.last_name LIKE ? OR u.email LIKE ?)';
            $term     = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM applications app
             JOIN applicants a ON a.id = app.applicant_id
             JOIN users      u ON u.id = a.user_id
             JOIN jobs       j ON j.id = app.job_id
             WHERE {$where}"
        );
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Get a single application with full applicant detail for employer review.
     *
     * @param int $applicationId
     * @param int $employerId     Ownership validation
     * @return array|null
     */
    public function getApplicationDetail(int $applicationId, int $employerId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT
                app.*,
                CONCAT(a.first_name, ' ', a.last_name) AS applicant_name,
                a.first_name,
                a.last_name,
                a.middle_name,
                a.contact_number,
                a.address,
                a.birthdate,
                a.civil_status,
                a.gender,
                a.id                                   AS applicant_id,
                u.email,
                j.title                                AS job_title,
                j.employment_type,
                j.id                                   AS job_id
             FROM applications app
             JOIN applicants a ON a.id = app.applicant_id
             JOIN users      u ON u.id = a.user_id
             JOIN jobs       j ON j.id = app.job_id
             WHERE app.id = ? AND j.employer_id = ?
             LIMIT 1"
        );
        $stmt->execute([$applicationId, $employerId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Update employer notes on an application.
     *
     * @param int    $applicationId
     * @param string $notes
     */
    public function updateNotes(int $applicationId, string $notes): void
    {
        $stmt = $this->db->prepare(
            'UPDATE applications SET employer_notes = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$notes, $applicationId]);
    }

    // ── PLACEMENT / EMPLOYMENT RECORDS ───────────────────────────

    /**
     * Record a confirmed placement (hired outcome).
     *
     * @param array $data [applicant_id, employer_id, job_id, application_id, date_hired, salary, employment_type]
     * @return int  Inserted employment record ID
     */
    public function recordPlacement(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO employment_records
             (applicant_id, employer_id, job_id, application_id, date_hired, salary, employment_type, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int)$data['applicant_id'],
            (int)$data['employer_id'],
            !empty($data['job_id']) ? (int)$data['job_id'] : null,
            !empty($data['application_id']) ? (int)$data['application_id'] : null,
            $data['date_hired']      ?? date('Y-m-d'),
            $data['salary']          ?? null,
            $data['employment_type'] ?? 'full_time',
            'active',
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Get pipeline status count summary for an employer (for dashboard cards).
     *
     * @param int $employerId
     * @return array  Keyed by status
     */
    public function getPipelineSummary(int $employerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT app.status, COUNT(*) AS total
             FROM applications app
             JOIN jobs j ON j.id = app.job_id
             WHERE j.employer_id = ?
             GROUP BY app.status'
        );
        $stmt->execute([$employerId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $summary = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            $summary[$row['status']] = (int)$row['total'];
        }

        return $summary;
    }

    // ── INTERVIEW QUERIES ────────────────────────────────────────

    /**
     * Schedule a new interview.
     *
     * @param array $data
     * @return int  Interview ID
     */
    public function scheduleInterview(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO interviews
             (application_id, referral_id, applicant_id, employer_id, job_id,
              scheduled_by, interview_date, interview_time, duration_minutes,
              interview_type, location, employer_notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            !empty($data['application_id']) ? (int)$data['application_id'] : null,
            !empty($data['referral_id'])    ? (int)$data['referral_id']    : null,
            (int)$data['applicant_id'],
            (int)$data['employer_id'],
            !empty($data['job_id'])         ? (int)$data['job_id']         : null,
            (int)$data['scheduled_by'],
            $data['interview_date'],
            $data['interview_time'],
            (int)($data['duration_minutes'] ?? 60),
            $data['interview_type']  ?? 'in_person',
            $data['location']        ?? null,
            $data['employer_notes']  ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Get all interviews for an employer with applicant info.
     *
     * @param int    $employerId
     * @param string $status     Optional status filter
     * @return array
     */
    public function getEmployerInterviews(int $employerId, string $status = ''): array
    {
        $where  = 'i.employer_id = ?';
        $params = [$employerId];

        if ($status !== '') {
            $where   .= ' AND i.status = ?';
            $params[] = $status;
        }

        $stmt = $this->db->prepare(
            "SELECT i.*,
                    CONCAT(a.first_name, ' ', a.last_name) AS applicant_name,
                    a.contact_number,
                    u.email                                AS applicant_email,
                    j.title                                AS job_title
             FROM interviews i
             JOIN applicants a ON a.id = i.applicant_id
             JOIN users      u ON u.id = a.user_id
             LEFT JOIN jobs  j ON j.id = i.job_id
             WHERE {$where}
             ORDER BY i.interview_date ASC, i.interview_time ASC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── PRIVATE HELPERS ──────────────────────────────────────────

    /**
     * Write application status history entry.
     */
    private function writeStatusHistory(
        int $applicationId,
        int $changedBy,
        string $fromStatus,
        string $toStatus,
        string $note
    ): void {
        // Only insert if table exists (graceful degradation)
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO application_status_history
                 (application_id, changed_by, from_status, to_status, note)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$applicationId, $changedBy, $fromStatus, $toStatus, $note ?: null]);
        } catch (\PDOException $e) {
            error_log('[EmploymentModel] status_history write skipped: ' . $e->getMessage());
        }
    }
}
