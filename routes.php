<?php
/**
 * PESO Balayan – Route Definitions
 * File: config/routes.php
 *
 * $router is injected by App::run().
 * Format: $router->get('/path', 'ControllerClass@method');
 *         $router->post('/path', 'ControllerClass@method');
 */

// ── Public / Auth ──────────────────────────────────────────────
$router->get('/',              'AuthController@showLogin');
$router->get('/login',         'AuthController@showLogin');
$router->post('/login',        'AuthController@login');
$router->get('/logout',        'AuthController@logout');
$router->get('/register',      'AuthController@showRegister');
$router->post('/register',     'AuthController@register');
$router->get('/forgot-password',  'AuthController@showForgotPassword');
$router->post('/forgot-password', 'AuthController@sendResetLink');
$router->get('/reset-password/:token', 'AuthController@showResetPassword');
$router->post('/reset-password',       'AuthController@resetPassword');

// ── Applicant ──────────────────────────────────────────────────
$router->get('/applicant/dashboard',      'ApplicantController@dashboard');
$router->get('/applicant/jobs',           'ApplicantController@jobs');
$router->get('/applicant/job/:id',        'ApplicantController@viewJob');
$router->post('/applicant/job/:id/apply', 'ApplicantController@applyJob');
$router->get('/applicant/applications',   'ApplicantController@myApplications');
$router->get('/applicant/profile',        'ApplicantController@profile');
$router->post('/applicant/profile',       'ApplicantController@updateProfile');
$router->get('/applicant/settings',       'ApplicantController@settings');
$router->post('/applicant/settings',      'ApplicantController@updateSettings');

// ── Employer ───────────────────────────────────────────────────
$router->get('/employer/dashboard',               'EmployerController@dashboard');
$router->get('/employer/jobs',                    'EmployerController@jobs');
$router->get('/employer/jobs/create',             'EmployerController@createJob');
$router->post('/employer/jobs/create',            'EmployerController@storeJob');
$router->get('/employer/jobs/:id/edit',           'EmployerController@editJob');
$router->post('/employer/jobs/:id/edit',          'EmployerController@updateJob');
$router->post('/employer/jobs/:id/delete',        'EmployerController@deleteJob');
$router->get('/employer/referred-applicants',     'EmployerController@referredApplicants');
$router->get('/employer/applicant/:id',           'EmployerController@viewApplicant');
$router->post('/employer/applicant/:id/decision', 'EmployerController@makeDecision');
$router->get('/employer/notifications',           'EmployerController@notifications');
$router->get('/employer/settings',                'EmployerController@settings');

// ── Admin ──────────────────────────────────────────────────────
$router->get('/admin/dashboard',                   'AdminController@dashboard');
$router->get('/admin/applicants',                  'AdminController@applicants');
$router->get('/admin/applicants/create',           'AdminController@createApplicant');
$router->post('/admin/applicants/create',          'AdminController@storeApplicant');
$router->get('/admin/applicants/:id',              'AdminController@viewApplicant');
$router->get('/admin/jobs',                        'AdminController@jobs');
$router->get('/admin/jobs/:id',                    'AdminController@viewJob');
$router->get('/admin/matching',                    'AdminController@matching');
$router->post('/admin/matching/trigger',           'AdminController@triggerMatching');
$router->post('/admin/matching/:id/refer',         'AdminController@referApplicant');
$router->get('/admin/employers',                   'AdminController@employers');
$router->post('/admin/employers/:id/verify',       'AdminController@verifyEmployer');
$router->get('/admin/reports',                     'AdminController@reports');
$router->get('/admin/settings',                    'AdminController@settings');

// ── Super Admin ────────────────────────────────────────────────
$router->get('/superadmin/dashboard',              'SuperAdminController@dashboard');
$router->get('/superadmin/applicants',             'SuperAdminController@applicants');
$router->get('/superadmin/jobs',                   'SuperAdminController@jobs');
$router->get('/superadmin/matching',               'SuperAdminController@matching');
$router->get('/superadmin/employers',              'SuperAdminController@employers');
$router->post('/superadmin/employers/:id/approve', 'SuperAdminController@approveEmployer');
$router->post('/superadmin/employers/:id/reject',  'SuperAdminController@rejectEmployer');
$router->get('/superadmin/reports',                'SuperAdminController@reports');
$router->get('/superadmin/audit-logs',             'SuperAdminController@auditLogs');
$router->get('/superadmin/users',                  'SuperAdminController@manageUsers');
$router->post('/superadmin/users/create',          'SuperAdminController@createUser');
$router->post('/superadmin/users/:id/delete',      'SuperAdminController@deleteUser');
$router->get('/superadmin/settings',               'SuperAdminController@settings');

// ── API / AJAX ─────────────────────────────────────────────────
$router->post('/api/csrf-token',         'ApiController@getCsrfToken');
$router->get('/api/jobs/search',         'ApiController@searchJobs');
$router->get('/api/notifications/count', 'ApiController@notificationCount');
$router->post('/api/notifications/read', 'ApiController@markNotificationsRead');
