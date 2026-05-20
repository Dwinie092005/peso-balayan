<?php

namespace App\Controllers\Employer;

use App\Core\Controller;
use App\Models\EmployerModel;
use App\Models\JobModel;
use App\Models\ApplicationModel;
use App\Models\NotificationModel;
use App\Services\AuditService;

class EmployerDashboardController extends Controller
{
    private EmployerModel $employerModel;
    private JobModel $jobModel;
    private ApplicationModel $applicationModel;
    private NotificationModel $notificationModel;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->requireRole('employer');

        $this->employerModel     = new EmployerModel();
        $this->jobModel          = new JobModel();
        $this->applicationModel  = new ApplicationModel();
        $this->notificationModel = new NotificationModel();
    }

    /**
     * Render the employer dashboard with live stats.
     */
    public function index(): void
    {
        $userId   = $this->session->get('user_id');
        $employer = $this->employerModel->findByUserId($userId);

        if (!$employer) {
            $this->redirect('/employer/profile/setup');
            return;
        }

        $employerId = $employer['id'];

        $stats = [
            'total_jobs'       => $this->jobModel->countByEmployer($employerId),
            'active_jobs'      => $this->jobModel->countByStatus($employerId, 'active'),
            'total_applicants' => $this->applicationModel->countByEmployer($employerId),
            'referred'         => $this->applicationModel->countReferredByEmployer($employerId),
        ];

        $recentJobs         = $this->jobModel->getRecentByEmployer($employerId, 5);
        $recentApplicants   = $this->applicationModel->getRecentByEmployer($employerId, 5);
        $notifications      = $this->notificationModel->getUnreadByUser($userId, 5);

        AuditService::log($userId, 'VIEW', 'employer_dashboard', null, 'Employer viewed dashboard');

        $this->view('employer/dashboard', [
            'pageTitle'        => 'Employer Dashboard',
            'employer'         => $employer,
            'stats'            => $stats,
            'recentJobs'       => $recentJobs,
            'recentApplicants' => $recentApplicants,
            'notifications'    => $notifications,
            'layout'           => 'main',
        ]);
    }
}
