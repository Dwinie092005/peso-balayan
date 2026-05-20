<?php

namespace App\Middleware;

/**
 * GuestMiddleware
 *
 * Ensures that only unauthenticated (guest) users can access
 * certain pages such as login, register, forgot-password.
 *
 * Authenticated users are redirected to their role dashboard.
 *
 * USAGE (in controller constructor or action):
 *   GuestMiddleware::handle();
 */
class GuestMiddleware
{
    /** Dashboard paths indexed by role */
    private const ROLE_DASHBOARDS = [
        'applicant'  => '/applicant/dashboard',
        'employer'   => '/employer/dashboard',
        'admin'      => '/admin/dashboard',
        'super_admin'=> '/admin/dashboard',
    ];

    /**
     * Redirect authenticated users away from guest-only pages.
     * Exits if the user is already logged in.
     */
    public static function handle(): void
    {
        if (!empty($_SESSION['user_id'])) {
            $role   = strtolower($_SESSION['user_role'] ?? '');
            $target = self::ROLE_DASHBOARDS[$role] ?? '/dashboard';
            header('Location: ' . $target);
            exit;
        }
    }

    /**
     * Determine if the current visitor is a guest (unauthenticated).
     *
     * @return bool
     */
    public static function isGuest(): bool
    {
        return empty($_SESSION['user_id']);
    }
}
