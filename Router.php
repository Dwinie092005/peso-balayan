<?php
/**
 * PESO Balayan – Router
 * File: app/core/Router.php
 *
 * Parses the URL, resolves controller and action,
 * and dispatches the request. No hardcoded routes.
 */

namespace App\Core;

class Router
{
    private array $routes = [];

    // ── ROUTE REGISTRATION ────────────────────────────────────

    public function get(string $path, string $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, string $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function any(string $path, string $handler): void
    {
        $this->addRoute('ANY', $path, $handler);
    }

    private function addRoute(string $method, string $path, string $handler): void
    {
        $this->routes[] = [
            'method'  => $method,
            'path'    => $path,
            'handler' => $handler,
        ];
    }

    // ── DISPATCH ──────────────────────────────────────────────

    /**
     * Match the current request against registered routes
     * and dispatch to the appropriate controller action.
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = $this->parseUri();

        foreach ($this->routes as $route) {
            $pattern = $this->buildPattern($route['path']);

            if (
                ($route['method'] === 'ANY' || $route['method'] === $method)
                && preg_match($pattern, $uri, $matches)
            ) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->call($route['handler'], $params);
                return;
            }
        }

        // No route matched
        http_response_code(404);
        $errorView = ROOT_PATH . '/app/views/errors/404.php';
        if (file_exists($errorView)) {
            require $errorView;
        } else {
            echo '<h1>404 – Page Not Found</h1>';
        }
    }

    // ── HELPERS ───────────────────────────────────────────────

    /**
     * Extract and sanitize the URI segment after BASE_URL.
     */
    private function parseUri(): string
    {
        $basePath = rtrim(parse_url(BASE_URL, PHP_URL_PATH), '/');
        $uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri      = rawurldecode($uri);

        if ($basePath && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }

        return '/' . trim($uri, '/');
    }

    /**
     * Convert route path (with :param placeholders) to regex.
     * E.g. /applicant/:id  →  /applicant/(?P<id>[^/]+)
     */
    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('#:([a-zA-Z_]+)#', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';
        return $pattern;
    }

    /**
     * Resolve handler string "ControllerClass@method" and call it.
     */
    private function call(string $handler, array $params = []): void
    {
        [$class, $method] = explode('@', $handler);

        $fqcn = "App\\Controllers\\{$class}";

        if (!class_exists($fqcn)) {
            http_response_code(500);
            echo APP_DEBUG
                ? "Controller [{$fqcn}] not found."
                : "Internal server error.";
            return;
        }

        $controller = new $fqcn();

        if (!method_exists($controller, $method)) {
            http_response_code(500);
            echo APP_DEBUG
                ? "Action [{$method}] not found on [{$fqcn}]."
                : "Internal server error.";
            return;
        }

        $controller->{$method}(...array_values($params));
    }
}
