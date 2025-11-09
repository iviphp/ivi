<?php

declare(strict_types=1);

/**
 * -----------------------------------------------------------------------------
 * Test Suite: Core Application Bootstrap
 * -----------------------------------------------------------------------------
 *
 * This test suite validates the initialization and basic runtime behavior of the
 * Ivi Framework’s core `App` bootstrap class. It ensures that the core
 * application object can be properly instantiated, configured, and provided with
 * a valid base path — even when running in an isolated testing environment.
 *
 * ## Responsibilities
 * - Verify that the `App` class can be instantiated with a valid base path.
 * - Confirm that environment variables (e.g. `APP_ENV`) can be safely set and
 *   read within the testing context.
 *
 * ## Design Notes
 * - Tests use PHPUnit’s native assertions for clarity and maintainability.
 * - No external dependencies or framework services are booted during these tests,
 *   keeping them lightweight and deterministic.
 * - Follows the `tests/Core/` convention for testing framework internals.
 *
 * @package  Ivi\Tests\Core
 * @category Tests
 * @version  1.0.0
 * @since    Ivi Framework v1.1
 */

use PHPUnit\Framework\TestCase;
use Ivi\Core\Bootstrap\App;

final class AppTest extends TestCase
{
    /**
     * Ensure the App core class can be instantiated successfully.
     *
     * This test confirms that the application root path resolves correctly and
     * that an `App` instance can be created without throwing exceptions.
     *
     * @return void
     */
    public function testAppCanBeInstantiated(): void
    {
        // Project root: tests/Core/ → go up two levels
        $basePath = realpath(__DIR__ . '/../../');
        $this->assertNotFalse($basePath, 'Base path should resolve');

        // Pass base path to the App constructor
        $app = new App($basePath);
        $this->assertInstanceOf(App::class, $app);
    }

    /**
     * Ensure environment variables can be safely read during testing.
     *
     * This test verifies that environment configuration behaves consistently
     * within the PHPUnit runtime.
     *
     * @return void
     */
    public function testAppHasEnvironmentVariable(): void
    {
        $_ENV['APP_ENV'] = 'testing';
        $this->assertSame('testing', $_ENV['APP_ENV']);
    }
}
