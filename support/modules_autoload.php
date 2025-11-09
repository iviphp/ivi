<?php

declare(strict_types=1);

/**
 * Ivi.php Modules Autoloader (PSR-4-like)
 *
 * - Ignore Ivi\* et App\* (gérés par Composer)
 * - Résout en priorité: modules/Vendor/Package/src/Rest.php
 * - Fallbacks:          modules/Vendor/Package/Rest.php
 *                      modules/Full/Path.php
 *                      src/Modules/Vendor/Package[/src]/Rest.php
 *
 * Exemple:
 *   Market\Core\Infra\Http\Controllers\HomeController
 * -> modules/Market/Core/src/Infra/Http/Controllers/HomeController.php
 */
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Ivi\\') || str_starts_with($class, 'App\\')) {
        return;
    }
    if (strpos($class, '\\') === false) {
        return;
    }

    $debug = (bool) (getenv('IVI_DEBUG_MODULES')
        ?: ($_ENV['IVI_DEBUG_MODULES'] ?? null)
        ?: ($_SERVER['IVI_DEBUG_MODULES'] ?? null));

    $root      = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
    $roots     = [$root . '/modules', $root . '/src/Modules'];

    $segments  = explode('\\', ltrim($class, '\\'));
    $vendor    = $segments[0] ?? null;
    $package   = $segments[1] ?? null;
    $restSegs  = array_slice($segments, 2);
    $fullPath  = implode('/', $segments) . '.php';       // modules/Market/Core/Infra/Http/Controllers/HomeController.php
    $restPath  = implode('/', $restSegs) . '.php';       // Infra/Http/Controllers/HomeController.php

    $candidates = [];

    foreach ($roots as $base) {
        // 1) modules/Vendor/Package/Rest.php (PSR-4 simple)
        if ($vendor && $package && $restSegs) {
            $candidates[] = "$base/$vendor/$package/$restPath";
        }
        // 2) modules/Vendor/Package/Rest.php
        if ($vendor && $package && $restSegs) {
            $candidates[] = "$base/$vendor/$package/$restPath";
        }
        // 3) modules/Full/Path.php (ancienne règle naïve)
        $candidates[] = "$base/$fullPath";
    }

    foreach (array_unique($candidates) as $path) {
        if ($debug) {
            fwrite(STDERR, "[modules_autoload] try: {$class} -> {$path} " . (is_file($path) ? "(FOUND)\n" : "(MISSING)\n"));
        }
        if (is_file($path)) {
            require $path;
            return;
        }
    }
});
