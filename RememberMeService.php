<?php

namespace App\Services;

/**
 * RememberMeService
 *
 * Manages "remember me" persistent login cookies.
 * Uses a two-part cookie (selector:validator) strategy.
 *
 * SECURITY MODEL:
 * - Selector is public (non-secret lookup key)
 * - Validator is secret (only hash stored in DB)
 * - Tokens rotate on every successful use
 * - All tokens purged on logout or theft detection
 */
class RememberMeService
{
    private const COOKIE_NAME = 'peso_rm';
    private const COOKIE_DAYS = 30;
    private const SEPARATOR   = ':';

    private TokenService $tokenService;

    public function __construct()
    {
        $this->tokenService = new TokenService();
    }

    /**
     * Issue a remember-me cookie for a user.
     * Called after successful login when "remember me" is checked.
     *
     * @param int $userId
     */
    public function issue(int $userId): void
    {
        $token     = $this->tokenService->createRememberToken($userId);
        $cookieVal = $token['selector'] . self::SEPARATOR . $token['validator'];
        $expires   = time() + (self::COOKIE_DAYS * 86400);

        setcookie(
            self::COOKIE_NAME,
            $cookieVal,
            [
                'expires'  => $expires,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * Validate the remember-me cookie and return user_id if valid.
     * Automatically rotates the token on success.
     *
     * @return int|null  User ID if valid, null otherwise
     */
    public function validate(): ?int
    {
        $cookieVal = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!$cookieVal || substr_count($cookieVal, self::SEPARATOR) !== 1) {
            return null;
        }

        [$selector, $validator] = explode(self::SEPARATOR, $cookieVal, 2);

        if (empty($selector) || empty($validator)) {
            return null;
        }

        $userId = $this->tokenService->validateRememberToken($selector, $validator);

        if ($userId === null) {
            $this->clear();
            return null;
        }

        // Rotate token: delete old, issue new (prevents replay attacks)
        $this->tokenService->deleteRememberToken($selector);
        $this->issue($userId);

        return $userId;
    }

    /**
     * Clear the remember-me cookie and optionally revoke DB tokens.
     *
     * @param int|null $userId  If provided, deletes ALL tokens for this user
     */
    public function clear(?int $userId = null): void
    {
        // Revoke DB token if we have the selector
        $cookieVal = $_COOKIE[self::COOKIE_NAME] ?? null;
        if ($cookieVal && strpos($cookieVal, self::SEPARATOR) !== false) {
            [$selector] = explode(self::SEPARATOR, $cookieVal, 2);
            $this->tokenService->deleteRememberToken($selector);
        }

        // Also purge all tokens for user on full logout
        if ($userId !== null) {
            $this->tokenService->deleteAllRememberTokens($userId);
        }

        // Expire the cookie
        setcookie(
            self::COOKIE_NAME,
            '',
            [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * Check whether a remember-me cookie exists (not validated yet).
     */
    public function hasCookie(): bool
    {
        return !empty($_COOKIE[self::COOKIE_NAME]);
    }
}
