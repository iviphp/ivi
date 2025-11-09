<?php

declare(strict_types=1);

/**
 * -----------------------------------------------------------------------------
 * Test Bootstrap for Ivi Framework
 * -----------------------------------------------------------------------------
 *
 * This file bootstraps the testing environment for the Ivi Framework.
 * It ensures that the Composer autoloader is available, loads a dedicated
 * `.env.testing` file if present, and configures a consistent runtime
 * environment for PHPUnit.
 *
 * ## Responsibilities
 * - Initialize Composer’s autoloader for class autoloading.
 * - Load environment variables from `.env.testing` using `vlucas/phpdotenv`.
 * - Set a default `APP_ENV=testing` if not explicitly defined.
 *
 * ## Design Notes
 * - Keeps the testing environment isolated from development and production.
 * - Uses `safeLoad()` to avoid exceptions when `.env.testing` is missing.
 * - Compatible with both local PHPUnit runs and CI pipelines.
 *
 * ## Example
 * To create a separate testing environment:
 * ```
 * cp .env .env.testing
 * php vendor/bin/phpunit
 * ```
 *
 * @package  Ivi\Tests
 * @category Bootstrap
 * @version  1.0.0
 * @since    Ivi Framework v1.1
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    throw new RuntimeException("Autoload not found at {$autoload}");
}
require $autoload;

// Load a dedicated .env.testing file if available
$dotenvPath = __DIR__ . '/../';
if (file_exists($dotenvPath . '.env.testing')) {
    $dotEnv = Dotenv\Dotenv::createImmutable($dotenvPath, '.env.testing');
    $dotEnv->safeLoad();
}

// Default environment variable for testing context
$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'testing';
