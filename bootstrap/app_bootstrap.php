<?php

declare(strict_types=1);

/**
 * ============================================================================
 *  Ivi.php — Application Bootstrap
 * ============================================================================
 *
 * This file bootstraps the Ivi framework runtime environment.
 * It ensures that the application starts consistently, regardless of whether
 * it is executed as a standalone project or installed as a Composer dependency.
 *
 * Responsibilities:
 * - Initialize early error handling
 * - Load Composer autoloader
 * - Load modules
 * - Bootstrap environment constants and configuration
 * - Initialize session & error subsystems
 * - Instantiate and run the app
 *
 * @package Ivi\Core
 * @version 1.2
 * ============================================================================
 */

//
// 1) Early error system
//
require_once dirname(__DIR__) . '/bootstrap/early_errors.php';

//
// 2) Composer autoloader
//
(function (): void {
    $candidates = [
        dirname(__DIR__) . '/vendor/autoload.php',
        dirname(__DIR__, 4) . '/autoload.php',
        (fn() => getenv('COMPOSER_VENDOR_DIR') ? rtrim(getenv('COMPOSER_VENDOR_DIR'), '/\\') . '/autoload.php' : null)(),
        (fn() => getenv('HOME') ? getenv('HOME') . '/.config/composer/vendor/autoload.php' : null)(),
        (fn() => getenv('HOME') ? getenv('HOME') . '/.composer/vendor/autoload.php' : null)(),
    ];

    $candidates = array_values(array_filter(array_unique($candidates)));

    foreach ($candidates as $path) {
        if (is_string($path) && is_file($path)) {
            require_once $path;
            return;
        }
    }

    throw new RuntimeException(
        "Composer autoload not found. Tried paths:\n - " . implode("\n - ", $candidates) .
            "\nHint: run `composer install`."
    );
})();

//
// 2b) Bootstrap environment constants
//
\Ivi\Core\Bootstrap\Loader::bootstrap(dirname(__DIR__));

//
// 2c) Vérifie que les constantes Google sont définies
//
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
}
if (!defined('GOOGLE_REDIRECT_URI')) {
    define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost/auth/google/callback');
}
if (!defined('GOOGLE_SCOPES')) {
    define('GOOGLE_SCOPES', getenv('GOOGLE_SCOPES') ?: 'email,profile');
}

//
// 2d) Initialise Config
//
\Ivi\Core\Config\Config::init(dirname(__DIR__) . '/config');

//
// 3) Modules autoload
//
$modulesAutoload = dirname(__DIR__) . '/support/modules_autoload.php';
if (is_file($modulesAutoload)) {
    require_once $modulesAutoload;
}

//
// 4) Session & error subsystems
//
require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/session.php';

use Ivi\Core\Bootstrap\App;
use Ivi\Core\Router\Router;

//
// 5) Initialise le router avant les routes
//
$router = new Router();

// Inclut routes.php dans le scope où $router existe
$routesFile = dirname(__DIR__) . '/config/routes.php';
if (is_file($routesFile)) {
    require $routesFile;
}

//
// 6) Démarre l'application
//
$app = new App(
    baseDir: dirname(__DIR__),
    resolver: static fn(string $class) => new $class()
);

$app->run();
