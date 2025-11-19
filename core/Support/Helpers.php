<?php

declare(strict_types=1);

use Dotenv\Dotenv;


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

if (!function_exists('base_path')) {
    /**
     * Absolute path to project root (where composer.json lives).
     *
     * Examples:
     *  base_path();                         // /home/you/iviphp/ivi
     *  base_path('docs/getting-started.md') // /home/you/iviphp/ivi/docs/getting-started.md
     */
    function base_path(string $path = ''): string
    {
        // core/Support/Helpers.php → up 2 levels = project root
        $root = realpath(__DIR__ . '/../../') ?: __DIR__ . '/../../';
        return $path !== '' ? rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $root;
    }
}

if (!function_exists('public_path')) {
    /** Absolute path to /public */
    function public_path(string $path = ''): string
    {
        $base = base_path('public');
        return $path !== '' ? $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $base;
    }
}

if (!function_exists('resource_path')) {
    /** Absolute path to /views, /lang, etc. (you can point to /views by default) */
    function resource_path(string $path = ''): string
    {
        $base = base_path('views');
        return $path !== '' ? $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $base;
    }
}

if (!function_exists('docs_path')) {
    /** Absolute path to /docs */
    function docs_path(string $path = ''): string
    {
        $base = base_path('docs');
        return $path !== '' ? $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $base;
    }
}

if (!function_exists('migrations_add_path')) {
    function migrations_add_path(string $path): void
    {
        if (function_exists('migrations')) {
            $mgr = migrations();
            if (is_object($mgr) && method_exists($mgr, 'addPath')) {
                $mgr->addPath($path);
                return;
            }
        }
        $GLOBALS['__ivi_migration_paths'] ??= [];
        if (!in_array($path, $GLOBALS['__ivi_migration_paths'], true)) {
            $GLOBALS['__ivi_migration_paths'][] = $path;
        }
    }
}

if (!function_exists('configv')) {
    /**
     * Safe config getter — fonctionne avec ou sans le helper config().
     *
     * @param string $key     Clé du paramètre (ex: "market.title")
     * @param mixed  $default Valeur par défaut si non trouvée
     * @return mixed
     */
    function configv(string $key, mixed $default = null): mixed
    {
        // Si ton framework a un helper config()
        if (function_exists('config')) {
            try {
                return config($key, $default);
            } catch (\Throwable) {
                // En cas d'erreur (clé manquante, etc.)
            }
        }

        // Fallback global pour les modules (système Softadastra)
        $segments = explode('.', $key);
        $value = $GLOBALS['__ivi_config'] ?? [];

        foreach ($segments as $seg) {
            if (is_array($value) && array_key_exists($seg, $value)) {
                $value = $value[$seg];
            } else {
                return $default;
            }
        }

        return $value ?? $default;
    }
}

if (!function_exists('cfg')) {
    function cfg(string $key, mixed $default = null): mixed
    {
        return configv($key, $default);
    }
}

if (!function_exists('e')) {
    function e(?string $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config_set')) {
    function config_set(string $key, array $value): void
    {
        $GLOBALS['__ivi_config'][$key] = $value;
    }
}

/* -------------------------------------------------------------------------- */
/* Environment Helpers                                                        */
/* -------------------------------------------------------------------------- */

if (!function_exists('env')) {
    /**
     * Get an environment variable (from $_ENV, $_SERVER, or getenv()).
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        $val = getenv($key);
        return $val !== false ? $val : $default;
    }
}

if (!function_exists('load_env')) {
    /**
     * Load variables from .env (safe: ignore missing).
     *
     * @param string|null $directory Project root by default
     */
    function load_env(?string $directory = null): void
    {
        $dir = $directory ?: base_path();
        if (is_file($dir . '/.env')) {
            Dotenv::createImmutable($dir)->safeLoad();
        }
    }
}

if (!function_exists('app_path')) {
    /** Absolute path to /src (application code). */
    function app_path(string $path = ''): string
    {
        $base = base_path('src');
        return $path !== '' ? $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $base;
    }
}

if (!function_exists('config_path')) {
    /** Absolute path to /config. */
    function config_path(string $path = ''): string
    {
        $base = base_path('config');
        return $path !== '' ? $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $base;
    }
}

if (!function_exists('module_asset')) {
    /**
     * Generate a public URL for a file inside a module's assets or public directory.
     * By default, returns the full HTML tag for CSS/JS.
     *
     * Examples:
     *   module_asset('User/Core', 'assets/css/login.css');
     *   → <link rel="stylesheet" href="/modules/User/Core/assets/css/login.css">
     *
     *   module_asset('User/Core', 'assets/js/login.js');
     *   → <script src="/modules/User/Core/assets/js/login.js"></script>
     *
     * @param string $module
     * @param string $path
     * @param bool $tag If true, return HTML tag; else just URL
     * @return string
     */
    function module_asset(string $module, string $path, bool $tag = true): string
    {
        $modulePath = trim(str_replace('\\', '/', $module), '/');
        $path = ltrim($path, '/');

        $url = function_exists('asset')
            ? asset("modules/{$modulePath}/{$path}")
            : "/modules/{$modulePath}/{$path}";

        if (!$tag) {
            return $url;
        }

        // Détecte le type de fichier par extension
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        return match (strtolower($ext)) {
            'css' => '<link rel="stylesheet" href="' . $url . '">',
            'js'  => '<script src="' . $url . '" defer></script>',
            default => $url,
        };
    }
}


if (!function_exists('module_view_path')) {
    /**
     * Resolve the absolute path to a module's view file.
     *
     * Example:
     *   module_view_path('Market/Core', 'home.php');
     *   → /path/to/project/modules/Market/Core/views/home.php
     *
     * @param  string  $module
     * @param  string  $view
     * @return string
     */
    function module_view_path(string $module, string $view): string
    {
        $base = base_path("modules/" . trim(str_replace('\\', '/', $module), '/'));
        return "{$base}/views/" . ltrim($view, '/');
    }
}

if (!function_exists('current_path')) {
    /**
     * Returns the current request path.
     *
     * Useful for comparing links to determine if they are active.
     *
     * @return string Current path (e.g., "/docs")
     */
    function current_path(): string
    {
        return (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    }
}

if (!function_exists('active_class')) {
    /**
     * Returns the "active" CSS class if the given link matches the current path.
     *
     * @param string $href The URL path to check
     * @param string $class The CSS class to return when active (default: 'active')
     * @return string 'active' if the link matches the current path, otherwise empty
     */
    function active_class(string $href, string $class = 'active'): string
    {
        $path = current_path();
        if ($href === '/') {
            return $path === '/' ? $class : '';
        }
        return str_starts_with($path, rtrim($href, '/')) ? $class : '';
    }
}

if (!function_exists('spa_link')) {
    /**
     * Generates a SPA-ready anchor link (<a>).
     *
     * Internal links automatically get `data-spa` attribute for AJAX navigation.
     * External links (absolute URLs, different domain) are left unchanged.
     *
     * @param string $href The link URL
     * @param string $label The visible text of the link
     * @param array $attrs Additional HTML attributes (e.g., 'class', 'id')
     * @return string HTML anchor tag
     */
    function spa_link(string $href, string $label, array $attrs = []): string
    {
        // Detect external link
        $parsed = parse_url($href);
        $isExternal = isset($parsed['host'])
            || str_starts_with($href, 'http')
            || str_starts_with($href, '//');

        // Internal link: add SPA attribute
        if (!$isExternal) {
            $attrs['data-spa'] = 'true';

            // Ensure class attribute exists
            if (!isset($attrs['class'])) {
                $attrs['class'] = 'nav-link';
            } else {
                $attrs['class'] .= ' nav-link';
            }
        }

        // Build attributes
        $attrString = '';
        foreach ($attrs as $k => $v) {
            $attrString .= sprintf(' %s="%s"', htmlspecialchars($k), htmlspecialchars((string)$v));
        }

        return sprintf(
            '<a href="%s"%s>%s</a>',
            htmlspecialchars($href),
            $attrString,
            htmlspecialchars($label)
        );
    }
}

if (!function_exists('menu')) {
    /**
     * Generates a full HTML menu (SPA-ready).
     *
     * Example:
     * echo menu([
     *     '/' => 'Home',
     *     '/docs' => 'Docs',
     *     '/guide' => 'Guide',
     * ], ['class' => 'nav-links']);
     *
     * @param array $items ['href' => 'Label', ...] The menu items
     * @param array $attrs Attributes for the container <nav> or <ul>
     * @param bool $useList If true, outputs <ul><li>...</li></ul>; otherwise <nav>...</nav>
     * @return string HTML of the menu
     */
    function menu(array $items, array $attrs = [], bool $useList = false): string
    {
        $attrString = '';
        foreach ($attrs as $k => $v) $attrString .= sprintf(' %s="%s"', htmlspecialchars($k), htmlspecialchars((string)$v));

        $html = $useList ? "<ul{$attrString}>" : "<nav{$attrString}>";
        foreach ($items as $href => $label) {
            $linkAttrs = ['data-spa' => true];
            $html .= $useList ? "<li>" . spa_link($href, $label, $linkAttrs) . "</li>" : spa_link($href, $label, $linkAttrs);
        }
        $html .= $useList ? '</ul>' : '</nav>';
        return $html;
    }
}

if (!function_exists('config_value')) {
    /**
     * Global config accessor.
     *
     * Usage:
     *   config_value('google');            // entire google.php array
     *   config_value('google.client_id');  // specific key
     *   config_value('google.foo', 'bar'); // default if key not set
     *
     * @param string|null $key Dot-notated key or null to get all
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    function config_value(?string $key = null, mixed $default = null): mixed
    {
        // Try DI container first
        if (function_exists('container')) {
            try {
                $c = container();
                if ($c && $c->has('config')) {
                    $cfg = $c->get('config');

                    if ($key === null && method_exists($cfg, 'all')) {
                        return $cfg->all();
                    }

                    if ($key !== null && method_exists($cfg, 'get')) {
                        return $cfg->get($key, $default);
                    }
                }
            } catch (\Throwable $e) {
                // fallback to static Config
            }
        }

        // Static fallback
        if ($key === null) {
            return \Ivi\Core\Config\Config::all();
        }

        return \Ivi\Core\Config\Config::get($key, $default);
    }
}

if (!function_exists('log_msg')) {
    /**
     * Global logging helper with console filtering.
     *
     * @param mixed       $value The data to log
     * @param string|null $label Optional label
     * @param string      $level One of: info, debug, warning, error
     * @param bool        $json  Whether to log in JSON format
     * @param bool        $trace Whether to include stack trace
     */
    function log_msg(
        mixed $value,
        ?string $label = null,
        string $level = 'info',
        bool $json = false,
        bool $trace = false
    ): void {
        // Écrire dans le fichier
        \Ivi\Core\Debug\Debug::log($value, $label, $level, $json, $trace);

        // Filtrage console
        $consoleMinLevel = 'info'; // 'debug', 'info', 'warning', 'error'
        $levels = ['debug' => 1, 'info' => 2, 'warning' => 3, 'error' => 4];
        if (PHP_SAPI === 'cli' && ($levels[strtolower($level)] ?? 2) >= ($levels[$consoleMinLevel] ?? 2)) {
            // Afficher en console seulement si >= consoleMinLevel
            $msg = $json ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : print_r($value, true);
            $labelStr = $label ? "[$label] " : '';
            echo strtoupper($level) . " {$labelStr}{$msg}\n";
        }
    }
}

if (!function_exists('log_info')) {
    function log_info(mixed $value, ?string $label = null): void
    {
        log_msg($value, $label, 'info');
    }
}

if (!function_exists('log_debug')) {
    function log_debug(mixed $value, ?string $label = null): void
    {
        log_msg($value, $label, 'debug');
    }
}

if (!function_exists('log_warning')) {
    function log_warning(mixed $value, ?string $label = null): void
    {
        log_msg($value, $label, 'warning');
    }
}

if (!function_exists('log_error')) {
    function log_error(mixed $value, ?string $label = null): void
    {
        log_msg($value, $label, 'error');
    }
}
