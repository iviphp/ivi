<?php

declare(strict_types=1);

/**
 * -----------------------------------------------------------------------------
 * Test Suite: Core Helper Functions
 * -----------------------------------------------------------------------------
 *
 * This test suite validates the existence and correct behavior of global helper
 * functions provided by the Ivi Framework. These helpers form the foundation of
 * the developer experience by offering safe, expressive access to environment
 * variables, base paths, and other runtime utilities.
 *
 * ## Responsibilities
 * - Ensure that core helper functions (`base_path()`, `env()`) are globally available.
 * - Verify that helpers return consistent and valid values under normal conditions.
 *
 * ## Design Notes
 * - Tests are isolated and lightweight, relying only on built-in PHP behavior.
 * - Environment variables are simulated for deterministic test execution.
 * - No dependency on the full framework bootstrap — this suite can run in isolation.
 *
 * @package  Ivi\Tests\Core
 * @category Tests
 * @version  1.0.0
 * @since    Ivi Framework v1.1
 */

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    /**
     * Verify that the base_path() helper exists and points to a valid directory.
     *
     * This test ensures that the helper is globally registered and resolves
     * correctly to the framework’s root directory.
     *
     * @return void
     */
    public function testBasePathHelperExistsAndIsDir(): void
    {
        $this->assertTrue(function_exists('base_path'), 'base_path() helper should exist');
        $this->assertDirectoryExists(base_path());
    }

    /**
     * Verify that the env() helper reads environment variables correctly.
     *
     * This test simulates a temporary environment variable and checks that
     * the helper retrieves its value safely.
     *
     * @return void
     */
    public function testEnvHelperReadsValue(): void
    {
        // Simulate an environment variable for this test
        putenv('IVI_TEST_KEY=foo');
        $_ENV['IVI_TEST_KEY'] = 'foo';

        $this->assertTrue(function_exists('env'), 'env() helper should exist');
        $this->assertSame('foo', env('IVI_TEST_KEY'));
    }
}
