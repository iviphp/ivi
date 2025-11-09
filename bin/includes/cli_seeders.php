<?php

declare(strict_types=1);

/**
 * Discover seeder paths for global app and modules.
 *
 * Returns an ordered list of existing directories, e.g.:
 * [
 *   /.../scripts/seeders,
 *   /.../modules/Market/Core/database/seeders,
 *   /.../modules/Blog/Core/database/seeders,
 * ]
 */
function ivi_cli_module_seeder_paths(string $baseDir): array
{
    $configFile  = $baseDir . '/config/modules.php';
    $modulesCfg  = is_file($configFile) ? require $configFile : ['modules' => []];
    $modulesList = $modulesCfg['modules'] ?? [];

    $paths = [];

    // (1) Global seeders (optionnel)
    $global = $baseDir . '/scripts/seeders';
    if (is_dir($global)) {
        $paths[] = $global;
    }

    // (2) Modules seeders
    foreach ($modulesList as $slug) {
        $seed = $baseDir . "/modules/{$slug}/database/seeders";
        if (is_dir($seed)) {
            $paths[] = $seed;
        }
    }

    return array_values(array_unique($paths));
}
