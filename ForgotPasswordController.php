<?php

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Models\UserModel;
use App\Services\TokenService;
use App\Services\MailService;
use App\Middleware\GuestMiddleware;
use App\Helpers\FlashHelper;
use App\Helpers\AuthHelper;

/**
 * ForgotPasswordController
 *
 * Handles the forgot-password flow:
 * 1. Show the email input form
 * 2. Validate submitted email
 * 3. Generate a secure reset token
 * 4. Send reset link via email
 *
 * SECURITY:
 * - Rate-limited via brute-force tracker
 * - Generic response (no email enumeration)
 * - CSRF protected form
 * - Token stored as hash only
 */
class ForgotPasswordController extends Controller
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
     * GET /forgot-password
     * Display the forgot password form.
     */
    public function index(): void
    {
        $this->view('auth/forgot-password', [
            'pageTitle' => 'Forgot Password',
            'layout'    => 'auth',
        ]);
    }

    /**
     * POST /forgot-password
     * Process the email submission and send reset link.
     */
    public function send(): void
    {
        $this->validateCsrf();

        $email     = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
        $ipAddress = AuthHelper::getClientIp();

        // Rate limit check
        if (AuthHelper::isLockedOut($ipAddress)) {
            $remaining = ceil(AuthHelper::getLockoutSecondsRemaining($ipAddress) / 60);
            FlashHelper::error("Too many attempts. Please try again in {$remaining} minute(s).");
            $this->redirect('/forgot-password');
            return;
        }

        // Validate email format
        if (!AuthHelper::isValidEmail($email)) {
            FlashHelper::error('Please enter a valid email address.');
            $this->redirect('/forgot-password');
            return;
        }

        // Always show the same generic message (prevents email enumeration)
        $genericMessage = 'If that email is registered, you will receive a reset link shortly.';

        $user = $this->userModel->findByEmail($email);

        if ($user) {
            $rawToken = $this->tokenService->createPasswordResetToken((int)$user['id'], $email);

            $resetUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . '/reset-password?token=' . urlencode($rawToken);

            $sent = $this->mailService->sendPasswordReset($email, $user['name'] ?? $email, $resetUrl);

            if (!$sent) {
                error_log('[ForgotPassword] Mail failed for user_id: ' . $user['id']);
            }
        }

        AuthHelper::recordFailedAttempt($ipAddress, $email);

        FlashHelper::success($genericMessage);
        $this->redirect('/forgot-password');
    }
}
