<?php

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Models\UserModel;
use App\Services\TokenService;
use App\Services\MailService;
use App\Middleware\GuestMiddleware;
use App\Helpers\FlashHelper;
use App\Helpers\AuthHelper;
use App\Services\AuditService;

/**
 * ResetPasswordController
 *
 * Handles the password reset flow:
 * 1. Validate the token from the URL
 * 2. Show the new password form
 * 3. Validate and apply the new password
 * 4. Send confirmation email
 * 5. Invalidate the token
 *
 * SECURITY:
 * - Token is validated and consumed on use
 * - Password is hashed with password_hash()
 * - Session regenerated after reset
 * - CSRF protected
 */
class ResetPasswordController extends Controller
{
    private UserModel    $userModel;
    private TokenService $tokenService;
    private MailService  $mailService;

    public function __construct()
    {
        parent::__construct();
        GuestMiddleware::handle();

        $this->userModel    = new UserModel();
        $this->tokenService = new TokenService();
        $this->mailService  = new MailService();
    }

    /**
     * GET /reset-password?token=xxx
     * Show the new password form, after verifying the token.
     */
    public function index(): void
    {
        $rawToken = trim($_GET['token'] ?? '');

        if (empty($rawToken)) {
            FlashHelper::error('Invalid or missing reset link.');
            $this->redirect('/forgot-password');
            return;
        }

        $record = $this->tokenService->validatePasswordResetToken($rawToken);

        if (!$record) {
            FlashHelper::error('This reset link is invalid or has expired. Please request a new one.');
            $this->redirect('/forgot-password');
            return;
        }

        $this->view('auth/reset-password', [
            'pageTitle' => 'Set New Password',
            'layout'    => 'auth',
            'token'     => $rawToken,
            'email'     => $record['email'],
        ]);
    }

    /**
     * POST /reset-password
     * Apply the new password.
     */
    public function update(): void
    {
        $this->validateCsrf();

        $rawToken        = trim($_POST['token']            ?? '');
        $newPassword     = $_POST['password']              ?? '';
        $confirmPassword = $_POST['password_confirmation'] ?? '';

        // Re-validate token
        $record = $this->tokenService->validatePasswordResetToken($rawToken);

        if (!$record) {
            FlashHelper::error('This reset link is invalid or has expired.');
            $this->redirect('/forgot-password');
            return;
        }

        // Password match check
        if (!AuthHelper::passwordsMatch($newPassword, $confirmPassword)) {
            FlashHelper::error('Passwords do not match.');
            $this->redirect('/reset-password?token=' . urlencode($rawToken));
            return;
        }

        // Password strength check
        $errors = AuthHelper::validatePassword($newPassword);
        if (!empty($errors)) {
            foreach ($errors as $err) {
                FlashHelper::error($err);
            }
            $this->redirect('/reset-password?token=' . urlencode($rawToken));
            return;
        }

        // Hash and update password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $userId         = (int)$record['user_id'];

        $this->userModel->updatePassword($userId, $hashedPassword);

        // Consume token (single-use)
        $this->tokenService->consumePasswordResetToken($rawToken);

        // Send notification
        $user = $this->userModel->findById($userId);
        if ($user) {
            $this->mailService->sendPasswordChangedNotice($user['email'], $user['name'] ?? $user['email']);
        }

        AuditService::log($userId, 'PASSWORD_RESET', 'users', $userId, 'Password reset via email link');

        FlashHelper::success('Your password has been reset successfully. You can now log in.');
        $this->redirect('/login');
    }
}
