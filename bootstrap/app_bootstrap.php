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
 * ## Responsibilities
 * - Initialize early error handling (critical for boot diagnostics)
 * - Locate and include the Composer autoloader (portable resolution)
 * - Optionally register module autoloaders
 * - Initialize error and session subsystems
 * - Instantiate and run the core application
 *
 * ## Autoload Resolution Strategy
 * 1. Local vendor directory → `<project_root>/vendor/autoload.php`
 * 2. Dependency context → `<parent_project>/vendor/autoload.php`
 * 3. User-defined vendor directory via `COMPOSER_VENDOR_DIR`
 * 4. Global Composer installation paths:
 *    - `~/.config/composer/vendor/autoload.php`
 *    - `~/.composer/vendor/autoload.php`
 *
 * ## Design Notes
 * - Fully portable: safe to include from both local and dependency contexts
 * - Clear diagnostics when no valid autoload file is found
 * - Isolated from global state until after autoload and session bootstrap
 *
 * @package   Ivi\Core
 * @category  Bootstrap
 * @version   1.0
 * ============================================================================
 */

//
// 1) Early error system (must be first)
//
require_once dirname(__DIR__) . '/bootstrap/early_errors.php';

//
// 2) Robust Composer autoloader resolution
//
(function (): void {
    $candidates = [
        // Local project installation
        dirname(__DIR__) . '/vendor/autoload.php',

        // Composer dependency installation (e.g., vendor/iviphp/ivi/bootstrap)
        dirname(__DIR__, 4) . '/autoload.php',

        // User-defined vendor directory (if set)
        (function () {
            $vd = getenv('COMPOSER_VENDOR_DIR');
            return $vd ? rtrim($vd, '/\\') . '/autoload.php' : null;
        })(),

        // Global Composer installations
        (function () {
            $home = getenv('HOME');
            return $home ? $home . '/.config/composer/vendor/autoload.php' : null;
        })(),
        (function () {
            $home = getenv('HOME');
            return $home ? $home . '/.composer/vendor/autoload.php' : null;
        })(),
    ];

    $candidates = array_values(array_filter(array_unique($candidates ?? [])));

    foreach ($candidates as $path) {
        if (is_string($path) && is_file($path)) {
            require_once $path;
            return;
        }
    }

    $tried = implode("\n - ", $candidates);
    throw new RuntimeException(
        "Composer autoload not found. Tried paths:\n - " . $tried .
            "\nHint: run `composer install` at the project root, or ensure ivi.php is installed correctly."
    );
})();

//
// 3) Module autoloading (optional)
//
$modulesAutoload = dirname(__DIR__) . '/support/modules_autoload.php';
if (is_file($modulesAutoload)) {
    require_once $modulesAutoload;
}

//
// 4) Local subsystems: error and session initialization
//
require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/session.php';

use Ivi\Core\Bootstrap\App;

//
// 5) Application startup
//
$app = new App(
    baseDir: dirname(__DIR__),
    resolver: static fn(string $class) => new $class()
);

$app->run();
