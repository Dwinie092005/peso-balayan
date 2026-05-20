<?php
/**
 * PESO Balayan – Auth Middleware
 * File: app/middleware/AuthMiddleware.php
 *
 * Protects routes based on authenticated session and role.
 * Call AuthMiddleware::requireRole(ROLE_ADMIN) at the top of any controller action.
 */

namespace App\Middleware;

class AuthMiddleware
{
    /**
     * Require the user to be logged in.
     * Redirects to /login if not authenticated.
     */
    public static function requireAuth(): void
    {
        if (empty($_SESSION['user']['id'])) {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Please log in to continue.'];
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        // Check account is still active
        if (empty($_SESSION['user']['is_active'])) {
            session_destroy();
            header('Location: ' . BASE_URL . '/login?reason=inactive');
            exit;
        }
    }

    /**
     * Require a specific role (or array of roles).
     *
     * Usage:
     *   AuthMiddleware::requireRole(ROLE_ADMIN);
     *   AuthMiddleware::requireRole([ROLE_ADMIN, ROLE_SUPER_ADMIN]);
     */
    public static function requireRole(string|array $roles): void
    {
        self::requireAuth();

        $roles       = (array) $roles;
        $currentRole = $_SESSION['user']['role'] ?? '';

        if (!in_array($currentRole, $roles, true)) {
            http_response_code(403);
            $errorView = ROOT_PATH . '/app/views/errors/403.php';
            if (file_exists($errorView)) {
                require $errorView;
            } else {
                echo '<h1>403 – Access Denied</h1><p>You do not have permission to view this page.</p>';
            }
            exit;
        }
    }

    /**
     * Redirect already-authenticated users away from guest pages (login, register).
     */
    public static function redirectIfAuthenticated(): void
    {
        if (!empty($_SESSION['user']['id'])) {
            $role = $_SESSION['user']['role'] ?? ROLE_APPLICANT;
            header('Location: ' . self::dashboardUrl($role));
            exit;
        }
    }

    /**
     * Resolve the correct dashboard URL for a given role.
     */
    public static function dashboardUrl(string $role): string
    {
        $map = [
            ROLE_APPLICANT   => BASE_URL . '/applicant/dashboard',
            ROLE_EMPLOYER    => BASE_URL . '/employer/dashboard',
            ROLE_ADMIN       => BASE_URL . '/admin/dashboard',
            ROLE_SUPER_ADMIN => BASE_URL . '/superadmin/dashboard',
        ];

        return $map[$role] ?? BASE_URL . '/login';
    }
}
