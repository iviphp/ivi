<?php

declare(strict_types=1);

/**
 * Discover module migration paths and (optionally) let modules register config.
 *
 * Returns an array of existing migration directories, e.g.:
 * [
 *   /.../scripts/migrations,
 *   /.../modules/Market/Core/database/migrations,
 *   /.../modules/Blog/Core/database/migrations,
 * ]
 */
function ivi_cli_module_migration_paths(string $baseDir): array
{
    $configFile  = $baseDir . '/config/modules.php';
    $modulesCfg  = is_file($configFile) ? require $configFile : ['modules' => []];
    $modulesList = $modulesCfg['modules'] ?? [];

    $paths = [];

    // (1) Always include global migrations if present
    $global = $baseDir . '/scripts/migrations';
    if (is_dir($global)) {
        $paths[] = $global;
    }

    // (2) Load each Module.php (returns an instance) and call register() so
    //     config merges happen (safe for modules needing config at boot).
    $modules = [];
    foreach ($modulesList as $slug) {
        $moduleFile = $baseDir . "/modules/{$slug}/Module.php";
        if (is_file($moduleFile)) {
            /** @var \App\Modules\ModuleContract $m */
            $m = require $moduleFile;
            $modules[] = $m;
        }
    }
    foreach ($modules as $m) {
        try {
            $m->register();
        } catch (\Throwable $e) {
            // keep CLI resilient
        }
    }

    // (3) Collect each module's migrations folder if it exists
    foreach ($modulesList as $slug) {
        $mig = $baseDir . "/modules/{$slug}/database/migrations";
        if (is_dir($mig)) {
            $paths[] = $mig;
        }
    }

    // De-dupe while preserving order
    $paths = array_values(array_unique($paths));

    return $paths;
}
