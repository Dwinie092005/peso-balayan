<?php
/**
 * PESO Balayan – Base Controller
 * File: app/core/Controller.php
 *
 * All controllers extend this. Handles view rendering,
 * JSON responses, redirects, and shared utilities.
 */

namespace App\Core;

abstract class Controller
{
    // ── VIEW RENDERING ────────────────────────────────────────

    /**
     * Render a view file with optional data.
     * Wraps in a layout if $layout is specified.
     *
     * @param string $view   Path relative to app/views/ (e.g. 'auth/login')
     * @param array  $data   Variables passed into the view
     * @param string $layout Layout name (e.g. 'main', 'auth'), or '' for no layout
     */
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        // Make data variables available in views
        extract($data, EXTR_SKIP);

        $viewFile = ROOT_PATH . "/app/views/{$view}.php";
        if (!file_exists($viewFile)) {
            $this->abort(404, "View [{$view}] not found.");
        }

        // Buffer the inner view content
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Wrap with layout, or output bare
        if ($layout) {
            $layoutFile = ROOT_PATH . "/app/views/layouts/{$layout}.php";
            if (!file_exists($layoutFile)) {
                $this->abort(500, "Layout [{$layout}] not found.");
            }
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    // ── JSON RESPONSE ─────────────────────────────────────────

    /**
     * Send a JSON response and halt execution.
     */
    protected function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Shorthand: success JSON.
     */
    protected function jsonSuccess(string $message, array $data = []): void
    {
        $this->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    /**
     * Shorthand: error JSON.
     */
    protected function jsonError(string $message, int $code = 422): void
    {
        $this->json(['success' => false, 'message' => $message], $code);
    }

    // ── REDIRECTS ─────────────────────────────────────────────

    /**
     * Redirect to a URL.
     */
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    /**
     * Redirect to a named route under BASE_URL.
     */
    protected function redirectTo(string $path): void
    {
        $this->redirect(BASE_URL . '/' . ltrim($path, '/'));
    }

    /**
     * Redirect back to the referring URL (or a fallback).
     */
    protected function back(string $fallback = '/'): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/' . ltrim($fallback, '/');
        $this->redirect($ref);
    }

    // ── HTTP ERRORS ───────────────────────────────────────────

    /**
     * Abort with an HTTP status and optional message.
     */
    protected function abort(int $code, string $message = ''): void
    {
        http_response_code($code);
        $errorView = ROOT_PATH . "/app/views/errors/{$code}.php";
        if (file_exists($errorView)) {
            $content = $message;
            require $errorView;
        } else {
            echo "<h1>Error {$code}</h1><p>" . htmlspecialchars($message) . "</p>";
        }
        exit;
    }

    // ── INPUT HELPERS ─────────────────────────────────────────

    /**
     * Get a sanitized POST value.
     */
    protected function post(string $key, string $default = ''): string
    {
        return isset($_POST[$key])
            ? trim(htmlspecialchars($_POST[$key], ENT_QUOTES, 'UTF-8'))
            : $default;
    }

    /**
     * Get a sanitized GET value.
     */
    protected function get(string $key, string $default = ''): string
    {
        return isset($_GET[$key])
            ? trim(htmlspecialchars($_GET[$key], ENT_QUOTES, 'UTF-8'))
            : $default;
    }

    /**
     * Check if the current request is POST.
     */
    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Check if the request expects a JSON response (AJAX).
     */
    protected function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    // ── FLASH MESSAGES ────────────────────────────────────────

    /**
     * Set a flash message in session.
     */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * Retrieve and clear the flash message.
     */
    protected function getFlash(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }

    // ── AUTH SHORTCUT ─────────────────────────────────────────

    /**
     * Get the currently authenticated user from session.
     */
    protected function authUser(): array
    {
        return $_SESSION['user'] ?? [];
    }

    /**
     * Check if a user is logged in.
     */
    protected function isAuthenticated(): bool
    {
        return !empty($_SESSION['user']['id']);
    }
}
