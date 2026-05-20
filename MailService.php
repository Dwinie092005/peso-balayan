<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * MailService
 *
 * Wraps PHPMailer for all transactional email sending.
 * Configuration is pulled from environment variables only.
 * Templates are inline HTML — no external template files required.
 */
class MailService
{
    private PHPMailer $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        $this->configure();
    }

    /**
     * Configure SMTP from environment variables.
     */
    private function configure(): void
    {
        $this->mailer->isSMTP();
        $this->mailer->Host       = $_ENV['MAIL_HOST']       ?? 'smtp.gmail.com';
        $this->mailer->Port       = (int)($_ENV['MAIL_PORT'] ?? 587);
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = $_ENV['MAIL_USERNAME']   ?? '';
        $this->mailer->Password   = $_ENV['MAIL_PASSWORD']   ?? '';
        $this->mailer->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] === 'ssl'
                                    ? PHPMailer::ENCRYPTION_SMTPS
                                    : PHPMailer::ENCRYPTION_STARTTLS;

        $this->mailer->setFrom(
            $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@peso-balayan.gov.ph',
            $_ENV['MAIL_FROM_NAME']    ?? 'PESO Balayan'
        );

        $this->mailer->isHTML(true);
        $this->mailer->CharSet = PHPMailer::CHARSET_UTF8;

        // Disable SMTP debug output in production
        $this->mailer->SMTPDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true'
                                   ? SMTP::DEBUG_SERVER
                                   : SMTP::DEBUG_OFF;
    }

    /**
     * Core send method — resets recipients after each send.
     *
     * @param string $toEmail
     * @param string $toName
     * @param string $subject
     * @param string $htmlBody
     * @return bool
     */
    private function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearReplyTos();

            $this->mailer->addAddress($toEmail, $toName);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $htmlBody;
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<p>', '</p>'], "\n", $htmlBody));

            $this->mailer->send();
            return true;

        } catch (MailerException $e) {
            error_log('[MailService] Send failed to ' . $toEmail . ': ' . $e->getMessage());
            return false;
        }
    }

    // ── TRANSACTIONAL EMAIL METHODS ──────────────────────────

    /**
     * Send password reset email.
     *
     * @param string $toEmail
     * @param string $toName
     * @param string $resetUrl  Full reset URL including raw token
     * @return bool
     */
    public function sendPasswordReset(string $toEmail, string $toName, string $resetUrl): bool
    {
        $appName   = $_ENV['APP_NAME']    ?? 'PESO Balayan';
        $expiryMin = 60;

        $subject = "[{$appName}] Reset Your Password";

        $body = $this->wrapTemplate("Reset Your Password", "
            <p>Hi <strong>" . htmlspecialchars($toName) . "</strong>,</p>
            <p>We received a request to reset the password for your <strong>{$appName}</strong> account.</p>
            <p>Click the button below to choose a new password. This link will expire in <strong>{$expiryMin} minutes</strong>.</p>
            <div style='text-align:center; margin:32px 0;'>
                <a href='" . htmlspecialchars($resetUrl) . "'
                   style='background:#2563eb;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px;display:inline-block;'>
                    Reset Password
                </a>
            </div>
            <p>If you did not request a password reset, please ignore this email — your account remains secure.</p>
            <p>For security, do not share this link with anyone.</p>
        ");

        return $this->send($toEmail, $toName, $subject, $body);
    }

    /**
     * Send email verification email.
     *
     * @param string $toEmail
     * @param string $toName
     * @param string $verifyUrl  Full verification URL
     * @return bool
     */
    public function sendEmailVerification(string $toEmail, string $toName, string $verifyUrl): bool
    {
        $appName = $_ENV['APP_NAME'] ?? 'PESO Balayan';
        $subject = "[{$appName}] Verify Your Email Address";

        $body = $this->wrapTemplate("Verify Your Email", "
            <p>Hi <strong>" . htmlspecialchars($toName) . "</strong>,</p>
            <p>Welcome to <strong>{$appName}</strong>! Please confirm your email address to activate your account.</p>
            <div style='text-align:center; margin:32px 0;'>
                <a href='" . htmlspecialchars($verifyUrl) . "'
                   style='background:#16a34a;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px;display:inline-block;'>
                    Verify Email Address
                </a>
            </div>
            <p>This verification link expires in <strong>24 hours</strong>.</p>
            <p>If you did not register for {$appName}, you can safely ignore this email.</p>
        ");

        return $this->send($toEmail, $toName, $subject, $body);
    }

    /**
     * Send password changed notification.
     *
     * @param string $toEmail
     * @param string $toName
     * @return bool
     */
    public function sendPasswordChangedNotice(string $toEmail, string $toName): bool
    {
        $appName    = $_ENV['APP_NAME'] ?? 'PESO Balayan';
        $supportUrl = $_ENV['APP_URL']  ?? '#';
        $subject    = "[{$appName}] Your Password Was Changed";

        $body = $this->wrapTemplate("Password Changed", "
            <p>Hi <strong>" . htmlspecialchars($toName) . "</strong>,</p>
            <p>This is a confirmation that the password for your <strong>{$appName}</strong> account was successfully changed.</p>
            <p>If you made this change, no further action is needed.</p>
            <p>If you did <strong>NOT</strong> make this change, please contact our support team immediately:</p>
            <div style='text-align:center; margin:24px 0;'>
                <a href='" . htmlspecialchars($supportUrl) . "/support'
                   style='background:#dc2626;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;display:inline-block;'>
                    Contact Support
                </a>
            </div>
        ");

        return $this->send($toEmail, $toName, $subject, $body);
    }

    // ── EMAIL TEMPLATE WRAPPER ───────────────────────────────

    /**
     * Wrap email content in a consistent branded HTML template.
     *
     * @param string $heading
     * @param string $content  Inner HTML
     * @return string Full HTML email
     */
    private function wrapTemplate(string $heading, string $content): string
    {
        $appName = $_ENV['APP_NAME']  ?? 'PESO Balayan';
        $year    = date('Y');

        return "
<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1.0'>
<title>{$heading}</title>
</head>
<body style='margin:0;padding:0;background:#f4f6fb;font-family:Arial,sans-serif;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f6fb;padding:40px 0;'>
  <tr>
    <td align='center'>
      <table width='560' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);'>

        <!-- Header -->
        <tr>
          <td style='background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:32px 40px;text-align:center;'>
            <h1 style='color:#ffffff;font-size:22px;margin:0;font-weight:700;letter-spacing:.5px;'>{$appName}</h1>
            <p style='color:rgba(255,255,255,.75);font-size:13px;margin:6px 0 0;'>Automated Qualification Filtering &amp; Decision Support System</p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style='padding:36px 40px;color:#374151;font-size:15px;line-height:1.7;'>
            <h2 style='font-size:20px;color:#1e3a8a;margin:0 0 20px;font-weight:700;'>{$heading}</h2>
            {$content}
            <hr style='border:none;border-top:1px solid #e5e7eb;margin:32px 0;'>
            <p style='font-size:13px;color:#9ca3af;margin:0;'>
                This is an automated message from <strong>{$appName}</strong>.<br>
                Please do not reply to this email.
            </p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style='background:#f9fafb;padding:20px 40px;text-align:center;border-top:1px solid #e5e7eb;'>
            <p style='font-size:12px;color:#9ca3af;margin:0;'>
                &copy; {$year} {$appName} &middot; PESO Balayan, Batangas
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>";
    }
}
