<?php
/**
 * PESO Balayan – Security & CSRF Helper
 * File: app/helpers/SecurityHelper.php
 */

namespace App\Helpers;

class SecurityHelper
{
    // ── CSRF ──────────────────────────────────────────────────

    /**
     * Generate and store a CSRF token in session.
     */
    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate the CSRF token from POST.
     */
    public static function validateCsrf(): bool
    {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Render a hidden CSRF input field (for forms).
     */
    public static function csrfField(): string
    {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    // ── XSS Prevention ────────────────────────────────────────

    /**
     * Escape output for safe HTML display.
     */
    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // ── Input Sanitization ────────────────────────────────────

    /**
     * Strip tags and trim a string.
     */
    public static function clean(string $value): string
    {
        return trim(strip_tags($value));
    }

    /**
     * Validate and sanitize email.
     */
    public static function sanitizeEmail(string $email): string|false
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Validate email format.
     */
    public static function isValidEmail(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Validate Philippine mobile number (e.g. 09XXXXXXXXX).
     */
    public static function isValidMobile(string $number): bool
    {
        return (bool) preg_match('/^(09|\+639)\d{9}$/', $number);
    }

    // ── Password ──────────────────────────────────────────────

    /**
     * Hash a password using bcrypt.
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify a plain password against a hash.
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check password strength: min 8 chars, 1 uppercase, 1 digit.
     */
    public static function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[0-9]/', $password);
    }

    // ── File Upload Validation ────────────────────────────────

    /**
     * Validate an uploaded file against system rules.
     * Returns ['valid' => bool, 'error' => string]
     */
    public static function validateUpload(array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Upload failed with error code ' . $file['error']];
        }

        if ($file['size'] > MAX_UPLOAD_SIZE) {
            $maxMB = MAX_UPLOAD_SIZE / (1024 * 1024);
            return ['valid' => false, 'error' => "File size exceeds the {$maxMB}MB limit."];
        }

        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($file['tmp_name']);

        if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
            return ['valid' => false, 'error' => 'File type not allowed. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS)];
        }

        if (!in_array($mime, ALLOWED_MIME_TYPES, true)) {
            return ['valid' => false, 'error' => 'File MIME type not allowed.'];
        }

        return ['valid' => true, 'error' => ''];
    }

    /**
     * Generate a safe, unique filename for an upload.
     */
    public static function generateFilename(string $originalName): string
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        return bin2hex(random_bytes(16)) . '_' . time() . '.' . $ext;
    }

    // ── Token Generation ──────────────────────────────────────

    /**
     * Generate a cryptographically secure random token.
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    // ── IP / User Agent ───────────────────────────────────────

    /**
     * Get the client's real IP address.
     */
    public static function getIpAddress(): string
    {
        $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = filter_var(
                    explode(',', $_SERVER[$key])[0],
                    FILTER_VALIDATE_IP
                );
                if ($ip) return $ip;
            }
        }
        return 'unknown';
    }

    /**
     * Get the client's user agent string (sanitized).
     */
    public static function getUserAgent(): string
    {
        return substr(strip_tags($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
