<?php

declare(strict_types=1);

namespace Ivi\Core\Config;

/**
 * Class Config
 *
 * Lightweight, on-demand configuration loader with full initialization.
 *
 * Features:
 *  - Preloads all config files (except routes.php) on init
 *  - Supports dot-notation: "file.key"
 *  - Caches loaded configs in memory
 *  - Allows runtime modification
 *
 * Usage:
 *   Config::init(base_path('config'));
 *   $google = Config::get('google');
 *   $clientId = Config::get('google.client_id');
 */
final class Config
{
    /** @var array<string, mixed> Cached keys */
    private static array $cache = [];

    /** @var array<string, array> Loaded files */
    private static array $loadedFiles = [];

    /** @var string|null Config directory */
    private static ?string $configDir = null;

    /**
     * Initialize the config system and preload all config files
     * except routes.php
     *
     * @param string $configDir Absolute path to config directory
     */
    public static function init(string $configDir): void
    {
        self::$configDir = rtrim($configDir, '/');
        self::$loadedFiles = [];
        self::$cache = [];

        // Load all PHP files in config/ except routes.php
        $files = glob(self::$configDir . '/*.php');
        if (!$files) {
            return;
        }

        foreach ($files as $filePath) {
            $filename = basename($filePath, '.php');
            if ($filename === 'routes') {
                continue; // skip routes.php
            }
            $data = require $filePath;
            if (!is_array($data)) {
                throw new \RuntimeException("Config file {$filename}.php must return an array.");
            }
            self::$loadedFiles[$filename] = $data;
        }
    }

    /**
     * Get a config value.
     *
     * @param string $key     "file.key" or just "file"
     * @param mixed  $default Default if missing
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        if (!str_contains($key, '.')) {
            $value = self::$loadedFiles[$key] ?? $default;
            self::$cache[$key] = $value;
            return $value;
        }

        [$file, $subkey] = self::parseKey($key);

        $value = self::$loadedFiles[$file][$subkey] ?? $default;
        self::$cache[$key] = $value;
        return $value;
    }

    /**
     * Set a value at runtime
     */
    public static function set(string $key, mixed $value): void
    {
        if (!str_contains($key, '.')) {
            self::$loadedFiles[$key] = $value;
            self::$cache[$key] = $value;
            return;
        }

        [$file, $subkey] = self::parseKey($key);

        if (!isset(self::$loadedFiles[$file])) {
            self::$loadedFiles[$file] = [];
        }

        self::$loadedFiles[$file][$subkey] = $value;
        self::$cache[$key] = $value;
    }

    /**
     * Parse a dot-notation key
     */
    private static function parseKey(string $key): array
    {
        $parts = explode('.', $key, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Invalid config key: {$key}");
        }
        return [$parts[0], $parts[1]];
    }

    /**
     * Return all loaded files
     */
    public static function all(): array
    {
        return self::$loadedFiles;
    }

    /**
     * Check if a key exists
     */
    public static function has(string $key): bool
    {
        return self::get($key, '__not_set__') !== '__not_set__';
    }
}
