<?php

namespace App\Controllers\Employer;

use App\Core\Controller;
use App\Models\EmploymentModel;
use App\Models\NotificationModel;
use App\Middleware\RoleMiddleware;
use App\Helpers\FlashHelper;
use App\Services\AuditService;

/**
 * HiringController
 *
 * Manages the employer-side hiring pipeline:
 * - View all applicants per job
 * - Review individual applicant profiles
 * - Shortlist / reject / hire applicants
 * - Schedule and manage interviews
 * - Record placements
 * - Add employer notes
 */
class HiringController extends Controller
{
    private EmploymentModel   $employmentModel;
    private NotificationModel $notificationModel;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        RoleMiddleware::require(['employer']);

        $this->employmentModel   = new EmploymentModel();
        $this->notificationModel = new NotificationModel();
    }

    /**
     * GET /employer/applicants
     * Full applicant pipeline list for the employer.
     */
    public function index(): void
    {
        $employerId = $this->getEmployerId();
        $status     = $_GET['status'] ?? '';
        $search     = trim($_GET['search'] ?? '');
        $page       = max(1, (int)($_GET['page'] ?? 1));
        $perPage    = 15;
        $offset     = ($page - 1) * $perPage;

        $applicants = $this->employmentModel->getEmployerPipeline(
            $employerId, $status, $search, $perPage, $offset
        );

        $total   = $this->employmentModel->countEmployerPipeline($employerId, $status, $search);
        $summary = $this->employmentModel->getPipelineSummary($employerId);

        $this->view('employer/applicants', [
            'pageTitle'  => 'Applicant Pipeline',
            'applicants' => $applicants,
            'summary'    => $summary,
            'statuses'   => \App\Models\EmploymentModel::STATUSES,
            'filters'    => compact('status', 'search'),
            'pagination' => [
                'current'  => $page,
                'total'    => (int)ceil($total / $perPage),
                'per_page' => $perPage,
                'count'    => $total,
            ],
            'layout'     => 'main',
        ]);
    }

    /**
     * GET /employer/applicants/{applicationId}
     * Single applicant detail view with full profile, history, and interviews.
     */
    public function show(int $applicationId): void
    {
        $employerId  = $this->getEmployerId();
        $application = $this->employmentModel->getApplicationDetail($applicationId, $employerId);

        if (!$application) {
            FlashHelper::error('Applicant not found or access denied.');
            $this->redirect('/employer/applicants');
            return;
        }

        $interviews = $this->employmentModel->getEmployerInterviews($employerId);
        $interviews = array_filter($interviews, fn($i) => (int)($i['application_id'] ?? 0) === $applicationId);

        AuditService::log(
            $this->session->get('user_id'),
            'VIEW',
            'applications',
            $applicationId,
            'Employer viewed applicant profile'
        );

        $this->view('employer/applicant-show', [
            'pageTitle'   => 'Applicant Profile',
            'application' => $application,
            'interviews'  => array_values($interviews),
            'statuses'    => \App\Models\EmploymentModel::STATUSES,
            'csrfToken'   => $this->getCsrfToken(),
            'layout'      => 'main',
        ]);
    }

    /**
     * POST /employer/applicants/{applicationId}/status
     * Update an applicant's pipeline status.
     */
    public function updateStatus(int $applicationId): void
    {
        $this->validateCsrf();

        $employerId = $this->getEmployerId();
        $newStatus  = $_POST['status'] ?? '';
        $note       = trim($_POST['note'] ?? '');

        // Ownership validation
        $application = $this->employmentModel->getApplicationDetail($applicationId, $employerId);

        if (!$application) {
            $this->jsonError('Application not found or access denied.', 403);
            return;
        }

        $updated = $this->employmentModel->updateApplicationStatus(
            $applicationId,
            $newStatus,
            (int)$this->session->get('user_id'),
            $note
        );

        if (!$updated) {
            $this->jsonError('Invalid status transition.');
            return;
        }

        // Notify applicant
        $this->notificationModel->create([
            'user_id' => $application['user_id'] ?? null,
            'message' => 'Your application for "' . ($application['job_title'] ?? 'a position') . '" has been updated to: ' . ucwords(str_replace('_', ' ', $newStatus)),
            'icon'    => 'fa-briefcase',
        ]);

        AuditService::log(
            $this->session->get('user_id'),
            'UPDATE',
            'applications',
            $applicationId,
            "Status changed to {$newStatus}"
        );

        $this->json(['success' => true, 'status' => $newStatus]);
    }

    /**
     * POST /employer/applicants/{applicationId}/notes
     * Save employer notes for an applicant.
     */
    public function saveNotes(int $applicationId): void
    {
        $this->validateCsrf();

        $employerId  = $this->getEmployerId();
        $application = $this->employmentModel->getApplicationDetail($applicationId, $employerId);

        if (!$application) {
            $this->jsonError('Access denied.', 403);
            return;
        }

        $notes = trim($_POST['notes'] ?? '');
        $this->employmentModel->updateNotes($applicationId, $notes);

        $this->json(['success' => true]);
    }

    /**
     * POST /employer/interviews/schedule
     * Schedule an interview for an applicant.
     */
    public function scheduleInterview(): void
    {
        $this->validateCsrf();

        $employerId = $this->getEmployerId();
        $userId     = (int)$this->session->get('user_id');

        $applicationId = (int)($_POST['application_id'] ?? 0);
        $application   = $this->employmentModel->getApplicationDetail($applicationId, $employerId);

        if (!$application) {
            FlashHelper::error('Access denied.');
            $this->redirect('/employer/applicants');
            return;
        }

        // Validate required fields
        $date  = $_POST['interview_date'] ?? '';
        $time  = $_POST['interview_time'] ?? '';
        $type  = $_POST['interview_type'] ?? 'in_person';

        if (empty($date) || empty($time)) {
            FlashHelper::error('Interview date and time are required.');
            $this->redirect('/employer/applicants/' . $applicationId);
            return;
        }

        $interviewId = $this->employmentModel->scheduleInterview([
            'application_id'   => $applicationId,
            'applicant_id'     => $application['applicant_id'],
            'employer_id'      => $employerId,
            'job_id'           => $application['job_id'],
            'scheduled_by'     => $userId,
            'interview_date'   => $date,
            'interview_time'   => $time,
            'duration_minutes' => (int)($_POST['duration_minutes'] ?? 60),
            'interview_type'   => $type,
            'location'         => trim($_POST['location'] ?? ''),
            'employer_notes'   => trim($_POST['employer_notes'] ?? ''),
        ]);

        // Update application status to interview_scheduled
        $this->employmentModel->updateApplicationStatus(
            $applicationId,
            'interview_scheduled',
            $userId,
            'Interview scheduled for ' . $date . ' at ' . $time
        );

        // Notify applicant
        $this->notificationModel->create([
            'user_id' => $application['user_id'] ?? null,
            'message' => 'An interview has been scheduled for your application at "' . ($application['job_title'] ?? '') . '".',
            'icon'    => 'fa-calendar-check',
        ]);

        AuditService::log($userId, 'CREATE', 'interviews', $interviewId, 'Interview scheduled');

        FlashHelper::success('Interview scheduled successfully.');
        $this->redirect('/employer/applicants/' . $applicationId);
    }

    /**
     * POST /employer/applicants/{applicationId}/hire
     * Record a confirmed hire and create an employment record.
     */
    public function hire(int $applicationId): void
    {
        $this->validateCsrf();

        $employerId  = $this->getEmployerId();
        $userId      = (int)$this->session->get('user_id');
        $application = $this->employmentModel->getApplicationDetail($applicationId, $employerId);

        if (!$application) {
            $this->jsonError('Access denied.', 403);
            return;
        }

        // Record placement
        $this->employmentModel->recordPlacement([
            'applicant_id'    => $application['applicant_id'],
            'employer_id'     => $employerId,
            'job_id'          => $application['job_id'],
            'application_id'  => $applicationId,
            'date_hired'      => $_POST['date_hired']      ?? date('Y-m-d'),
            'salary'          => $_POST['salary']          ?? null,
            'employment_type' => $_POST['employment_type'] ?? $application['employment_type'] ?? 'full_time',
        ]);

        // Update status to hired
        $this->employmentModel->updateApplicationStatus($applicationId, 'hired', $userId, 'Applicant hired');

        // Notify applicant
        $this->notificationModel->create([
            'user_id' => $application['user_id'] ?? null,
            'message' => 'Congratulations! You have been hired for "' . ($application['job_title'] ?? 'a position') . '".',
            'icon'    => 'fa-star',
        ]);

        AuditService::log($userId, 'HIRE', 'applications', $applicationId, 'Applicant marked as hired');

        $this->json(['success' => true]);
    }

    // ── PRIVATE HELPERS ──────────────────────────────────────────

    /**
     * Get the employer ID for the current session user.
     *
     * @return int
     */
    private function getEmployerId(): int
    {
        return (int)($this->session->get('employer_id') ?? 0);
    }

    /**
     * Return a JSON success/data response.
     */
    private function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Return a JSON error response.
     */
    private function jsonError(string $message, int $code = 422): void
    {
        $this->json(['success' => false, 'message' => $message], $code);
    }
}
