<?php

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Models\UserModel;
use App\Services\TokenService;
use App\Services\MailService;
use App\Helpers\FlashHelper;
use App\Services\AuditService;

/**
 * VerificationController
 *
 * Handles email verification:
 * 1. Show pending verification notice
 * 2. Verify token from email link
 * 3. Resend verification email
 *
 * SECURITY:
 * - Token consumed on first use
 * - Expired tokens rejected
 * - Must be authenticated to resend
 */
class VerificationController extends Controller
{
    private UserModel    $userModel;
    private TokenService $tokenService;
    private MailService  $mailService;

    public function __construct()
    {
        parent::__construct();
        $this->userModel    = new UserModel();
        $this->tokenService = new TokenService();
        $this->mailService  = new MailService();
    }

    /**
     * GET /verify-email
     * Show the "check your inbox" page.
     */
    public function notice(): void
    {
        $this->requireAuth();

        $this->view('auth/verify-email', [
            'pageTitle' => 'Verify Your Email',
            'layout'    => 'auth',
            'status'    => 'pending',
        ]);
    }

    /**
     * GET /verify-email/confirm?token=xxx
     * Process the verification token from the email link.
     */
    public function confirm(): void
    {
        $rawToken = trim($_GET['token'] ?? '');

        if (empty($rawToken)) {
            FlashHelper::error('Invalid verification link.');
            $this->redirect('/verify-email');
            return;
        }

        $record = $this->tokenService->validateEmailVerificationToken($rawToken);

        if (!$record) {
            FlashHelper::error('This verification link is invalid or has expired. Please request a new one.');
            $this->redirect('/verify-email');
            return;
        }

        $userId = (int)$record['user_id'];

        // Mark email as verified in users table
        $this->userModel->markEmailVerified($userId);

        // Consume token
        $this->tokenService->consumeEmailVerificationToken($rawToken);

        AuditService::log($userId, 'EMAIL_VERIFIED', 'users', $userId, 'Email verified via link');

        $this->view('auth/verify-email', [
            'pageTitle' => 'Email Verified',
            'layout'    => 'auth',
            'status'    => 'verified',
        ]);
    }

    /**
     * POST /verify-email/resend
     * Resend the verification email to the currently logged-in user.
     */
    public function resend(): void
    {
        $this->requireAuth();
        $this->validateCsrf();

        $userId = (int)$this->session->get('user_id');
        $user   = $this->userModel->findById($userId);

        if (!$user) {
            FlashHelper::error('User not found.');
            $this->redirect('/verify-email');
            return;
        }

        if (!empty($user['email_verified_at'])) {
            FlashHelper::info('Your email is already verified.');
            $this->redirect('/dashboard');
            return;
        }

        $rawToken  = $this->tokenService->createEmailVerificationToken($userId, $user['email']);
        $verifyUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . '/verify-email/confirm?token=' . urlencode($rawToken);

        $sent = $this->mailService->sendEmailVerification(
            $user['email'],
            $user['name'] ?? $user['email'],
            $verifyUrl
        );

        if ($sent) {
            FlashHelper::success('Verification email resent. Please check your inbox.');
        } else {
            FlashHelper::error('Failed to send verification email. Please try again later.');
        }

        $this->redirect('/verify-email');
    }
}
