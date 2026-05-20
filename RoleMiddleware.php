<?php

namespace App\Middleware;

use App\Helpers\FlashHelper;

/**
 * RoleMiddleware
 *
 * Enforces role-based access control on controller actions.
 * Redirects unauthorized users with a flash error message.
 *
 * USAGE (in controller constructor):
 *   RoleMiddleware::require(['admin', 'super_admin']);
 *   RoleMiddleware::require('employer');
 */
class RoleMiddleware
{
    /** Session key for the authenticated user's role */
    private const SESSION_ROLE_KEY = 'user_role';

    /**
     * Require the current session user to have one of the given roles.
     * Redirects to an appropriate page if the check fails.
     *
     * @param string|string[] $roles  Allowed role(s)
     * @param string          $redirect  Override redirect URL
     */
    public static function require($roles, string $redirect = ''): void
    {
        $roles = (array)$roles;

        // Must be authenticated first
        if (empty($_SESSION['user_id'])) {
            FlashHelper::warning('Please log in to continue.');
            self::redirectTo('/login');
        }

        $currentRole = strtolower($_SESSION[self::SESSION_ROLE_KEY] ?? '');

        // Normalise role list for comparison
        $allowed = array_map('strtolower', $roles);

        if (!in_array($currentRole, $allowed, true)) {
            FlashHelper::error('You do not have permission to access that page.');

            // Redirect to role-specific dashboard or override
            $target = $redirect ?: self::dashboardForRole($currentRole);
            self::redirectTo($target);
        }
    }

    /**
     * Return the default dashboard path for a given role.
     *
     * @param string $role
     * @return string
     */
    private static function dashboardForRole(string $role): string
    {
        $map = [
            'applicant'  => '/applicant/dashboard',
            'employer'   => '/employer/dashboard',
            'admin'      => '/admin/dashboard',
            'super_admin'=> '/admin/dashboard',
        ];

        return $map[$role] ?? '/dashboard';
    }

    /**
     * Redirect and terminate execution.
     *
     * @param string $url
     */
    private static function redirectTo(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Return the current user's role from session.
     *
     * @return string|null
     */
    public static function currentRole(): ?string
    {
        return $_SESSION[self::SESSION_ROLE_KEY] ?? null;
    }

    /**
     * Check if current user has a role without redirecting.
     *
     * @param string|string[] $roles
     * @return bool
     */
    public static function is($roles): bool
    {
        $roles       = array_map('strtolower', (array)$roles);
        $currentRole = strtolower($_SESSION[self::SESSION_ROLE_KEY] ?? '');
        return in_array($currentRole, $roles, true);
    }
}
