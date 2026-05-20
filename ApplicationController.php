<?php
/**
 * PESO Balayan IMIS — Application Controller
 * File: app/controllers/applications/ApplicationController.php
 *
 * Handles: submit, withdraw, status updates, timeline, employer review,
 *          matching queue trigger, resume upload, notification dispatch
 * Depends: ApplicationModel, JobModel, Auth, SecurityHelper, AuditService
 */

namespace App\Controllers\Applications;

use App\Core\BaseController;
use App\Core\Auth;
use App\Core\SecurityHelper;
use App\Models\ApplicationModel;
use App\Models\JobModel;
use finfo;

class ApplicationController extends BaseController
{
    private ApplicationModel $appModel;
    private JobModel         $jobModel;

    /** Allowed resume MIME types */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /** Allowed resume extensions */
    private const ALLOWED_EXTS = ['pdf', 'doc', 'docx'];

    /** Max resume file size (5 MB) */
    private const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

    /** Status transitions allowed per role */
    private const ADMIN_TRANSITIONS = [
        'pending'     => ['reviewed', 'rejected'],
        'reviewed'    => ['shortlisted', 'rejected'],
        'shortlisted' => ['referred',   'rejected'],
        'referred'    => ['hired',      'rejected'],
        'hired'       => [],
        'rejected'    => [],
        'withdrawn'   => [],
    ];

    private const EMPLOYER_TRANSITIONS = [
        'referred'    => ['hired', 'rejected'],
        'hired'       => [],
        'rejected'    => [],
        'pending'     => [],
        'reviewed'    => [],
        'shortlisted' => [],
        'withdrawn'   => [],
    ];

    public function __construct()
    {
        parent::__construct();
        $db             = $this->getDB();
        $this->appModel = new ApplicationModel($db);
        $this->jobModel = new JobModel($db);
    }

    // ──────────────────────────────────────────────────────────
    // APPLICANT — Submit application
    // ──────────────────────────────────────────────────────────

    /**
     * POST /jobs/{jobId}/apply
     *
     * Flow: validate → duplicate check → upload resume → persist
     *       → log timeline → trigger matching → notify → respond
     */
    public function apply(int $jobId): void
    {
        Auth::requireRole(['applicant']);
        $this->requirePost();
        SecurityHelper::verifyCsrf($_POST['_csrf_token'] ?? '');

        // ── Verify job exists and is active ──────────────────
        $job = $this->jobModel->getById($jobId);
        if (!$job || $job['status'] !== 'active') {
            $this->jsonError('This job vacancy is no longer accepting applications.', 422);
            return;
        }

        // ── Resolve applicant ─────────────────────────────────
        $applicantId = $this->resolveApplicantId();

        // ── Check for expired listing ─────────────────────────
        if (!empty($job['expires_at']) && $job['expires_at'] < date('Y-m-d')) {
            $this->jsonError('This job vacancy has expired.', 422);
            return;
        }

        // ── Duplicate application guard ───────────────────────
        if ($this->appModel->hasApplied($applicantId, $jobId)) {
            $this->jsonError('You have already applied for this position.', 409);
            return;
        }

        // ── Handle resume upload ──────────────────────────────
        $resumePath = null;
        try {
            $resumePath = $this->handleResumeUpload();
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 422);
            return;
        }

        // ── Validate cover letter length ──────────────────────
        $coverLetter = trim($_POST['cover_letter'] ?? '');
        if ($coverLetter !== '' && mb_strlen($coverLetter) > 3000) {
            $this->jsonError('Cover letter must not exceed 3,000 characters.', 422);
            return;
        }

        // ── Persist application ───────────────────────────────
        $appId = $this->appModel->apply([
            'job_id'       => $jobId,
            'applicant_id' => $applicantId,
            'resume_path'  => $resumePath,
            'cover_letter' => $coverLetter ?: null,
        ]);

        // ── Trigger matching engine (non-blocking) ────────────
        $this->dispatchMatchingQueue($appId, $applicantId, $jobId);

        // ── Send notification to admin ────────────────────────
        $this->dispatchNotification('new_application', [
            'application_id' => $appId,
            'job_id'         => $jobId,
            'applicant_id'   => $applicantId,
        ]);

        // ── Audit log ─────────────────────────────────────────
        $this->auditLog(
            'application_submitted',
            "Application #{$appId} submitted for Job #{$jobId}",
            $applicantId
        );

        $this->json([
            'success'        => true,
            'application_id' => $appId,
            'message'        => 'Application submitted successfully.',
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // APPLICANT — My applications list
    // ──────────────────────────────────────────────────────────

    /**
     * GET /applicant/applications
     */
    public function myApplications(): void
    {
        Auth::requireRole(['applicant']);

        $applicantId  = $this->resolveApplicantId();
        $applications = $this->appModel->getByApplicant($applicantId);
        $stats        = $this->appModel->getStatsForApplicant($applicantId);

        $this->render('applications/my-applications', [
            'title'        => 'My Applications',
            'applications' => $applications,
            'stats'        => $stats,
            'statusLabels' => ApplicationModel::STATUS_LABELS,
            'statusColors' => ApplicationModel::STATUS_COLORS,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // APPLICANT — Withdraw application
    // ──────────────────────────────────────────────────────────

    /**
     * POST /applicant/applications/{id}/withdraw
     */
    public function withdraw(int $applicationId): void
    {
        Auth::requireRole(['applicant']);
        $this->requirePost();
        SecurityHelper::verifyCsrf($_POST['_csrf_token'] ?? '');

        $applicantId = $this->resolveApplicantId();
        $application = $this->appModel->getById($applicationId);

        if (!$application) {
            $this->jsonError('Application not found.', 404);
            return;
        }

        // Ownership check
        if ((int)$application['applicant_id'] !== $applicantId) {
            $this->jsonError('Unauthorized.', 403);
            return;
        }

        // Cannot withdraw terminal statuses
        $terminal = ['hired', 'rejected', 'withdrawn'];
        if (in_array($application['status'], $terminal, true)) {
            $this->jsonError('This application cannot be withdrawn at its current status.', 422);
            return;
        }

        $ok = $this->appModel->withdraw($applicationId, $applicantId);
        if (!$ok) {
            $this->jsonError('Unable to withdraw application.', 500);
            return;
        }

        $this->auditLog(
            'application_withdrawn',
            "Application #{$applicationId} withdrawn by applicant #{$applicantId}",
            $applicantId
        );

        $this->json(['success' => true, 'message' => 'Application withdrawn.']);
    }

    // ──────────────────────────────────────────────────────────
    // ADMIN / EMPLOYER — Update application status
    // ──────────────────────────────────────────────────────────

    /**
     * POST /admin/applications/{id}/status
     * POST /employer/applications/{id}/status
     *
     * Admin can move forward through the full pipeline.
     * Employer can only mark referred applications as hired/rejected.
     */
    public function updateStatus(int $applicationId): void
    {
        Auth::requireRole(['admin', 'super_admin', 'employer']);
        $this->requirePost();
        SecurityHelper::verifyCsrf($_POST['_csrf_token'] ?? '');

        $role        = Auth::role();
        $application = $this->appModel->getById($applicationId);

        if (!$application) {
            $this->jsonError('Application not found.', 404);
            return;
        }

        // Employer must own the related job
        if ($role === 'employer') {
            $this->assertEmployerOwnsJob((int)$application['job_id']);
        }

        $currentStatus = $application['status'];
        $newStatus     = trim($_POST['status'] ?? '');
        $notes         = trim($_POST['notes']  ?? '');

        // Validate transition
        $allowedNext = $this->getAllowedTransitions($role, $currentStatus);
        if (!in_array($newStatus, $allowedNext, true)) {
            $this->jsonError(
                "Cannot move from '{$currentStatus}' to '{$newStatus}' as {$role}.",
                422
            );
            return;
        }

        // Employer notes vs admin notes
        if ($role === 'employer') {
            $this->getDB()->prepare(
                "UPDATE applications SET employer_notes = :n WHERE id = :id"
            )->execute([':n' => $notes ?: null, ':id' => $applicationId]);
        } elseif ($notes) {
            $this->getDB()->prepare(
                "UPDATE applications SET admin_notes = :n WHERE id = :id"
            )->execute([':n' => $notes, ':id' => $applicationId]);
        }

        $changedById = Auth::id();
        $ok = $this->appModel->updateStatus($applicationId, $newStatus, $notes, $changedById);

        if (!$ok) {
            $this->jsonError('Failed to update application status.', 500);
            return;
        }

        // Notify applicant of status change
        $this->dispatchNotification('status_changed', [
            'application_id' => $applicationId,
            'new_status'     => $newStatus,
            'applicant_id'   => (int)$application['applicant_id'],
        ]);

        // If hired — update employment record
        if ($newStatus === 'hired') {
            $this->recordEmployment($application);
        }

        $this->auditLog(
            'application_status_updated',
            "Application #{$applicationId}: {$currentStatus} → {$newStatus}",
            $changedById
        );

        $this->json([
            'success'    => true,
            'new_status' => $newStatus,
            'label'      => ApplicationModel::STATUS_LABELS[$newStatus] ?? $newStatus,
            'message'    => 'Application status updated.',
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // ANY AUTHORISED — View timeline
    // ──────────────────────────────────────────────────────────

    /**
     * GET /applications/{id}/timeline  |  AJAX
     *
     * Applicant sees own; admin/employer see all (with ownership check).
     */
    public function timeline(int $applicationId): void
    {
        Auth::requireAuth();

        $application = $this->appModel->getById($applicationId);
        if (!$application) {
            $this->jsonError('Application not found.', 404);
            return;
        }

        $this->assertCanViewApplication($application);

        $timeline = $this->appModel->getTimeline($applicationId);

        $this->json([
            'success'  => true,
            'timeline' => $timeline,
            'labels'   => ApplicationModel::STATUS_LABELS,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // ADMIN / EMPLOYER — Review single application
    // ──────────────────────────────────────────────────────────

    /**
     * GET /admin/applications/{id}
     * GET /employer/applications/{id}
     */
    public function review(int $applicationId): void
    {
        Auth::requireRole(['admin', 'super_admin', 'employer']);

        $application = $this->appModel->getById($applicationId);
        if (!$application) {
            $this->abort(404, 'Application not found.');
            return;
        }

        $role = Auth::role();
        if ($role === 'employer') {
            $this->assertEmployerOwnsJob((int)$application['job_id']);
        }

        $timeline       = $this->appModel->getTimeline($applicationId);
        $currentStatus  = $application['status'];
        $allowedNext    = $this->getAllowedTransitions($role, $currentStatus);

        $this->render('applications/review', [
            'title'        => 'Application Review',
            'application'  => $application,
            'timeline'     => $timeline,
            'allowedNext'  => $allowedNext,
            'statusLabels' => ApplicationModel::STATUS_LABELS,
            'statusColors' => ApplicationModel::STATUS_COLORS,
            'csrf'         => SecurityHelper::generateCsrf(),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // ADMIN — All applications for a job
    // ──────────────────────────────────────────────────────────

    /**
     * GET /admin/jobs/{jobId}/applications
     */
    public function jobApplications(int $jobId): void
    {
        Auth::requireRole(['admin', 'super_admin', 'employer']);

        $role = Auth::role();
        $job  = $this->jobModel->getById($jobId);

        if (!$job) {
            $this->abort(404, 'Job not found.');
            return;
        }

        if ($role === 'employer') {
            $this->assertEmployerOwnsJob($jobId);
        }

        $statusFilter = $_GET['status'] ?? '';
        $applications = $this->appModel->getByJob($jobId, $statusFilter);

        $this->render('applications/job-applications', [
            'title'        => "Applications — {$job['title']}",
            'job'          => $job,
            'applications' => $applications,
            'statusFilter' => $statusFilter,
            'statusLabels' => ApplicationModel::STATUS_LABELS,
            'statusColors' => ApplicationModel::STATUS_COLORS,
            'statuses'     => ApplicationModel::STATUSES,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // Private — Resume upload
    // ──────────────────────────────────────────────────────────

    /**
     * Validate, move, and return the relative path of an uploaded resume.
     * Returns null if no file was uploaded.
     * Throws RuntimeException on validation failure.
     */
    private function handleResumeUpload(): ?string
    {
        if (empty($_FILES['resume']['name']) || $_FILES['resume']['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $file = $_FILES['resume'];

        // Upload error check
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed. Please try again.');
        }

        // Size check
        if ($file['size'] > self::MAX_UPLOAD_BYTES) {
            throw new \RuntimeException('Resume file must not exceed 5 MB.');
        }

        // MIME validation via finfo (not relying on client-supplied type)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \RuntimeException('Only PDF, DOC, and DOCX resumes are accepted.');
        }

        // Extension validation
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTS, true)) {
            throw new \RuntimeException('Invalid file extension. Allowed: PDF, DOC, DOCX.');
        }

        // Prepare upload directory
        $uploadDir = BASE_PATH . '/uploads/resumes/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            throw new \RuntimeException('Server upload directory could not be created.');
        }

        // Secure filename — no original name preserved
        $filename = 'resume_' . bin2hex(random_bytes(12)) . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException('Failed to save uploaded file. Please try again.');
        }

        return 'resumes/' . $filename;
    }

    // ──────────────────────────────────────────────────────────
    // Private — Matching queue trigger
    // ──────────────────────────────────────────────────────────

    private function dispatchMatchingQueue(int $appId, int $applicantId, int $jobId): void
    {
        try {
            if (class_exists('App\Services\MatchingService')) {
                \App\Services\MatchingService::dispatch($applicantId, $jobId, $appId);
            }
        } catch (\Throwable $e) {
            // Non-fatal: log but do not surface to user
            error_log("[MatchingQueue] Failed for app #{$appId}: " . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────
    // Private — Employment record on hire
    // ──────────────────────────────────────────────────────────

    private function recordEmployment(array $application): void
    {
        try {
            $stmt = $this->getDB()->prepare("
                INSERT IGNORE INTO employment_records
                    (applicant_id, job_id, employer_id, hired_at)
                SELECT
                    :applicant_id,
                    j.id,
                    j.employer_id,
                    NOW()
                FROM jobs j
                WHERE j.id = :job_id
            ");
            $stmt->execute([
                ':applicant_id' => $application['applicant_id'],
                ':job_id'       => $application['job_id'],
            ]);
        } catch (\Throwable $e) {
            error_log("[EmploymentRecord] Failed for app #{$application['id']}: " . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────
    // Private — Notification dispatch
    // ──────────────────────────────────────────────────────────

    private function dispatchNotification(string $type, array $payload): void
    {
        try {
            if (class_exists('App\Services\NotificationService')) {
                \App\Services\NotificationService::dispatch($type, $payload);
            }
        } catch (\Throwable $e) {
            error_log("[Notification] Failed ({$type}): " . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────
    // Private — Authorisation helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Returns allowed next statuses for a given role + current status.
     */
    private function getAllowedTransitions(string $role, string $currentStatus): array
    {
        if (in_array($role, ['admin', 'super_admin'], true)) {
            return self::ADMIN_TRANSITIONS[$currentStatus] ?? [];
        }

        if ($role === 'employer') {
            return self::EMPLOYER_TRANSITIONS[$currentStatus] ?? [];
        }

        return [];
    }

    /**
     * Asserts the current employer user owns the given job_id.
     * Aborts with 403 on failure.
     */
    private function assertEmployerOwnsJob(int $jobId): void
    {
        $userId = Auth::id();
        $stmt   = $this->getDB()->prepare("
            SELECT 1 FROM jobs j
            JOIN employers e ON j.employer_id = e.id
            WHERE j.id = :job_id AND e.user_id = :user_id
        ");
        $stmt->execute([':job_id' => $jobId, ':user_id' => $userId]);

        if (!$stmt->fetchColumn()) {
            $this->abort(403, 'You do not have permission to manage this application.');
        }
    }

    /**
     * Asserts the current user is allowed to view the given application.
     */
    private function assertCanViewApplication(array $application): void
    {
        $role = Auth::role();

        if (in_array($role, ['admin', 'super_admin'], true)) {
            return;
        }

        if ($role === 'applicant') {
            $applicantId = $this->resolveApplicantId();
            if ((int)$application['applicant_id'] !== $applicantId) {
                $this->abort(403, 'Access denied.');
            }
            return;
        }

        if ($role === 'employer') {
            $this->assertEmployerOwnsJob((int)$application['job_id']);
            return;
        }

        $this->abort(403, 'Access denied.');
    }

    /**
     * Resolve applicant_id from the session user, abort 403 if not found.
     */
    private function resolveApplicantId(): int
    {
        $userId = Auth::id();
        $stmt   = $this->getDB()->prepare(
            "SELECT id FROM applicants WHERE user_id = :uid LIMIT 1"
        );
        $stmt->execute([':uid' => $userId]);
        $id = (int)$stmt->fetchColumn();

        if (!$id) {
            $this->abort(403, 'Applicant profile not found. Please complete your registration.');
        }

        return $id;
    }

    // ──────────────────────────────────────────────────────────
    // Private — Response helpers
    // ──────────────────────────────────────────────────────────

    private function jsonError(string $message, int $status = 400): void
    {
        $this->json(['success' => false, 'message' => $message], $status);
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->abort(405, 'Method not allowed.');
        }
    }

    private function auditLog(string $action, string $description, int $actorId = 0): void
    {
        if (class_exists('App\Services\AuditService')) {
            \App\Services\AuditService::log($action, $description, $actorId ?: Auth::id());
        }
    }
}
