<?php

namespace App\Helpers;

use App\Core\Database;
use PDO;

/**
 * AuthHelper
 *
 * Provides authentication utility methods:
 * - Password strength validation
 * - Brute-force protection (login attempt rate limiting)
 * - Session hardening utilities
 */
class AuthHelper
{
    /** Max failed attempts before lockout */
    private const MAX_ATTEMPTS = 5;

    /** Lockout window in minutes */
    private const LOCKOUT_MINUTES = 15;

    // ── PASSWORD VALIDATION ──────────────────────────────────

    /**
     * Validate password strength.
     * Returns array of error messages (empty = valid).
     *
     * @param string $password
     * @return string[]
     */
    public static function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }

        if (!preg_match('/[\W_]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }

        return $errors;
    }

    /**
     * Validate that two password inputs match.
     *
     * @param string $password
     * @param string $confirm
     * @return bool
     */
    public static function passwordsMatch(string $password, string $confirm): bool
    {
        return $password === $confirm;
    }

    /**
     * Validate email format.
     *
     * @param string $email
     * @return bool
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    // ── BRUTE FORCE PROTECTION ───────────────────────────────

    /**
     * Record a failed login attempt for an IP address.
     *
     * @param string      $ipAddress
     * @param string|null $email
     */
    public static function recordFailedAttempt(string $ipAddress, ?string $email = null): void
    {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'INSERT INTO login_attempts (ip_address, email, attempted_at) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$ipAddress, $email]);
    }

    /**
     * Check whether an IP is currently locked out.
     *
     * @param string $ipAddress
     * @return bool
     */
    public static function isLockedOut(string $ipAddress): bool
    {
        $db       = Database::getInstance()->getConnection();
        $cutoff   = date('Y-m-d H:i:s', time() - (self::LOCKOUT_MINUTES * 60));

        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = ? AND attempted_at >= ?'
        );
        $stmt->execute([$ipAddress, $cutoff]);
        $count = (int)$stmt->fetchColumn();

        return $count >= self::MAX_ATTEMPTS;
    }

    /**
     * Get how many seconds remain in the lockout window for an IP.
     *
     * @param string $ipAddress
     * @return int  Seconds remaining (0 if not locked)
     */
    public static function getLockoutSecondsRemaining(string $ipAddress): int
    {
        $db     = Database::getInstance()->getConnection();
        $cutoff = date('Y-m-d H:i:s', time() - (self::LOCKOUT_MINUTES * 60));

        $stmt = $db->prepare(
            'SELECT attempted_at FROM login_attempts
             WHERE ip_address = ? AND attempted_at >= ?
             ORDER BY attempted_at ASC
             LIMIT 1'
        );
        $stmt->execute([$ipAddress, $cutoff]);
        $oldest = $stmt->fetchColumn();

        if (!$oldest) return 0;

        $unlockAt = strtotime($oldest) + (self::LOCKOUT_MINUTES * 60);
        return max(0, $unlockAt - time());
    }

    /**
     * Clear login attempts for an IP (called on successful login).
     *
     * @param string $ipAddress
     */
    public static function clearAttempts(string $ipAddress): void
    {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare('DELETE FROM login_attempts WHERE ip_address = ?');
        $stmt->execute([$ipAddress]);
    }

    // ── SESSION HARDENING ────────────────────────────────────

    /**
     * Regenerate session ID and bind fingerprint.
     * Call after successful login to prevent session fixation.
     */
    public static function regenerateSession(): void
    {
        session_regenerate_id(true);
        $_SESSION['_ip']         = self::getClientIp();
        $_SESSION['_ua']         = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['_login_time'] = time();
    }

    /**
     * Validate session fingerprint on each request.
     * Returns false if the session appears hijacked.
     *
     * @return bool
     */
    public static function isSessionValid(): bool
    {
        if (empty($_SESSION['_ip']) || empty($_SESSION['_ua'])) {
            return false;
        }

        $ipMatch = $_SESSION['_ip'] === self::getClientIp();
        $uaMatch = $_SESSION['_ua'] === ($_SERVER['HTTP_USER_AGENT'] ?? '');

        return $ipMatch && $uaMatch;
    }

    /**
     * Check whether the session has exceeded the idle timeout.
     *
     * @param int $timeoutMinutes  Default: 120 minutes
     * @return bool  True = timed out
     */
    public static function isSessionTimedOut(int $timeoutMinutes = 120): bool
    {
        if (empty($_SESSION['_last_activity'])) {
            $_SESSION['_last_activity'] = time();
            return false;
        }

        $elapsed = time() - (int)$_SESSION['_last_activity'];

        if ($elapsed > ($timeoutMinutes * 60)) {
            return true;
        }

        $_SESSION['_last_activity'] = time();
        return false;
    }

    /**
     * Get the real client IP address.
     *
     * @return string
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
