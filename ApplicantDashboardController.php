<?php

namespace App\Controllers\Applicant;

use App\Core\Controller;
use App\Models\ApplicantModel;
use App\Models\ApplicationModel;
use App\Models\JobModel;
use App\Models\NotificationModel;
use App\Services\AuditService;

class ApplicantDashboardController extends Controller
{
    private ApplicantModel $applicantModel;
    private ApplicationModel $applicationModel;
    private JobModel $jobModel;
    private NotificationModel $notificationModel;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->requireRole('applicant');

        $this->applicantModel    = new ApplicantModel();
        $this->applicationModel  = new ApplicationModel();
        $this->jobModel          = new JobModel();
        $this->notificationModel = new NotificationModel();
    }

    /**
     * Render the applicant dashboard with live stats.
     */
    public function index(): void
    {
        $userId     = $this->session->get('user_id');
        $applicant  = $this->applicantModel->findByUserId($userId);

        if (!$applicant) {
            $this->redirect('/applicant/profile/setup');
            return;
        }

        $applicantId = $applicant['id'];

        $stats = [
            'total_applications'  => $this->applicationModel->countByApplicant($applicantId),
            'pending_applications'=> $this->applicationModel->countByStatus($applicantId, 'pending'),
            'matched_jobs'        => $this->applicationModel->countByStatus($applicantId, 'matched'),
            'referred_jobs'       => $this->applicationModel->countByStatus($applicantId, 'referred'),
        ];

        $recentApplications = $this->applicationModel->getRecentByApplicant($applicantId, 5);
        $recentJobs         = $this->jobModel->getLatestActive(5);
        $notifications      = $this->notificationModel->getUnreadByUser($userId, 5);

        AuditService::log($userId, 'VIEW', 'applicant_dashboard', null, 'Applicant viewed dashboard');

        $this->view('applicant/dashboard', [
            'pageTitle'          => 'My Dashboard',
            'applicant'          => $applicant,
            'stats'              => $stats,
            'recentApplications' => $recentApplications,
            'recentJobs'         => $recentJobs,
            'notifications'      => $notifications,
            'layout'             => 'main',
        ]);
    }
}
