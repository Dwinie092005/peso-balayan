<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * TokenService
 *
 * Generates, stores, and validates cryptographically secure tokens
 * for password resets and email verification.
 *
 * SECURITY:
 * - Uses random_bytes(32) for raw token entropy
 * - Stores only SHA-256 hash in database (never raw token)
 * - Enforces expiry on all token types
 * - Single-use: marks token as used_at on consumption
 */
class TokenService
{
    private PDO $db;

    /** Token lifetimes in minutes */
    private const TTL_PASSWORD_RESET  = 60;    // 1 hour
    private const TTL_EMAIL_VERIFY    = 1440;  // 24 hours
    private const REMEMBER_DAYS       = 30;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ── RAW TOKEN GENERATION ─────────────────────────────────

    /**
     * Generate a URL-safe random token string.
     * @param int $bytes Entropy bytes (default 32 = 256 bits)
     * @return string Hex-encoded token
     */
    public function generateRaw(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Hash a raw token for safe database storage.
     * @param string $rawToken
     * @return string SHA-256 hex digest
     */
    public function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    // ── PASSWORD RESET TOKENS ────────────────────────────────

    /**
     * Create a password reset token for a user.
     * Invalidates any existing tokens for that user.
     *
     * @param int    $userId
     * @param string $email
     * @return string Raw token (send to user via email)
     */
    public function createPasswordResetToken(int $userId, string $email): string
    {
        // Invalidate old tokens
        $stmt = $this->db->prepare(
            'UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL'
        );
        $stmt->execute([$userId]);

        $rawToken  = $this->generateRaw();
        $tokenHash = $this->hash($rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + (self::TTL_PASSWORD_RESET * 60));

        $stmt = $this->db->prepare(
            'INSERT INTO password_resets (user_id, email, token_hash, expires_at)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $email, $tokenHash, $expiresAt]);

        return $rawToken;
    }

    /**
     * Validate a password reset token.
     * Returns the reset record if valid, null otherwise.
     *
     * @param string $rawToken
     * @return array|null
     */
    public function validatePasswordResetToken(string $rawToken): ?array
    {
        $tokenHash = $this->hash($rawToken);

        $stmt = $this->db->prepare(
            'SELECT * FROM password_resets
             WHERE token_hash = ?
               AND used_at IS NULL
               AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        return $record ?: null;
    }

    /**
     * Mark a password reset token as consumed.
     * @param string $rawToken
     */
    public function consumePasswordResetToken(string $rawToken): void
    {
        $tokenHash = $this->hash($rawToken);

        $stmt = $this->db->prepare(
            'UPDATE password_resets SET used_at = NOW() WHERE token_hash = ?'
        );
        $stmt->execute([$tokenHash]);
    }

    // ── EMAIL VERIFICATION TOKENS ────────────────────────────

    /**
     * Create an email verification token.
     * @param int    $userId
     * @param string $email
     * @return string Raw token
     */
    public function createEmailVerificationToken(int $userId, string $email): string
    {
        // Remove old unverified tokens for this user
        $stmt = $this->db->prepare(
            'DELETE FROM email_verifications WHERE user_id = ? AND verified_at IS NULL'
        );
        $stmt->execute([$userId]);

        $rawToken  = $this->generateRaw();
        $tokenHash = $this->hash($rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + (self::TTL_EMAIL_VERIFY * 60));

        $stmt = $this->db->prepare(
            'INSERT INTO email_verifications (user_id, email, token_hash, expires_at)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $email, $tokenHash, $expiresAt]);

        return $rawToken;
    }

    /**
     * Validate an email verification token.
     * @param string $rawToken
     * @return array|null
     */
    public function validateEmailVerificationToken(string $rawToken): ?array
    {
        $tokenHash = $this->hash($rawToken);

        $stmt = $this->db->prepare(
            'SELECT * FROM email_verifications
             WHERE token_hash = ?
               AND verified_at IS NULL
               AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        return $record ?: null;
    }

    /**
     * Mark email verification token as verified.
     * @param string $rawToken
     */
    public function consumeEmailVerificationToken(string $rawToken): void
    {
        $tokenHash = $this->hash($rawToken);

        $stmt = $this->db->prepare(
            'UPDATE email_verifications SET verified_at = NOW() WHERE token_hash = ?'
        );
        $stmt->execute([$tokenHash]);
    }

    // ── REMEMBER ME TOKENS ───────────────────────────────────

    /**
     * Create a remember-me token pair (selector + validator).
     * Selector is public (stored in cookie + DB).
     * Validator is secret (cookie only; DB stores hash).
     *
     * @param int $userId
     * @return array ['selector' => string, 'validator' => string, 'expires_at' => string]
     */
    public function createRememberToken(int $userId): array
    {
        $selector  = bin2hex(random_bytes(16)); // 32-char public ID
        $validator = $this->generateRaw(32);    // 64-char secret
        $tokenHash = $this->hash($validator);
        $expiresAt = date('Y-m-d H:i:s', time() + (self::REMEMBER_DAYS * 86400));

        $stmt = $this->db->prepare(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $selector, $tokenHash, $expiresAt]);

        return [
            'selector'   => $selector,
            'validator'  => $validator,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Validate a remember-me token and return the user_id.
     * Returns null if invalid or expired.
     *
     * @param string $selector
     * @param string $validator
     * @return int|null
     */
    public function validateRememberToken(string $selector, string $validator): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM remember_tokens
             WHERE selector = ? AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$selector]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return null;
        }

        if (!hash_equals($record['token_hash'], $this->hash($validator))) {
            // Possible theft attempt — invalidate all tokens for this user
            $this->deleteAllRememberTokens((int)$record['user_id']);
            return null;
        }

        return (int)$record['user_id'];
    }

    /**
     * Delete a specific remember-me token by selector.
     * @param string $selector
     */
    public function deleteRememberToken(string $selector): void
    {
        $stmt = $this->db->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $stmt->execute([$selector]);
    }

    /**
     * Delete ALL remember-me tokens for a user (on logout or theft detection).
     * @param int $userId
     */
    public function deleteAllRememberTokens(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    /**
     * Purge all expired tokens from all token tables (call via cron).
     */
    public function purgeExpiredTokens(): void
    {
        $this->db->exec('DELETE FROM password_resets    WHERE expires_at < NOW()');
        $this->db->exec('DELETE FROM remember_tokens    WHERE expires_at < NOW()');
        $this->db->exec('DELETE FROM email_verifications WHERE expires_at < NOW()');
    }
}
