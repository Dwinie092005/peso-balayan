<?php

namespace App\Models;

use App\Core\Model;

/**
 * ReferralModel
 *
 * Manages admin-to-employer applicant referrals.
 * Enforces status transitions and writes immutable history.
 */
class ReferralModel extends Model
{
    protected string $table = 'referrals';

    /** Valid forward status transitions */
    private const TRANSITIONS = [
        'pending'              => ['sent', 'withdrawn'],
        'sent'                 => ['acknowledged', 'withdrawn'],
        'acknowledged'         => ['interview_scheduled', 'rejected', 'withdrawn'],
        'interview_scheduled'  => ['hired', 'rejected', 'withdrawn'],
        'hired'                => [],
        'rejected'             => [],
        'withdrawn'            => [],
    ];

    // ── CREATE ───────────────────────────────────────────────────

    /**
     * Create a new referral record.
     *
     * @param array $data [applicant_id, employer_id, job_id, referred_by, referral_notes]
     * @return int  Inserted referral ID
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO referrals
             (applicant_id, employer_id, job_id, referred_by, referral_notes, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            (int)$data['applicant_id'],
            (int)$data['employer_id'],
            !empty($data['job_id']) ? (int)$data['job_id'] : null,
            (int)$data['referred_by'],
            $data['referral_notes'] ?? null,
            'pending',
        ]);

        return (int)$this->db->lastInsertId();
    }

    // ── READ ─────────────────────────────────────────────────────

    /**
     * Find a single referral by ID with joined applicant and employer names.
     *
     * @param int $referralId
     * @return array|null
     */
    public function findById(int $referralId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*,
                    CONCAT(a.first_name, " ", a.last_name) AS applicant_name,
                    a.email                                  AS applicant_email,
                    e.company_name,
                    j.title                                  AS job_title,
                    CONCAT(u.first_name, " ", u.last_name)   AS referred_by_name
             FROM referrals r
             JOIN applicants  a ON a.id        = r.applicant_id
             JOIN employers   e ON e.id        = r.employer_id
             LEFT JOIN jobs   j ON j.id        = r.job_id
             JOIN users       u ON u.id        = r.referred_by
             WHERE r.id = ?
             LIMIT 1'
        );
        $stmt->execute([$referralId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Get all referrals visible to an employer.
     *
     * @param int    $employerId
     * @param string $status     Optional filter
     * @param int    $limit
     * @param int    $offset
     * @return array
     */
    public function getByEmployer(int $employerId, string $status = '', int $limit = 20, int $offset = 0): array
    {
        $where  = 'r.employer_id = ?';
        $params = [$employerId];

        if ($status !== '') {
            $where   .= ' AND r.status = ?';
            $params[] = $status;
        }

        $stmt = $this->db->prepare(
            "SELECT r.*,
                    CONCAT(a.first_name, ' ', a.last_name) AS applicant_name,
                    a.email                                 AS applicant_email,
                    a.contact_number,
                    j.title                                 AS job_title
             FROM referrals r
             JOIN applicants a ON a.id = r.applicant_id
             LEFT JOIN jobs  j ON j.id = r.job_id
             WHERE {$where}
             ORDER BY r.referred_at DESC
             LIMIT ? OFFSET ?"
        );
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all referrals (admin view) with optional filters.
     *
     * @param array $filters [status, employer_id, applicant_id, date_from, date_to]
     * @param int   $limit
     * @param int   $offset
     * @return array
     */
    public function getAll(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $where  = '1 = 1';
        $params = [];

        if (!empty($filters['status'])) {
            $where   .= ' AND r.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['employer_id'])) {
            $where   .= ' AND r.employer_id = ?';
            $params[] = (int)$filters['employer_id'];
        }

        if (!empty($filters['applicant_id'])) {
            $where   .= ' AND r.applicant_id = ?';
            $params[] = (int)$filters['applicant_id'];
        }

        if (!empty($filters['date_from'])) {
            $where   .= ' AND DATE(r.referred_at) >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where   .= ' AND DATE(r.referred_at) <= ?';
            $params[] = $filters['date_to'];
        }

        $stmt = $this->db->prepare(
            "SELECT r.*,
                    CONCAT(a.first_name, ' ', a.last_name) AS applicant_name,
                    e.company_name,
                    j.title                                 AS job_title,
                    CONCAT(u.first_name, ' ', u.last_name)  AS referred_by_name
             FROM referrals r
             JOIN applicants a ON a.id = r.applicant_id
             JOIN employers  e ON e.id = r.employer_id
             LEFT JOIN jobs  j ON j.id = r.job_id
             JOIN users      u ON u.id = r.referred_by
             WHERE {$where}
             ORDER BY r.referred_at DESC
             LIMIT ? OFFSET ?"
        );
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Count total referrals matching filters (for pagination).
     *
     * @param array $filters
     * @return int
     */
    public function countAll(array $filters = []): int
    {
        $where  = '1 = 1';
        $params = [];

        if (!empty($filters['status'])) {
            $where   .= ' AND r.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['employer_id'])) {
            $where   .= ' AND r.employer_id = ?';
            $params[] = (int)$filters['employer_id'];
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM referrals r WHERE {$where}"
        );
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Get referral history (status change log) for a referral.
     *
     * @param int $referralId
     * @return array
     */
    public function getHistory(int $referralId): array
    {
        $stmt = $this->db->prepare(
            'SELECT rh.*,
                    CONCAT(u.first_name, " ", u.last_name) AS changed_by_name
             FROM referral_history rh
             JOIN users u ON u.id = rh.changed_by
             WHERE rh.referral_id = ?
             ORDER BY rh.changed_at ASC'
        );
        $stmt->execute([$referralId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── STATUS TRANSITIONS ───────────────────────────────────────

    /**
     * Transition a referral to a new status.
     * Validates the transition, updates the record, and writes history.
     *
     * @param int    $referralId
     * @param string $newStatus
     * @param int    $changedBy   user_id performing the action
     * @param string $note        Optional note for history
     * @return bool
     */
    public function transition(int $referralId, string $newStatus, int $changedBy, string $note = ''): bool
    {
        $referral = $this->findById($referralId);
        if (!$referral) return false;

        $currentStatus = $referral['status'];
        $allowed       = self::TRANSITIONS[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            return false;
        }

        // Update referral status
        $extraFields = '';
        if ($newStatus === 'sent') {
            $extraFields = ', acknowledged_at = NULL';
        } elseif ($newStatus === 'acknowledged') {
            $extraFields = ', acknowledged_at = NOW()';
        } elseif (in_array($newStatus, ['hired', 'rejected', 'withdrawn'], true)) {
            $extraFields = ', resolved_at = NOW()';
        }

        $stmt = $this->db->prepare(
            "UPDATE referrals SET status = ?, updated_at = NOW() {$extraFields} WHERE id = ?"
        );
        $stmt->execute([$newStatus, $referralId]);

        // Write history
        $this->writeHistory($referralId, $changedBy, $currentStatus, $newStatus, $note);

        return true;
    }

    /**
     * Update employer feedback notes on a referral.
     *
     * @param int    $referralId
     * @param string $notes
     */
    public function updateEmployerNotes(int $referralId, string $notes): void
    {
        $stmt = $this->db->prepare(
            'UPDATE referrals SET employer_notes = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$notes, $referralId]);
    }

    // ── HISTORY ──────────────────────────────────────────────────

    /**
     * Write an immutable referral history entry.
     *
     * @param int    $referralId
     * @param int    $changedBy
     * @param string $fromStatus
     * @param string $toStatus
     * @param string $note
     */
    private function writeHistory(
        int $referralId,
        int $changedBy,
        string $fromStatus,
        string $toStatus,
        string $note = ''
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO referral_history
             (referral_id, changed_by, from_status, to_status, note)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$referralId, $changedBy, $fromStatus, $toStatus, $note ?: null]);
    }

    /**
     * Check if an applicant has already been referred to a specific employer/job.
     *
     * @param int      $applicantId
     * @param int      $employerId
     * @param int|null $jobId
     * @return bool
     */
    public function isDuplicate(int $applicantId, int $employerId, ?int $jobId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM referrals
                   WHERE applicant_id = ? AND employer_id = ?
                     AND status NOT IN ("rejected", "withdrawn")';
        $params = [$applicantId, $employerId];

        if ($jobId !== null) {
            $sql    .= ' AND job_id = ?';
            $params[] = $jobId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }
}
