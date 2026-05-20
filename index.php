<?php
/**
 * PESO Balayan – Front Controller
 * File: public/index.php
 *
 * Single entry point for all HTTP requests.
 * No logic here — just bootstrap and run.
 */

define('ROOT_PATH', dirname(__DIR__));

// Load app configuration
require ROOT_PATH . '/config/config.php';

// Bootstrap and run
require ROOT_PATH . '/app/core/App.php';

$app = new App\Core\App();
$app->run();
