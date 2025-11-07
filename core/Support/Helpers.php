<?php

declare(strict_types=1);

/**
 * -----------------------------------------------------------------------------
 * Global Helpers & Aliases for Ivi.php
 * -----------------------------------------------------------------------------
 *
 * This file defines global helper functions available throughout both the
 * framework core and user applications. It provides:
 *
 * 1. **Debugging Utilities**
 *    - `dump()`, `dd()`, `ivi_dump()`, `ivi_dd()` for consistent output.
 *
 * 2. **Collection Aliases**
 *    - `vector([...])`  → Ivi\Core\Collections\Vector
 *    - `hashmap([...])` → Ivi\Core\Collections\HashMap
 *    - `hashset([...])` → Ivi\Core\Collections\HashSet
 *    - `str("...")`     → Ivi\Core\Collections\Str
 *
 * These aliases are safe, autoloaded, and usable anywhere inside the framework.
 *
 * -----------------------------------------------------------------------------
 * Example Usage
 * -----------------------------------------------------------------------------
 * ```php
 * $v = vector([1, 2, 3]);
 * $v->push(4);
 *
 * $m = hashmap(['lang' => 'PHP']);
 * $m->put('version', '1.0');
 *
 * $s = hashset(['apple', 'banana']);
 * $s->add('orange');
 *
 * $t = str(' Hello Ivi ')->trim()->upper();
 * dump($t->toString());
 * ```
 *
 * -----------------------------------------------------------------------------
 * @package Ivi\Core\Support
 * @category Helpers
 * @since 1.0.0
 * -----------------------------------------------------------------------------
 */

use Ivi\Core\Debug\Logger;
use Ivi\Core\Collections\Vector;
use Ivi\Core\Collections\HashMap;
use Ivi\Core\Collections\HashSet;
use Ivi\Core\Collections\Str;

/* -------------------------------------------------------------------------- */
/* Debugging Helpers                                                          */
/* -------------------------------------------------------------------------- */

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

        if (class_exists(Logger::class)) {
            Logger::dump($title, $data, $options);
            return;
        }

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
        Logger::dump($title, $data, $options);
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

/* -------------------------------------------------------------------------- */
/* Collection Aliases                                                         */
/* -------------------------------------------------------------------------- */

/**
 * Create a new Vector instance.
 *
 * @template T
 * @param iterable<T> $items Initial elements.
 * @return Vector<T>
 */
if (!function_exists('vector')) {
    function vector(iterable $items = []): Vector
    {
        return new Vector($items);
    }
}

/**
 * Create a new HashMap instance.
 *
 * @template K of array-key
 * @template V
 * @param iterable<K,V> $items Initial key/value pairs.
 * @return HashMap<K,V>
 */
if (!function_exists('hashmap')) {
    function hashmap(iterable $items = []): HashMap
    {
        return new HashMap($items);
    }
}

/**
 * Create a new HashSet instance.
 *
 * @template T of array-key
 * @param iterable<T> $items Initial elements.
 * @return HashSet<T>
 */
if (!function_exists('hashset')) {
    function hashset(iterable $items = []): HashSet
    {
        return new HashSet($items);
    }
}

/**
 * Create a fluent string wrapper (`Ivi\Core\Collections\Str`).
 *
 * @param string $value The initial string.
 * @return Str
 */
if (!function_exists('str')) {
    function str(string $value): Str
    {
        return new Str($value);
    }
}

if (!function_exists('base_url')) {
    /**
     * Build a base URL from current request host + optional path.
     * NOTE: ajuste si tu as une config d'URL connue (APP_URL).
     */
    function base_url(string $path = ''): string
    {
        // Si tu utilises $_ENV['APP_URL'], c'est encore mieux:
        // $base = rtrim($_ENV['APP_URL'] ?? '', '/');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = $scheme . '://' . $host;
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * Generate an asset URL, with cache-busting by filemtime (query param).
     *
     * @param string $path e.g. 'assets/css/app.css'
     * @param bool   $version Append ?v=mtime for cache-busting
     */
    function asset(string $path, bool $version = true): string
    {
        $publicPath = __DIR__ . '/../../public/' . ltrim($path, '/');
        $url = base_url($path);

        if ($version && is_file($publicPath)) {
            $ts = filemtime($publicPath) ?: time();
            $sep = (str_contains($url, '?') ? '&' : '?');
            $url .= $sep . 'v=' . $ts;
        }
        return $url;
    }
}

if (!function_exists('mix')) {
    /**
     * Resolve a hashed asset via manifest.json (Vite/Webpack-like).
     * Falls back to `asset()` if manifest not found or missing entry.
     *
     * @param string $logical e.g. 'assets/js/app.js'
     */
    function mix(string $logical): string
    {
        $manifestFile = __DIR__ . '/../../public/manifest.json';
        if (is_file($manifestFile)) {
            $map = json_decode((string)file_get_contents($manifestFile), true) ?: [];
            if (isset($map[$logical])) {
                return base_url($map[$logical]);
            }
        }
        return asset($logical, true);
    }
}
