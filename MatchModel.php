<?php

/**
 * MatchModel
 * PESO Balayan — Data access layer for matches, match_queue, match_history.
 *
 * All queries use prepared statements. No raw values interpolated into SQL.
 * Location: /app/models/MatchModel.php
 */

class MatchModel
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================================================
    // MATCH QUEUE
    // =========================================================================

    /**
     * Add an application to the matching queue (idempotent — skips duplicates).
     */
    public function enqueue(int $applicationId, int $priority = 5): bool
    {
        // Skip if already waiting or processing
        $existing = $this->db->fetchOne(
            "SELECT id FROM match_queue
             WHERE application_id = ? AND status IN ('waiting', 'processing')
             LIMIT 1",
            [$applicationId]
        );

        if ($existing) {
            return true;
        }

        $rows = $this->db->execute(
            "INSERT INTO match_queue (application_id, priority, status, scheduled_at)
             VALUES (?, ?, 'waiting', NOW())",
            [$applicationId, $priority]
        );

        return $rows > 0;
    }

    /**
     * Fetch next batch of waiting queue items, ordered by priority then age.
     */
    public function getWaitingQueue(int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT mq.*, a.id AS app_id, a.applicant_id, a.job_id
             FROM match_queue mq
             JOIN applications a ON a.id = mq.application_id
             WHERE mq.status = 'waiting'
               AND mq.attempts < mq.max_attempts
               AND mq.scheduled_at <= NOW()
             ORDER BY mq.priority ASC, mq.scheduled_at ASC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Mark a queue item as processing (claim it).
     */
    public function claimQueueItem(int $queueId): bool
    {
        $rows = $this->db->execute(
            "UPDATE match_queue
             SET status = 'processing', attempts = attempts + 1, last_attempted_at = NOW()
             WHERE id = ? AND status = 'waiting'",
            [$queueId]
        );
        return $rows > 0;
    }

    /**
     * Mark a queue item as completed or failed.
     */
    public function resolveQueueItem(int $queueId, string $status, string $error = ''): void
    {
        $this->db->execute(
            "UPDATE match_queue
             SET status = ?, error_message = ?, updated_at = NOW()
             WHERE id = ?",
            [$status, $error ?: null, $queueId]
        );
    }

    /**
     * Re-schedule a failed queue item for retry after a delay.
     */
    public function requeueFailed(int $queueId, int $delayMinutes = 60): void
    {
        $this->db->execute(
            "UPDATE match_queue
             SET status = 'waiting', scheduled_at = DATE_ADD(NOW(), INTERVAL ? MINUTE)
             WHERE id = ? AND attempts < max_attempts",
            [$delayMinutes, $queueId]
        );
    }

    // =========================================================================
    // MATCHES — WRITE
    // =========================================================================

    /**
     * Insert or update a match record (upsert on application_id + job_id).
     * Returns the match ID.
     */
    public function upsertMatch(array $data): int
    {
        $this->db->execute(
            "INSERT INTO matches
                (application_id, applicant_id, job_id,
                 skill_score, education_score, experience_score, location_score,
                 total_score, score_breakdown, status, matched_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                skill_score      = VALUES(skill_score),
                education_score  = VALUES(education_score),
                experience_score = VALUES(experience_score),
                location_score   = VALUES(location_score),
                total_score      = VALUES(total_score),
                score_breakdown  = VALUES(score_breakdown),
                status           = VALUES(status),
                matched_at       = NOW(),
                updated_at       = NOW()",
            [
                $data['application_id'],
                $data['applicant_id'],
                $data['job_id'],
                $data['skill_score'],
                $data['education_score'],
                $data['experience_score'],
                $data['location_score'],
                $data['total_score'],
                json_encode($data['score_breakdown']),
                $data['status'],
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update match status and record the reviewer.
     */
    public function updateStatus(int $matchId, string $status, int $reviewerId, string $notes = ''): bool
    {
        $rows = $this->db->execute(
            "UPDATE matches
             SET status       = ?,
                 admin_notes  = ?,
                 reviewed_by  = ?,
                 reviewed_at  = NOW(),
                 updated_at   = NOW()
             WHERE id = ?",
            [$status, $notes ?: null, $reviewerId, $matchId]
        );
        return $rows > 0;
    }

    /**
     * Increment rematch_count for a match.
     */
    public function incrementRematchCount(int $matchId): void
    {
        $this->db->execute(
            "UPDATE matches SET rematch_count = rematch_count + 1 WHERE id = ?",
            [$matchId]
        );
    }

    // =========================================================================
    // MATCHES — READ
    // =========================================================================

    /**
     * Get paginated matches with applicant + job info joined.
     */
    public function getPaginated(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildWhereClause($filters);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                    m.*,
                    CONCAT(ap.first_name, ' ', ap.last_name) AS applicant_name,
                    ap.applicant_code,
                    ap.photo                                  AS applicant_photo,
                    j.title                                   AS job_title,
                    j.position                                AS job_position,
                    e.company_name,
                    u.name                                    AS reviewer_name
                FROM matches m
                JOIN applicants ap  ON ap.id   = m.applicant_id
                JOIN jobs j         ON j.id    = m.job_id
                JOIN employers e    ON e.id    = j.employer_id
                LEFT JOIN users u   ON u.id    = m.reviewed_by
                {$where}
                ORDER BY m.total_score DESC, m.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Count total matches matching the current filters.
     */
    public function countFiltered(array $filters = []): int
    {
        [$where, $params] = $this->buildWhereClause($filters);

        $result = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM matches m {$where}",
            $params
        );

        return (int) ($result['total'] ?? 0);
    }

    /**
     * Get a single match with full details.
     */
    public function getById(int $matchId): ?array
    {
        return $this->db->fetchOne(
            "SELECT
                m.*,
                CONCAT(ap.first_name, ' ', ap.last_name) AS applicant_name,
                ap.applicant_code,
                ap.photo                                  AS applicant_photo,
                ap.education_level,
                ap.years_experience,
                ap.city                                   AS applicant_city,
                ap.province                               AS applicant_province,
                j.title                                   AS job_title,
                j.position                                AS job_position,
                j.required_education,
                j.required_experience,
                j.city                                    AS job_city,
                j.province                                AS job_province,
                e.company_name,
                e.id                                      AS employer_id,
                u.name                                    AS reviewer_name
             FROM matches m
             JOIN applicants ap  ON ap.id = m.applicant_id
             JOIN jobs j         ON j.id  = m.job_id
             JOIN employers e    ON e.id  = j.employer_id
             LEFT JOIN users u   ON u.id  = m.reviewed_by
             WHERE m.id = ?
             LIMIT 1",
            [$matchId]
        );
    }

    /**
     * Get all matches for a specific application.
     */
    public function getByApplicationId(int $applicationId): array
    {
        return $this->db->fetchAll(
            "SELECT m.*, j.title AS job_title, e.company_name
             FROM matches m
             JOIN jobs j      ON j.id = m.job_id
             JOIN employers e ON e.id = j.employer_id
             WHERE m.application_id = ?
             ORDER BY m.total_score DESC",
            [$applicationId]
        );
    }

    /**
     * Get top N matches for admin dashboard summary.
     */
    public function getTopMatches(int $limit = 10, string $status = 'qualified'): array
    {
        return $this->db->fetchAll(
            "SELECT
                m.id, m.total_score, m.status,
                CONCAT(ap.first_name, ' ', ap.last_name) AS applicant_name,
                ap.applicant_code,
                j.title AS job_title, e.company_name
             FROM matches m
             JOIN applicants ap  ON ap.id = m.applicant_id
             JOIN jobs j         ON j.id  = m.job_id
             JOIN employers e    ON e.id  = j.employer_id
             WHERE m.status = ?
             ORDER BY m.total_score DESC
             LIMIT ?",
            [$status, $limit]
        );
    }

    /**
     * Get dashboard stats counts.
     */
    public function getDashboardStats(): array
    {
        $result = $this->db->fetchOne(
            "SELECT
                COUNT(*)                                        AS total_matches,
                SUM(status = 'pending')                        AS pending,
                SUM(status = 'qualified')                      AS qualified,
                SUM(status = 'under_review')                   AS under_review,
                SUM(status = 'approved')                       AS approved,
                SUM(status = 'rejected')                       AS rejected,
                SUM(status = 'referred')                       AS referred,
                SUM(status = 'no_match')                       AS no_match,
                SUM(status = 'disqualified')                   AS disqualified,
                ROUND(AVG(total_score), 2)                     AS avg_score,
                SUM(total_score >= ?)                          AS above_threshold,
                SUM(total_score < ?)                           AS below_threshold
             FROM matches",
            [MATCH_QUALIFY_THRESHOLD, MATCH_QUALIFY_THRESHOLD]
        );

        return $result ?? [];
    }

    /**
     * Get matches with scores below the qualification threshold.
     */
    public function getLowScoreAlerts(int $limit = 5): array
    {
        return $this->db->fetchAll(
            "SELECT m.id, m.total_score, m.status,
                    CONCAT(ap.first_name, ' ', ap.last_name) AS applicant_name,
                    j.title AS job_title
             FROM matches m
             JOIN applicants ap ON ap.id = m.applicant_id
             JOIN jobs j        ON j.id  = m.job_id
             WHERE m.status IN ('pending','qualified')
               AND m.total_score < ?
             ORDER BY m.total_score ASC
             LIMIT ?",
            [MATCH_QUALIFY_THRESHOLD, $limit]
        );
    }

    // =========================================================================
    // MATCH HISTORY
    // =========================================================================

    /**
     * Record a status transition in the audit log.
     */
    public function recordHistory(
        int    $matchId,
        string $toStatus,
        string $fromStatus = '',
        int    $changedBy  = 0,
        string $notes      = '',
        array  $metadata   = []
    ): void {
        $this->db->execute(
            "INSERT INTO match_history
                (match_id, from_status, to_status, changed_by, notes, metadata)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $matchId,
                $fromStatus ?: null,
                $toStatus,
                $changedBy  ?: null,
                $notes      ?: null,
                $metadata   ? json_encode($metadata) : null,
            ]
        );
    }

    /**
     * Get full history timeline for a match.
     */
    public function getHistory(int $matchId): array
    {
        return $this->db->fetchAll(
            "SELECT mh.*, u.name AS changed_by_name
             FROM match_history mh
             LEFT JOIN users u ON u.id = mh.changed_by
             WHERE mh.match_id = ?
             ORDER BY mh.created_at ASC",
            [$matchId]
        );
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Build a reusable WHERE clause from a filters array.
     * Returns [sql_fragment, params_array].
     */
    private function buildWhereClause(array $filters): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($filters['status'])) {
            $conditions[] = 'm.status = ?';
            $params[]     = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = "(CONCAT(ap.first_name,' ',ap.last_name) LIKE ?
                              OR ap.applicant_code LIKE ?
                              OR j.title LIKE ?)";
            $term     = '%' . $filters['search'] . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (isset($filters['min_score']) && $filters['min_score'] !== '') {
            $conditions[] = 'm.total_score >= ?';
            $params[]     = (float) $filters['min_score'];
        }

        if (isset($filters['max_score']) && $filters['max_score'] !== '') {
            $conditions[] = 'm.total_score <= ?';
            $params[]     = (float) $filters['max_score'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'DATE(m.created_at) >= ?';
            $params[]     = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'DATE(m.created_at) <= ?';
            $params[]     = $filters['date_to'];
        }

        if (!empty($filters['job_id'])) {
            $conditions[] = 'm.job_id = ?';
            $params[]     = (int) $filters['job_id'];
        }

        // Always join applicants + jobs for search/display — ensure JOIN present
        $joins = "JOIN applicants ap ON ap.id = m.applicant_id
                  JOIN jobs j        ON j.id  = m.job_id";

        $where = $conditions
            ? $joins . ' WHERE ' . implode(' AND ', $conditions)
            : $joins;

        return [$where, $params];
    }
}
