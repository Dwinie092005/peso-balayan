<?php
/**
 * PESO Balayan IMIS — Job Controller
 * File: app/controllers/jobs/JobController.php
 *
 * Handles: job listing, detail, CRUD (employer/admin), bookmarking (applicant)
 * Depends: JobModel, ApplicationModel, Auth, SecurityHelper
 */

namespace App\Controllers\Jobs;

use App\Core\BaseController;
use App\Core\Auth;
use App\Core\SecurityHelper;
use App\Models\JobModel;
use App\Models\ApplicationModel;

class JobController extends BaseController
{
    private JobModel         $jobModel;
    private ApplicationModel $appModel;

    private const PER_PAGE    = 12;
    private const AJAX_HEADER = 'HTTP_X_REQUESTED_WITH';

    public function __construct()
    {
        parent::__construct();
        $db             = $this->getDB();
        $this->jobModel = new JobModel($db);
        $this->appModel = new ApplicationModel($db);
    }

    // ──────────────────────────────────────────────────────────
    // PUBLIC — Job listing
    // ──────────────────────────────────────────────────────────

    /**
     * GET /jobs  |  GET /employer/jobs  |  GET /admin/jobs
     *
     * Renders full page or returns JSON card HTML (AJAX filter).
     */
    public function index(): void
    {
        Auth::requireAuth();

        $role      = Auth::role();
        $filters   = $this->sanitiseFilters($_GET);
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $isAjax    = $this->isAjax();

        // Employer sees only their own listings; admin sees everything
        if ($role === 'employer') {
            $filters['employer_id'] = $this->resolveEmployerId();
            $filters['admin']       = true; // bypass active-only gate
        } elseif (in_array($role, ['admin', 'super_admin'], true)) {
            $filters['admin'] = true;
        }

        $jobs       = $this->jobModel->getAll($filters, $page, self::PER_PAGE);
        $total      = $this->jobModel->count($filters);
        $categories = $this->jobModel->getCategories();
        $cities     = $this->jobModel->getActiveCities();
        $totalPages = (int)ceil($total / self::PER_PAGE);

        // Attach saved status for applicants
        if ($role === 'applicant') {
            $applicantId = $this->resolveApplicantId();
            foreach ($jobs as &$job) {
                $job['is_saved']    = $this->jobModel->isSaved($applicantId, (int)$job['id']);
                $job['has_applied'] = $this->appModel->hasApplied($applicantId, (int)$job['id']);
            }
            unset($job);
        }

        if ($isAjax) {
            $this->json([
                'success'     => true,
                'jobs'        => $jobs,
                'total'       => $total,
                'page'        => $page,
                'total_pages' => $totalPages,
            ]);
            return;
        }

        $view = match($role) {
            'employer'   => 'jobs/employer-index',
            'admin',
            'super_admin'=> 'jobs/admin-index',
            default      => 'jobs/index',
        };

        $this->render($view, [
            'title'       => 'Job Vacancies',
            'jobs'        => $jobs,
            'filters'     => $filters,
            'categories'  => $categories,
            'cities'      => $cities,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => self::PER_PAGE,
            'totalPages'  => $totalPages,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // PUBLIC — Job detail
    // ──────────────────────────────────────────────────────────

    /**
     * GET /jobs/{id}
     */
    public function show(int $id): void
    {
        Auth::requireAuth();

        $job = $this->jobModel->getById($id);
        if (!$job) {
            $this->abort(404, 'Job vacancy not found.');
            return;
        }

        // Non-admin/employer cannot view draft/closed/expired jobs
        $role = Auth::role();
        if (!in_array($role, ['admin', 'super_admin', 'employer'], true)
            && $job['status'] !== 'active') {
            $this->abort(404, 'This job vacancy is no longer available.');
            return;
        }

        // Track views (non-admin, one increment per session per job)
        $viewKey = 'viewed_job_' . $id;
        if (empty($_SESSION[$viewKey])) {
            $this->jobModel->incrementViews($id);
            $_SESSION[$viewKey] = true;
        }

        $skills    = $this->jobModel->getSkills($id);
        $isSaved   = false;
        $hasApplied = false;

        if ($role === 'applicant') {
            $applicantId = $this->resolveApplicantId();
            $isSaved     = $this->jobModel->isSaved($applicantId, $id);
            $hasApplied  = $this->appModel->hasApplied($applicantId, $id);
        }

        $this->render('jobs/show', [
            'title'      => htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8') . ' — Job Detail',
            'job'        => $job,
            'skills'     => $skills,
            'isSaved'    => $isSaved,
            'hasApplied' => $hasApplied,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // EMPLOYER / ADMIN — Create vacancy
    // ──────────────────────────────────────────────────────────

    /**
     * GET /employer/jobs/create  |  GET /admin/jobs/create
     */
    public function create(): void
    {
        Auth::requireRole(['employer', 'admin', 'super_admin']);

        $this->render('jobs/create', [
            'title'      => 'Post New Vacancy',
            'categories' => $this->jobModel->getCategories(),
            'types'      => JobModel::EMPLOYMENT_TYPES,
            'typeLabels' => JobModel::TYPE_LABELS,
            'eduLevels'  => JobModel::EDUCATION_LABELS,
            'statuses'   => JobModel::STATUSES,
            'csrf'       => SecurityHelper::generateCsrf(),
        ]);
    }

    /**
     * POST /employer/jobs/store  |  POST /admin/jobs/store
     */
    public function store(): void
    {
        Auth::requireRole(['employer', 'admin', 'super_admin']);
        $this->requirePost();
        SecurityHelper::verifyCsrf($_POST['_csrf_token'] ?? '');

        $role = Auth::role();
        $data = $this->extractJobData($_POST);

        // Employer can only post for themselves
        $data['employer_id'] = ($role === 'employer')
            ? $this->resolveEmployerId()
            : (int)($_POST['employer_id'] ?? 0);

        if (!$data['employer_id']) {
            $this->flashError('Employer not found.');
            $this->redirect('/employer/jobs/create');
            return;
        }

        $errors = $this->validateJobData($data);
        if ($errors) {
            $this->flashError(implode(' ', $errors));
            $this->redirect($role === 'employer' ? '/employer/jobs/create' : '/admin/jobs/create');
            return;
        }

        // Employer-posted jobs start as draft awaiting admin activation
        if ($role === 'employer') {
            $data['status'] = 'draft';
        }

        $jobId = $this->jobModel->create($data);

        // Sync required skills
        if (!empty($_POST['skill_ids']) && is_array($_POST['skill_ids'])) {
            $skillIds = array_map('intval', $_POST['skill_ids']);
            $this->jobModel->syncSkills($jobId, $skillIds);
        }

        $this->auditLog('job_created', "Job #{$jobId}: {$data['title']}");
        $this->flashSuccess('Job vacancy posted successfully.');
        $this->redirect($role === 'employer' ? '/employer/jobs' : '/admin/jobs');
    }

    // ──────────────────────────────────────────────────────────
    // EMPLOYER / ADMIN — Edit vacancy
    // ──────────────────────────────────────────────────────────

    /**
     * GET /employer/jobs/{id}/edit  |  GET /admin/jobs/{id}/edit
     */
    public function edit(int $id): void
    {
        Auth::requireRole(['employer', 'admin', 'super_admin']);

        $job = $this->resolveOwnedJob($id);

        $this->render('jobs/edit', [
            'title'      => 'Edit Vacancy',
            'job'        => $job,
            'skills'     => $this->jobModel->getSkills($id),
            'categories' => $this->jobModel->getCategories(),
            'types'      => JobModel::EMPLOYMENT_TYPES,
            'typeLabels' => JobModel::TYPE_LABELS,
            'eduLevels'  => JobModel::EDUCATION_LABELS,
            'statuses'   => JobModel::STATUSES,
            'csrf'       => SecurityHelper::generateCsrf(),
        ]);
    }

    /**
     * POST /employer/jobs/{id}/update  |  POST /admin/jobs/{id}/update
     */
    public function update(int $id): void
    {
        Auth::requireRole(['employer', 'admin', 'super_admin']);
        $this->requirePost();
        SecurityHelper::verifyCsrf($_POST['_csrf_token'] ?? '');

        $this->resolveOwnedJob($id); // ownership / existence check

        $data   = $this->extractJobData($_POST);
        $errors = $this->validateJobData($data);

        if ($errors) {
            $this->flashError(implode(' ', $errors));
            $this->redirect('/employer/jobs/' . $id . '/edit');
            return;
        }

        $this->jobModel->update($id, $data);

        // Re-sync skills
        $skillIds = isset($_POST['skill_ids']) && is_array($_POST['skill_ids'])
            ? array_map('intval', $_POST['skill_ids'])
            : [];
        $this->jobModel->syncSkills($id, $skillIds);

        $this->auditLog('job_updated', "Job #{$id}: {$data['title']}");
        $this->flashSuccess('Job vacancy updated.');

        $role = Auth::role();
        $this->redirect($role === 'employer' ? '/employer/jobs' : '/admin/jobs');
    }

    // ──────────────────────────────────────────────────────────
    // EMPLOYER / ADMIN — Delete vacancy
    // ──────────────────────────────────────────────────────────

    /**
     * POST /employer/jobs/{id}/delete  |  AJAX-safe
     */
    public function delete(int $id): void
    {
        Auth::requireRole(['employer', 'admin', 'super_admin']);
        $this->requirePost();
        SecurityHelper::verifyCsrf($_POST['_csrf_token'] ?? '');

        $job = $this->resolveOwnedJob($id);

        $this->jobModel->delete($id);
        $this->auditLog('job_deleted', "Job #{$id}: {$job['title']}");

        if ($this->isAjax()) {
            $this->json(['success' => true, 'message' => 'Vacancy deleted.']);
            return;
        }

        $this->flashSuccess('Job vacancy deleted.');
        $role = Auth::role();
        $this->redirect($role === 'employer' ? '/employer/jobs' : '/admin/jobs');
    }

    // ──────────────────────────────────────────────────────────
    // ADMIN — Toggle job status
    // ──────────────────────────────────────────────────────────

    /**
     * POST /admin/jobs/{id}/status  |  AJAX
     */
    public function toggleStatus(int $id): void
    {
        Auth::requireRole(['admin', 'super_admin']);
        $this->requirePost();
        SecurityHelper::verifyCsrf($_POST['_csrf_token'] ?? '');

        $newStatus = trim($_POST['status'] ?? '');
        if (!in_array($newStatus, JobModel::STATUSES, true)) {
            $this->json(['success' => false, 'message' => 'Invalid status.'], 422);
            return;
        }

        $this->jobModel->toggleStatus($id, $newStatus);
        $this->auditLog('job_status_changed', "Job #{$id} set to {$newStatus}.");

        if ($this->isAjax()) {
            $this->json(['success' => true, 'status' => $newStatus]);
            return;
        }

        $this->flashSuccess('Job status updated.');
        $this->redirect('/admin/jobs');
    }

    // ──────────────────────────────────────────────────────────
    // APPLICANT — Bookmark / un-bookmark
    // ──────────────────────────────────────────────────────────

    /**
     * POST /jobs/{id}/bookmark  |  AJAX only
     */
    public function bookmark(int $id): void
    {
        Auth::requireRole(['applicant']);
        $this->requirePost();
        SecurityHelper::verifyCsrf($_POST['_csrf_token'] ?? '');

        $job = $this->jobModel->getById($id);
        if (!$job) {
            $this->json(['success' => false, 'message' => 'Job not found.'], 404);
            return;
        }

        $applicantId = $this->resolveApplicantId();
        $isSaved     = $this->jobModel->toggleSave($applicantId, $id);

        $this->json([
            'success' => true,
            'saved'   => $isSaved,
            'message' => $isSaved ? 'Job bookmarked.' : 'Bookmark removed.',
        ]);
    }

    /**
     * GET /applicant/saved-jobs
     */
    public function savedJobs(): void
    {
        Auth::requireRole(['applicant']);

        $applicantId = $this->resolveApplicantId();
        $jobs        = $this->jobModel->getSavedByApplicant($applicantId);

        $this->render('jobs/saved', [
            'title' => 'Saved Jobs',
            'jobs'  => $jobs,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Load a job and assert the current user owns it (or is admin).
     * Aborts with 403 on failure.
     */
    private function resolveOwnedJob(int $id): array
    {
        $job  = $this->jobModel->getById($id);
        $role = Auth::role();

        if (!$job) {
            $this->abort(404, 'Job vacancy not found.');
        }

        if (in_array($role, ['admin', 'super_admin'], true)) {
            return $job;
        }

        // Employer ownership check
        $employerId = $this->resolveEmployerId();
        if ((int)$job['employer_id'] !== $employerId) {
            $this->abort(403, 'You do not have permission to manage this vacancy.');
        }

        return $job;
    }

    /**
     * Get employer_id from session-linked employers row.
     */
    private function resolveEmployerId(): int
    {
        $userId = Auth::id();
        $stmt   = $this->getDB()->prepare(
            "SELECT id FROM employers WHERE user_id = :uid LIMIT 1"
        );
        $stmt->execute([':uid' => $userId]);
        $id = (int)$stmt->fetchColumn();

        if (!$id) {
            $this->abort(403, 'Employer profile not found.');
        }

        return $id;
    }

    /**
     * Get applicant_id from session-linked applicants row.
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
            $this->abort(403, 'Applicant profile not found.');
        }

        return $id;
    }

    /**
     * Extract and sanitise job fields from POST.
     */
    private function extractJobData(array $post): array
    {
        return [
            'category_id'        => !empty($post['category_id'])       ? (int)$post['category_id']       : null,
            'title'              => trim($post['title']                 ?? ''),
            'description'        => trim($post['description']          ?? ''),
            'requirements'       => trim($post['requirements']         ?? '') ?: null,
            'benefits'           => trim($post['benefits']             ?? '') ?: null,
            'employment_type'    => in_array($post['employment_type']  ?? '', JobModel::EMPLOYMENT_TYPES, true)
                                    ? $post['employment_type'] : 'full_time',
            'salary_min'         => is_numeric($post['salary_min']     ?? '') ? (float)$post['salary_min']     : null,
            'salary_max'         => is_numeric($post['salary_max']     ?? '') ? (float)$post['salary_max']     : null,
            'salary_negotiable'  => !empty($post['salary_negotiable']) ? 1 : 0,
            'location_city'      => trim($post['location_city']        ?? '') ?: null,
            'location_province'  => trim($post['location_province']    ?? '') ?: null,
            'location_address'   => trim($post['location_address']     ?? '') ?: null,
            'slots'              => max(1, (int)($post['slots']        ?? 1)),
            'education_required' => array_key_exists($post['education_required'] ?? '', JobModel::EDUCATION_LABELS)
                                    ? $post['education_required'] : 'none',
            'experience_years'   => max(0, (int)($post['experience_years'] ?? 0)),
            'status'             => in_array($post['status'] ?? '', JobModel::STATUSES, true)
                                    ? $post['status'] : 'draft',
            'expires_at'         => !empty($post['expires_at'])
                                    ? date('Y-m-d', strtotime($post['expires_at'])) : null,
        ];
    }

    /**
     * Validate job payload; returns array of error strings (empty = valid).
     */
    private function validateJobData(array $data): array
    {
        $errors = [];

        if (empty($data['title'])) {
            $errors[] = 'Job title is required.';
        } elseif (mb_strlen($data['title']) > 200) {
            $errors[] = 'Job title must not exceed 200 characters.';
        }

        if (empty($data['description'])) {
            $errors[] = 'Job description is required.';
        }

        if ($data['salary_min'] !== null && $data['salary_max'] !== null
            && $data['salary_min'] > $data['salary_max']) {
            $errors[] = 'Minimum salary cannot exceed maximum salary.';
        }

        if ($data['expires_at'] !== null && $data['expires_at'] < date('Y-m-d')) {
            $errors[] = 'Expiry date cannot be in the past.';
        }

        return $errors;
    }

    /**
     * Sanitise filter values from GET params.
     */
    private function sanitiseFilters(array $get): array
    {
        return [
            'search'           => trim($get['search']           ?? ''),
            'category_id'      => !empty($get['category_id'])      ? (int)$get['category_id']      : null,
            'employment_type'  => in_array($get['employment_type'] ?? '', JobModel::EMPLOYMENT_TYPES, true)
                                   ? $get['employment_type'] : null,
            'location_city'    => trim($get['location_city']    ?? '') ?: null,
            'education_required' => array_key_exists($get['education_required'] ?? '', JobModel::EDUCATION_LABELS)
                                   ? $get['education_required'] : null,
            'salary_min'       => is_numeric($get['salary_min'] ?? '') ? (float)$get['salary_min'] : null,
            'status'           => in_array($get['status'] ?? '', JobModel::STATUSES, true)
                                   ? $get['status'] : null,
            'expires_after'    => date('Y-m-d'), // only show non-expired by default
        ];
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER[self::AJAX_HEADER])
            && strtolower($_SERVER[self::AJAX_HEADER]) === 'xmlhttprequest';
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->abort(405, 'Method not allowed.');
        }
    }

    private function flashSuccess(string $msg): void
    {
        $_SESSION['flash']['success'] = $msg;
    }

    private function flashError(string $msg): void
    {
        $_SESSION['flash']['danger'] = $msg;
    }

    private function auditLog(string $action, string $description): void
    {
        if (class_exists('App\Services\AuditService')) {
            \App\Services\AuditService::log($action, $description, Auth::id());
        }
    }
}
