<?php
/**
 * PESO Balayan – Application Bootstrap
 * File: app/core/App.php
 *
 * Bootstraps the entire app: autoloader, session, error handling,
 * route loading, and dispatch.
 */

namespace App\Core;

class App
{
    private Router $router;

    public function __construct()
    {
        $this->registerAutoloader();
        $this->initSession();
        $this->setErrorHandling();
        $this->router = new Router();
    }

    /**
     * PSR-4-style autoloader for the App namespace.
     * Maps App\Foo\Bar → app/Foo/Bar.php
     */
    private function registerAutoloader(): void
    {
        spl_autoload_register(function (string $class): void {
            // Strip leading namespace prefix
            $prefix = 'App\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file     = ROOT_PATH . '/app/' . $relative . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }

    /**
     * Start session with security hardening.
     */
    private function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);

            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Strict',
            ]);

            session_start();

            // Regenerate session ID periodically (every 30 min)
            if (!isset($_SESSION['_last_regen'])) {
                $_SESSION['_last_regen'] = time();
            } elseif (time() - $_SESSION['_last_regen'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['_last_regen'] = time();
            }
        }
    }

    /**
     * Configure error handling based on environment.
     */
    private function setErrorHandling(): void
    {
        if (APP_DEBUG) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
            ini_set('error_log', ROOT_PATH . '/logs/error.log');
        }
    }

    /**
     * Load route definitions and run the router.
     */
    public function run(): void
    {
        $routesFile = ROOT_PATH . '/config/routes.php';
        if (file_exists($routesFile)) {
            $router = $this->router;
            require $routesFile;
        }

        $this->router->dispatch();
    }
}
