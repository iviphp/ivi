<?php

declare(strict_types=1);

/**
 * -----------------------------------------------------------------------------
 * Global Debugging Helpers for Ivi.php
 * -----------------------------------------------------------------------------
 *
 * This file defines global helper functions for quick and consistent debugging
 * within both framework and user applications.
 *
 * These helpers automatically delegate to the most appropriate logger:
 * - `App\Debug\Logger` if defined by the user (preferred)
 * - otherwise, fallback to `Ivi\Core\Debug\Logger`
 *
 * They provide a uniform interface for dumping or stopping execution while
 * preserving formatting and readability across CLI and web environments.
 *
 * -----------------------------------------------------------------------------
 * Available Functions
 * -----------------------------------------------------------------------------
 * - `dump(mixed $data, array $options = []): void`
 *   Pretty-print any PHP variable using Ivi's Logger or fallback to plain text.
 *
 * - `dd(mixed $data, array $options = []): never`
 *   Equivalent to `dump()` followed by immediate termination (`die`).
 *
 * - `ivi_dump(mixed $data, array $options = []): void`
 *   Internal version that always uses Ivi\Core\Debug\Logger directly.
 *
 * - `ivi_dd(mixed $data, array $options = []): never`
 *   Internal variant of `dd()` using framework logger only.
 *
 * -----------------------------------------------------------------------------
 * Example Usage
 * -----------------------------------------------------------------------------
 * ```php
 * dump($user);
 * dump($config, ['title' => 'Config Snapshot']);
 *
 * dd(['error' => 'Unexpected null value']);
 *
 * ivi_dump(['env' => $_ENV], ['theme' => 'dark']);
 * ```
 *
 * -----------------------------------------------------------------------------
 * Design Notes
 * -----------------------------------------------------------------------------
 * - Uses a layered fallback to ensure output even if Logger is unavailable.
 * - Safe to use in controllers, middleware, or CLI commands.
 * - Automatically sets headers to `text/plain` when necessary.
 * - Designed for both developers and framework internals.
 *
 * @package Ivi\Core\Debug
 * @category Helpers
 * @since 1.0.0
 * -----------------------------------------------------------------------------
 */

if (!function_exists('dump')) {
    /**
     * Dump a variable’s contents in a human-readable format.
     *
     * Delegates to `App\Debug\Logger` or `Ivi\Core\Debug\Logger`.
     * Falls back to `print_r()` if no logger exists.
     *
     * @param mixed $data Data to dump.
     * @param array<string,mixed> $options Optional settings (e.g. 'title', 'theme', 'exit').
     */
    function dump(mixed $data, array $options = []): void
    {
        $title = $options['title'] ?? 'Dump';
        unset($options['title']);

        // 1) Use custom app logger if available
        // if (class_exists(\App\Debug\Logger::class)) {
        //     \App\Debug\Logger::dump($title, $data, $options);
        //     return;
        // }

        // 2) Fallback to Ivi\Core logger
        if (class_exists(\Ivi\Core\Debug\Logger::class)) {
            \Ivi\Core\Debug\Logger::dump($title, $data, $options);
            return;
        }

        // 3) Minimal plain-text fallback
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo $title . ":\n";
        print_r($data);
    }
}

if (!function_exists('dd')) {
    /**
     * Dump the provided data and terminate the script immediately.
     *
     * @param mixed $data Data to dump.
     * @param array<string,mixed> $options Additional dump options.
     * @return never
     */
    function dd(mixed $data, array $options = []): never
    {
        $options['exit'] = true;
        dump($data, $options);
        exit;
    }
}

if (!function_exists('ivi_dump')) {
    /**
     * Internal dump function forcing Ivi\Core\Debug\Logger.
     *
     * @param mixed $data Data to dump.
     * @param array<string,mixed> $options Dump customization options.
     */
    function ivi_dump(mixed $data, array $options = []): void
    {
        $title = $options['title'] ?? 'Dump';
        \Ivi\Core\Debug\Logger::dump($title, $data, $options);
    }
}

if (!function_exists('ivi_dd')) {
    /**
     * Internal version of `dd()` using only Ivi\Core\Debug\Logger.
     *
     * @param mixed $data Data to dump.
     * @param array<string,mixed> $options Optional customization options.
     * @return never
     */
    function ivi_dd(mixed $data, array $options = []): never
    {
        $options['exit'] = true;
        ivi_dump($data, $options);
        exit;
    }
}
