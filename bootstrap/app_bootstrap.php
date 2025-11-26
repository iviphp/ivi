<?php

declare(strict_types=1);

/**
 * ============================================================================
 *  Ivi.php — Application Bootstrap
 * ============================================================================
 *
 * This file bootstraps the Ivi framework runtime environment.
 * It ensures that the application starts consistently, whether executed
 * as a standalone project or installed as a Composer dependency.
 *
 * Responsibilities:
 * - Initialize early error handling
 * - Load Composer autoloader
 * - Load modules
 * - Bootstrap environment constants and configuration
 * - Initialize session & error subsystems
 * - Instantiate and run the application
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
$candidates = [
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__, 4) . '/autoload.php',
    getenv('COMPOSER_VENDOR_DIR') ? rtrim(getenv('COMPOSER_VENDOR_DIR'), '/\\') . '/autoload.php' : null,
    getenv('HOME') ? getenv('HOME') . '/.config/composer/vendor/autoload.php' : null,
    getenv('HOME') ? getenv('HOME') . '/.composer/vendor/autoload.php' : null,
];

$candidates = array_values(array_filter(array_unique($candidates)));
$autoloadIncluded = false;
foreach ($candidates as $path) {
    if (is_string($path) && is_file($path)) {
        require_once $path;
        $autoloadIncluded = true;
        break;
    }
}

if (!$autoloadIncluded) {
    throw new \RuntimeException(
        "Composer autoload not found. Tried paths:\n - " . implode("\n - ", $candidates) .
            "\nHint: run `composer install`."
    );
}

//
// 3) Include Container + helpers
//
$containerFile = dirname(__DIR__) . '/support/container.php';
if (is_file($containerFile)) {
    require_once $containerFile;
}

/**
 * Global configuration accessor.
 *
 * Usage:
 *   config_value('google');            // entire google.php array
 *   config_value('google.client_id');  // specific key
 *   config_value('google.foo', 'bar'); // default if key not set
 */
if (!function_exists('config_value')) {
    function config_value(?string $key = null, mixed $default = null): mixed
    {
        // Try DI container first
        try {
            $c = container();
            if ($c->has('config')) {
                $cfg = $c->get('config');
                if ($key === null && method_exists($cfg, 'all')) {
                    return $cfg->all();
                }
                if ($key !== null && method_exists($cfg, 'get')) {
                    return $cfg->get($key, $default);
                }
            }
        } catch (\Throwable $e) {
            // fallback silently
        }

        // Static fallback
        if ($key === null) {
            return \Ivi\Core\Config\Config::all();
        }

        return \Ivi\Core\Config\Config::get($key, $default);
    }
}

//
// 4) Bootstrap environment constants
//
\Ivi\Core\Bootstrap\Loader::bootstrap(dirname(__DIR__));

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
// 5) Initialize Config
//
\Ivi\Core\Config\Config::init(dirname(__DIR__) . '/config');

//
// 6) Modules autoload
//
$modulesAutoload = dirname(__DIR__) . '/support/modules_autoload.php';
if (is_file($modulesAutoload)) {
    require_once $modulesAutoload;
}

//
// 7) Session & error subsystems
//
require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/session.php';

use Ivi\Core\Bootstrap\App;
use Ivi\Core\Router\Router;

//
// 8) Initialize the router before routes
//
$router = new Router();

// Include routes.php in the scope where $router exists
$routesFile = dirname(__DIR__) . '/config/routes.php';
if (is_file($routesFile)) {
    require $routesFile;
}

//
// 9) Start the application
//
$app = new App(
    baseDir: dirname(__DIR__),
    resolver: static fn(string $class) => make($class)
);

$app->run();
