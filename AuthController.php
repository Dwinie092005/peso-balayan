<?php
/**
 * PESO Balayan – Auth Controller
 * File: app/controllers/AuthController.php
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Models\UserModel;
use App\Models\ApplicantModel;
use App\Helpers\SecurityHelper;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;

class AuthController extends Controller
{
    private UserModel      $userModel;
    private ApplicantModel $applicantModel;

    public function __construct()
    {
        $this->userModel      = new UserModel();
        $this->applicantModel = new ApplicantModel();
    }

    // ── GET /login ────────────────────────────────────────────

    public function showLogin(): void
    {
        AuthMiddleware::redirectIfAuthenticated();
        $this->render('auth/login', [
            'title'     => 'Sign In – ' . APP_NAME,
            'csrfField' => SecurityHelper::csrfField(),
        ], 'auth');
    }

    // ── POST /login ───────────────────────────────────────────

    public function login(): void
    {
        AuthMiddleware::redirectIfAuthenticated();

        if (!SecurityHelper::validateCsrf()) {
            $this->flash('error', 'Invalid CSRF token. Please try again.');
            $this->redirectTo('login');
            return;
        }

        $email    = SecurityHelper::sanitizeEmail($this->post('email')) ?: '';
        $password = $_POST['password'] ?? '';

        // Basic validation
        if (!$email || !$password) {
            $this->flash('error', 'Email and password are required.');
            $this->redirectTo('login');
            return;
        }

        if (!SecurityHelper::isValidEmail($email)) {
            $this->flash('error', 'Please enter a valid email address.');
            $this->redirectTo('login');
            return;
        }

        // Fetch user
        $user = $this->userModel->findByEmail($email);

        if (!$user || !SecurityHelper::verifyPassword($password, $user['password_hash'])) {
            AuditService::log(null, 'login_failed', 'auth', null, "Failed login attempt for: {$email}");
            $this->flash('error', 'Invalid email or password.');
            $this->redirectTo('login');
            return;
        }

        if (!$user['is_active']) {
            $this->flash('error', 'Your account has been deactivated. Please contact PESO Balayan.');
            $this->redirectTo('login');
            return;
        }

        // Successful login – store session
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'        => $user['id'],
            'email'     => $user['email'],
            'role'      => $user['role'],
            'is_active' => $user['is_active'],
        ];
        $_SESSION['_last_regen'] = time();

        // Remember me – 30 days cookie
        if (!empty($_POST['remember_me'])) {
            $token = SecurityHelper::generateToken();
            $this->userModel->update((int) $user['id'], ['remember_token' => $token]);
            setcookie('remember_token', $token, time() + (30 * 24 * 3600), '/', '', isset($_SERVER['HTTPS']), true);
        }

        // Update last login
        $this->userModel->update((int) $user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        AuditService::log($user['id'], 'login', 'auth', null, 'Successful login');

        // Redirect to correct dashboard
        $this->redirect(AuthMiddleware::dashboardUrl($user['role']));
    }

    // ── GET /logout ───────────────────────────────────────────

    public function logout(): void
    {
        $userId = $_SESSION['user']['id'] ?? null;

        // Clear remember-me cookie
        if (!empty($_COOKIE['remember_token'])) {
            if ($userId) {
                $this->userModel->update((int) $userId, ['remember_token' => null]);
            }
            setcookie('remember_token', '', time() - 3600, '/');
        }

        if ($userId) {
            AuditService::log($userId, 'logout', 'auth');
        }

        $_SESSION = [];
        session_destroy();

        $this->redirectTo('login');
    }

    // ── GET /register ─────────────────────────────────────────

    public function showRegister(): void
    {
        AuthMiddleware::redirectIfAuthenticated();
        $this->render('auth/register', [
            'title'     => 'Register – ' . APP_NAME,
            'csrfField' => SecurityHelper::csrfField(),
        ], 'auth');
    }

    // ── POST /register ────────────────────────────────────────

    public function register(): void
    {
        AuthMiddleware::redirectIfAuthenticated();

        if (!SecurityHelper::validateCsrf()) {
            $this->flash('error', 'Invalid CSRF token.');
            $this->redirectTo('register');
            return;
        }

        $email    = SecurityHelper::sanitizeEmail($this->post('email')) ?: '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';
        $role     = $this->post('role');

        // Only applicants and employers can self-register
        $allowedRoles = [ROLE_APPLICANT, ROLE_EMPLOYER];
        if (!in_array($role, $allowedRoles, true)) {
            $role = ROLE_APPLICANT;
        }

        // Validate
        $errors = [];
        if (!SecurityHelper::isValidEmail($email))     $errors[] = 'Invalid email address.';
        if (!SecurityHelper::isStrongPassword($password)) $errors[] = 'Password must be at least 8 characters with 1 uppercase and 1 number.';
        if ($password !== $confirm)                      $errors[] = 'Passwords do not match.';
        if ($this->userModel->emailExists($email))       $errors[] = 'Email is already registered.';

        if (!empty($errors)) {
            $_SESSION['register_errors'] = $errors;
            $_SESSION['register_old']    = ['email' => $email, 'role' => $role];
            $this->redirectTo('register');
            return;
        }

        // Create user
        $userId = $this->userModel->create([
            'email'         => $email,
            'password_hash' => SecurityHelper::hashPassword($password),
            'role'          => $role,
            'is_active'     => 1,
        ]);

        // If applicant, auto-create applicant record (profile to be completed)
        if ($role === ROLE_APPLICANT) {
            $code = $this->applicantModel->generateApplicantCode();
            $this->applicantModel->create([
                'user_id'        => $userId,
                'applicant_code' => $code,
                'first_name'     => '',
                'last_name'      => '',
                'gender'         => 'male',
                'birthdate'      => '1990-01-01',
                'status'         => STATUS_PENDING,
            ]);
        }

        AuditService::log($userId, 'register', 'auth', $userId, "New {$role} registered");

        $this->flash('success', 'Account created! Please complete your profile.');
        $this->redirectTo('login');
    }

    // ── GET /forgot-password ──────────────────────────────────

    public function showForgotPassword(): void
    {
        $this->render('auth/forgot-password', [
            'title'     => 'Forgot Password – ' . APP_NAME,
            'csrfField' => SecurityHelper::csrfField(),
        ], 'auth');
    }

    // ── POST /forgot-password ─────────────────────────────────

    public function sendResetLink(): void
    {
        if (!SecurityHelper::validateCsrf()) {
            $this->flash('error', 'Invalid CSRF token.');
            $this->redirectTo('forgot-password');
            return;
        }

        $email = SecurityHelper::sanitizeEmail($this->post('email')) ?: '';

        // Always show success message (prevent email enumeration)
        $this->flash('success', 'If that email exists, a reset link has been sent.');

        $user = $this->userModel->findByEmail($email);
        if ($user) {
            $token   = SecurityHelper::generateToken();
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $this->userModel->update((int) $user['id'], [
                'reset_token'      => $token,
                'reset_expires_at' => $expires,
            ]);

            // TODO: queue email via MailService
            AuditService::log($user['id'], 'password_reset_request', 'auth');
        }

        $this->redirectTo('forgot-password');
    }

    // ── GET /reset-password/:token ────────────────────────────

    public function showResetPassword(string $token): void
    {
        $user = $this->userModel->findByResetToken($token);

        if (!$user) {
            $this->flash('error', 'Invalid or expired reset link.');
            $this->redirectTo('login');
            return;
        }

        $this->render('auth/reset-password', [
            'title'     => 'Reset Password – ' . APP_NAME,
            'token'     => $token,
            'csrfField' => SecurityHelper::csrfField(),
        ], 'auth');
    }

    // ── POST /reset-password ──────────────────────────────────

    public function resetPassword(): void
    {
        if (!SecurityHelper::validateCsrf()) {
            $this->flash('error', 'Invalid CSRF token.');
            $this->redirectTo('login');
            return;
        }

        $token   = $this->post('token');
        $pass    = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        $user = $this->userModel->findByResetToken($token);

        if (!$user) {
            $this->flash('error', 'Invalid or expired reset link.');
            $this->redirectTo('login');
            return;
        }

        if (!SecurityHelper::isStrongPassword($pass)) {
            $this->flash('error', 'Password must be at least 8 characters with 1 uppercase and 1 number.');
            $this->redirect(BASE_URL . '/reset-password/' . urlencode($token));
            return;
        }

        if ($pass !== $confirm) {
            $this->flash('error', 'Passwords do not match.');
            $this->redirect(BASE_URL . '/reset-password/' . urlencode($token));
            return;
        }

        $this->userModel->update((int) $user['id'], [
            'password_hash'    => SecurityHelper::hashPassword($pass),
            'reset_token'      => null,
            'reset_expires_at' => null,
        ]);

        AuditService::log($user['id'], 'password_reset', 'auth');
        $this->flash('success', 'Password updated successfully. Please log in.');
        $this->redirectTo('login');
    }
}
