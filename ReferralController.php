<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\ReferralModel;
use App\Models\ApplicantModel;
use App\Models\EmployerModel;
use App\Models\JobModel;
use App\Models\NotificationModel;
use App\Middleware\RoleMiddleware;
use App\Helpers\FlashHelper;
use App\Services\AuditService;

/**
 * ReferralController (Admin)
 *
 * Manages the admin-initiated referral workflow:
 * - View and filter all referrals
 * - Create new referrals (applicant → employer/job)
 * - Update referral status
 * - Add notes to referrals
 * - View referral history timeline
 */
class ReferralController extends Controller
{
    private ReferralModel     $referralModel;
    private ApplicantModel    $applicantModel;
    private EmployerModel     $employerModel;
    private JobModel          $jobModel;
    private NotificationModel $notificationModel;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        RoleMiddleware::require(['admin', 'super_admin']);

        $this->referralModel     = new ReferralModel();
        $this->applicantModel    = new ApplicantModel();
        $this->employerModel     = new EmployerModel();
        $this->jobModel          = new JobModel();
        $this->notificationModel = new NotificationModel();
    }

    /**
     * GET /admin/referrals
     * List all referrals with filters.
     */
    public function index(): void
    {
        $filters = [
            'status'      => $_GET['status']      ?? '',
            'employer_id' => (int)($_GET['employer_id'] ?? 0) ?: null,
            'date_from'   => $_GET['date_from']   ?? '',
            'date_to'     => $_GET['date_to']     ?? '',
        ];

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $referrals = $this->referralModel->getAll(array_filter($filters), $perPage, $offset);
        $total     = $this->referralModel->countAll(array_filter($filters));
        $employers = $this->employerModel->getAllActive();

        $this->view('admin/referrals', [
            'pageTitle'  => 'Referral Management',
            'referrals'  => $referrals,
            'employers'  => $employers,
            'filters'    => $filters,
            'pagination' => [
                'current'  => $page,
                'total'    => (int)ceil($total / $perPage),
                'per_page' => $perPage,
                'count'    => $total,
            ],
            'csrfToken'  => $this->getCsrfToken(),
            'layout'     => 'main',
        ]);
    }

    /**
     * GET /admin/referrals/{id}
     * View a single referral with full history timeline.
     */
    public function show(int $referralId): void
    {
        $referral = $this->referralModel->findById($referralId);

        if (!$referral) {
            FlashHelper::error('Referral not found.');
            $this->redirect('/admin/referrals');
            return;
        }

        $history = $this->referralModel->getHistory($referralId);

        $this->view('admin/referral-show', [
            'pageTitle' => 'Referral Detail',
            'referral'  => $referral,
            'history'   => $history,
            'csrfToken' => $this->getCsrfToken(),
            'layout'    => 'main',
        ]);
    }

    /**
     * POST /admin/referrals/create
     * Create a new referral (admin refers applicant to employer).
     */
    public function create(): void
    {
        $this->validateCsrf();

        $applicantId = (int)($_POST['applicant_id'] ?? 0);
        $employerId  = (int)($_POST['employer_id']  ?? 0);
        $jobId       = (int)($_POST['job_id']       ?? 0) ?: null;
        $notes       = trim($_POST['referral_notes'] ?? '');
        $adminUserId = (int)$this->session->get('user_id');

        // Validate required fields
        if (!$applicantId || !$employerId) {
            FlashHelper::error('Applicant and employer are required.');
            $this->redirect('/admin/referrals');
            return;
        }

        // Duplicate check
        if ($this->referralModel->isDuplicate($applicantId, $employerId, $jobId)) {
            FlashHelper::warning('This applicant has already been referred to this employer for the selected job.');
            $this->redirect('/admin/referrals');
            return;
        }

        $referralId = $this->referralModel->create([
            'applicant_id'   => $applicantId,
            'employer_id'    => $employerId,
            'job_id'         => $jobId,
            'referred_by'    => $adminUserId,
            'referral_notes' => $notes,
        ]);

        // Auto-transition to 'sent'
        $this->referralModel->transition($referralId, 'sent', $adminUserId, 'Referral created and sent');

        // Notify employer
        $employer = $this->employerModel->findById($employerId);
        if ($employer) {
            $this->notificationModel->create([
                'user_id' => $employer['user_id'] ?? null,
                'message' => 'PESO Balayan has referred a qualified applicant for your job opening.',
                'icon'    => 'fa-user-check',
            ]);
        }

        // Notify applicant
        $applicant = $this->applicantModel->findById($applicantId);
        if ($applicant) {
            $jobTitle = $jobId ? ($this->jobModel->findById($jobId)['title'] ?? 'a position') : 'a position';
            $this->notificationModel->create([
                'user_id' => $applicant['user_id'] ?? null,
                'message' => "You have been referred by PESO Balayan to an employer for: {$jobTitle}.",
                'icon'    => 'fa-paper-plane',
            ]);
        }

        AuditService::log($adminUserId, 'CREATE', 'referrals', $referralId, 'New referral created');

        FlashHelper::success('Referral sent successfully.');
        $this->redirect('/admin/referrals');
    }

    /**
     * POST /admin/referrals/{id}/status
     * Update referral status (AJAX).
     */
    public function updateStatus(int $referralId): void
    {
        $this->validateCsrf();

        $newStatus   = $_POST['status'] ?? '';
        $note        = trim($_POST['note'] ?? '');
        $adminUserId = (int)$this->session->get('user_id');

        $updated = $this->referralModel->transition($referralId, $newStatus, $adminUserId, $note);

        if (!$updated) {
            $this->json(['success' => false, 'message' => 'Invalid status transition.'], 422);
            return;
        }

        AuditService::log($adminUserId, 'UPDATE', 'referrals', $referralId, "Status updated to {$newStatus}");

        $this->json(['success' => true, 'status' => $newStatus]);
    }

    /**
     * POST /admin/referrals/{id}/notes
     * Update admin referral notes.
     */
    public function updateNotes(int $referralId): void
    {
        $this->validateCsrf();

        $notes = trim($_POST['referral_notes'] ?? '');

        $stmt = $this->db->prepare(
            'UPDATE referrals SET referral_notes = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$notes, $referralId]);

        $this->json(['success' => true]);
    }

    /**
     * GET /admin/referrals/{id}/history (AJAX)
     * Return the referral status history as JSON.
     */
    public function history(int $referralId): void
    {
        $history = $this->referralModel->getHistory($referralId);
        $this->json(['history' => $history]);
    }

    // ── PRIVATE HELPERS ──────────────────────────────────────────

    private function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
