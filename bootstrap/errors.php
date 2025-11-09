<?php

declare(strict_types=1);

/**
 * ============================================================================
 *  Ivi.php — Early Error Configuration
 * ============================================================================
 *
 * This file configures PHP’s internal error handling system before the
 * application boot sequence begins. It ensures that all errors, warnings,
 * and notices are displayed during development, allowing developers to
 * detect and debug issues as early as possible.
 *
 * ## Responsibilities
 * - Enable verbose error reporting (`E_ALL`)
 * - Display errors during both runtime and startup
 * - Optionally include additional developer tools from `dev_errors.php`
 *
 * ## Design Notes
 * - This file should be included **before any other bootstrap logic**
 *   to guarantee visibility of critical errors (autoload, environment, etc.).
 * - In production, these directives should be disabled via `php.ini`
 *   or environment-level configuration (`APP_ENV=production`).
 *
 * @package   Ivi\Core
 * @category  Bootstrap
 * @version   1.0
 * ============================================================================
 */

// Enable maximum verbosity for development
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Optionally include developer-specific error helpers
$devErrors = dirname(__DIR__) . '/bootstrap/dev_errors.php';
if (is_file($devErrors)) {
    require_once $devErrors;
}
