<?php

/**
 * MatchingService
 * PESO Balayan — Orchestrates the full matching pipeline.
 *
 * Responsibilities:
 *  - Queue an application for matching
 *  - Process the queue batch (called by cron or admin trigger)
 *  - Fetch applicant + job data needed for scoring
 *  - Delegate scoring to ScoringService
 *  - Persist results via MatchModel
 *  - Record history on every transition
 *  - Trigger no-match flow when rematch limit is reached
 *
 * Location: /app/services/MatchingService.php
 */

class MatchingService
{
    private MatchModel     $matchModel;
    private ScoringService $scorer;
    private Database       $db;

    public function __construct()
    {
        $this->matchModel = new MatchModel();
        $this->scorer     = new ScoringService();
        $this->db         = Database::getInstance();
    }

    // =========================================================================
    // QUEUE
    // =========================================================================

    /**
     * Enqueue an application for matching.
     * Called immediately after an application is saved.
     */
    public function queue(int $applicationId, int $priority = 5): bool
    {
        return $this->matchModel->enqueue($applicationId, $priority);
    }

    // =========================================================================
    // BATCH PROCESSING  (cron / admin trigger)
    // =========================================================================

    /**
     * Process up to $batchSize waiting queue items.
     * Returns a summary report array.
     */
    public function processBatch(int $batchSize = 20): array
    {
        $report = [
            'processed' => 0,
            'succeeded' => 0,
            'failed'    => 0,
            'skipped'   => 0,
            'errors'    => [],
        ];

        $queue = $this->matchModel->getWaitingQueue($batchSize);

        foreach ($queue as $item) {
            $claimed = $this->matchModel->claimQueueItem((int) $item['id']);
            if (!$claimed) {
                $report['skipped']++;
                continue;
            }

            $report['processed']++;

            try {
                $this->processApplication((int) $item['application_id']);
                $this->matchModel->resolveQueueItem((int) $item['id'], 'completed');
                $report['succeeded']++;
            } catch (\Exception $e) {
                $error = $e->getMessage();
                $this->matchModel->resolveQueueItem((int) $item['id'], 'failed', $error);
                $report['failed']++;
                $report['errors'][] = [
                    'application_id' => $item['application_id'],
                    'error'          => $error,
                ];
                error_log('[MatchingService] Error processing application '
                    . $item['application_id'] . ': ' . $error);
            }
        }

        return $report;
    }

    /**
     * Manually (re)trigger matching for a single application.
     * Used by the admin "Trigger Match" button.
     */
    public function triggerForApplication(int $applicationId, int $adminId = 0): array
    {
        try {
            $result = $this->processApplication($applicationId);

            if ($adminId) {
                // Record manual trigger in history
                if (!empty($result['match_id'])) {
                    $this->matchModel->recordHistory(
                        (int) $result['match_id'],
                        $result['status'],
                        '',
                        $adminId,
                        'Manually triggered by admin'
                    );
                }
            }

            return ['success' => true, 'result' => $result];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // CORE MATCHING LOGIC
    // =========================================================================

    /**
     * Run the scoring engine for one application.
     * Saves/updates the match record and returns the result.
     *
     * @throws \RuntimeException if application or job data is missing.
     */
    private function processApplication(int $applicationId): array
    {
        // 1. Load application + applicant + job data
        $application = $this->loadApplicationData($applicationId);
        if (!$application) {
            throw new \RuntimeException("Application #{$applicationId} not found.");
        }

        $applicant = $this->loadApplicantData((int) $application['applicant_id']);
        if (!$applicant) {
            throw new \RuntimeException(
                "Applicant #{$application['applicant_id']} not found."
            );
        }

        $job = $this->loadJobData((int) $application['job_id']);
        if (!$job) {
            throw new \RuntimeException("Job #{$application['job_id']} not found.");
        }

        // 2. Compute score
        $scoreResult = $this->scorer->computeScore($applicant, $job);

        // 3. Persist result
        $matchData = [
            'application_id'  => $applicationId,
            'applicant_id'    => (int) $application['applicant_id'],
            'job_id'          => (int) $application['job_id'],
            'skill_score'     => $scoreResult['skill_score'],
            'education_score' => $scoreResult['education_score'],
            'experience_score'=> $scoreResult['experience_score'],
            'location_score'  => $scoreResult['location_score'],
            'total_score'     => $scoreResult['total_score'],
            'score_breakdown' => $scoreResult['score_breakdown'],
            'status'          => $scoreResult['status'],
        ];

        $matchId = $this->matchModel->upsertMatch($matchData);

        // 4. Record history
        $this->matchModel->recordHistory(
            $matchId,
            $scoreResult['status'],
            'pending',
            0,
            'Computed by matching engine',
            ['total_score' => $scoreResult['total_score']]
        );

        // 5. Handle no-match flow if disqualified
        if ($scoreResult['status'] === 'disqualified') {
            $this->handleDisqualified($matchId, $applicationId);
        }

        return array_merge($matchData, ['match_id' => $matchId]);
    }

    // =========================================================================
    // NO-MATCH FLOW
    // =========================================================================

    /**
     * Handle the no-match / disqualified flow.
     * Increments rematch count. If limit reached → triggers no_match status.
     */
    private function handleDisqualified(int $matchId, int $applicationId): void
    {
        $this->matchModel->incrementRematchCount($matchId);

        $match = $this->matchModel->getById($matchId);
        if (!$match) return;

        if ((int) $match['rematch_count'] >= REMATCH_LIMIT) {
            $this->matchModel->updateStatus($matchId, 'no_match', 0,
                'Rematch limit reached — moved to no-match queue.');

            $this->matchModel->recordHistory(
                $matchId, 'no_match', 'disqualified', 0,
                'Rematch limit (' . REMATCH_LIMIT . ') reached'
            );

            // Update application status to no_match
            $this->db->execute(
                "UPDATE applications SET status = 'no_match' WHERE id = ?",
                [$applicationId]
            );
        }
    }

    /**
     * Retry matching for applicants in no-match / disqualified status.
     * Re-queues them with lower priority for the next batch run.
     */
    public function retryNoMatchApplicants(): array
    {
        $applications = $this->db->fetchAll(
            "SELECT DISTINCT a.id AS application_id
             FROM applications a
             JOIN matches m ON m.application_id = a.id
             WHERE m.status IN ('no_match', 'disqualified')
               AND m.rematch_count < ?
               AND a.status NOT IN ('inactive', 'archived', 'hired')
             ORDER BY m.created_at ASC
             LIMIT 50",
            [REMATCH_LIMIT]
        );

        $queued = 0;
        foreach ($applications as $app) {
            if ($this->matchModel->enqueue((int) $app['application_id'], 8)) {
                $queued++;
            }
        }

        return ['queued' => $queued];
    }

    // =========================================================================
    // ADMIN ACTIONS (approve / reject)
    // =========================================================================

    /**
     * Approve a match — admin has reviewed and wants to refer the applicant.
     */
    public function approveMatch(int $matchId, int $adminId, string $notes = ''): bool
    {
        $match = $this->matchModel->getById($matchId);
        if (!$match) return false;

        $fromStatus = $match['status'];
        $updated    = $this->matchModel->updateStatus($matchId, 'approved', $adminId, $notes);

        if ($updated) {
            $this->matchModel->recordHistory(
                $matchId, 'approved', $fromStatus, $adminId, $notes
            );
            // Update application status to 'matched'
            $this->db->execute(
                "UPDATE applications SET status = 'matched', updated_at = NOW()
                 WHERE id = ?",
                [(int) $match['application_id']]
            );
        }

        return $updated;
    }

    /**
     * Reject a match — admin keeps applicant in pool for re-matching.
     */
    public function rejectMatch(int $matchId, int $adminId, string $notes = ''): bool
    {
        $match = $this->matchModel->getById($matchId);
        if (!$match) return false;

        $fromStatus = $match['status'];
        $updated    = $this->matchModel->updateStatus($matchId, 'rejected', $adminId, $notes);

        if ($updated) {
            $this->matchModel->recordHistory(
                $matchId, 'rejected', $fromStatus, $adminId, $notes
            );
        }

        return $updated;
    }

    /**
     * Mark a match as under_review (admin opened the review page).
     */
    public function markUnderReview(int $matchId, int $adminId): void
    {
        $match = $this->matchModel->getById($matchId);
        if (!$match || $match['status'] !== 'qualified') return;

        $this->matchModel->updateStatus($matchId, 'under_review', $adminId);
        $this->matchModel->recordHistory(
            $matchId, 'under_review', $match['status'], $adminId, 'Admin opened for review'
        );
    }

    // =========================================================================
    // DATA LOADERS
    // =========================================================================

    /**
     * Load application row.
     */
    private function loadApplicationData(int $applicationId): ?array
    {
        return $this->db->fetchOne(
            "SELECT id, applicant_id, job_id, status
             FROM applications WHERE id = ? LIMIT 1",
            [$applicationId]
        );
    }

    /**
     * Load applicant row with their skills array.
     */
    private function loadApplicantData(int $applicantId): ?array
    {
        $applicant = $this->db->fetchOne(
            "SELECT
                ap.*,
                ap.city     AS applicant_city,
                ap.province AS applicant_province
             FROM applicants ap
             WHERE ap.id = ? LIMIT 1",
            [$applicantId]
        );

        if (!$applicant) return null;

        // Load applicant's skills as flat array of names
        $skillRows = $this->db->fetchAll(
            "SELECT s.name
             FROM applicant_skills aps
             JOIN skills s ON s.id = aps.skill_id
             WHERE aps.applicant_id = ?",
            [$applicantId]
        );

        $applicant['skills'] = array_column($skillRows, 'name');

        return $applicant;
    }

    /**
     * Load job row with required skills array.
     */
    private function loadJobData(int $jobId): ?array
    {
        $job = $this->db->fetchOne(
            "SELECT j.*, e.city AS employer_city, e.province AS employer_province
             FROM jobs j
             JOIN employers e ON e.id = j.employer_id
             WHERE j.id = ? AND j.status = 'open'
             LIMIT 1",
            [$jobId]
        );

        if (!$job) return null;

        // Load required skills as flat array of names
        $skillRows = $this->db->fetchAll(
            "SELECT s.name
             FROM job_skills js
             JOIN skills s ON s.id = js.skill_id
             WHERE js.job_id = ?",
            [$jobId]
        );

        $job['required_skills'] = array_column($skillRows, 'name');

        // Resolve city/province from job directly or fall back to employer location
        if (empty($job['city']))     $job['city']     = $job['employer_city']     ?? '';
        if (empty($job['province'])) $job['province'] = $job['employer_province'] ?? '';

        return $job;
    }
}
