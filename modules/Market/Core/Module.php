<?php

/**
 * -----------------------------------------------------------------------------
 * Market/Core Module Definition
 * -----------------------------------------------------------------------------
 *
 * This file defines the **Market/Core** module for the Ivi Framework.
 * It implements the `ModuleContract` interface to provide a fully self-contained
 * registration and boot process, including configuration merging, route loading,
 * view namespace registration, and migration path discovery.
 *
 * ## Responsibilities
 * - Safely merge module configuration (`config/market.php`) with global config.
 * - Register a custom view namespace (`market::...`) for templating.
 * - Load module routes from `routes/web.php` during the boot phase.
 * - Register migration paths (`database/migrations`) for CLI execution.
 *
 * ## Design Principles
 * - **Safe by default:** No assumptions about available global helpers.
 * - **Non-intrusive:** Fallbacks prevent boot errors even in minimal runtime environments.
 * - **Modular:** Fully self-contained and can be loaded independently.
 *
 * @package  Ivi\Modules\Market
 * @category Framework
 * @version  1.0.0
 * @since    Ivi Framework v1.1
 */

use App\Modules\ModuleContract;
use Ivi\Core\Router\Router;

return new class implements ModuleContract {
    /**
     * Get the name of the module.
     *
     * Used internally for module registration and identification.
     *
     * @return string The canonical name of the module.
     */
    public function name(): string
    {
        return 'Market/Core';
    }

    /**
     * Register the module configuration.
     *
     * This method merges the module-specific configuration (`config/market.php`)
     * into the global application configuration. It uses safe fallbacks to ensure
     * stability even if global helpers (`config()`, `config_set()`) are unavailable.
     *
     * The merge is recursive to allow nested configuration arrays to be updated
     * without overwriting existing values.
     *
     * @return void
     */
    public function register(): void
    {
        $path = __DIR__ . '/config/market.php';
        if (is_file($path)) {
            $cfg = require $path;

            // Fetch current configuration (safe fallback)
            $current = function_exists('config')
                ? (array) config('market', [])
                : (array) ($GLOBALS['__ivi_config']['market'] ?? []);

            $merged = array_replace_recursive($current, (array) $cfg);

            // Apply new configuration safely
            if (function_exists('config_set')) {
                config_set('market', $merged);
            } else {
                $GLOBALS['__ivi_config']['market'] = $merged;
            }
        }
    }

    /**
     * Boot the module after registration.
     *
     * During the boot phase, the module performs the following tasks:
     * - Registers its view namespace for blade-like template resolution.
     * - Loads its HTTP routes from `routes/web.php` if present.
     * - Registers its database migrations to the global migration manager
     *   or adds them to a global fallback registry.
     *
     * @param  Router  $router  The router instance provided by the Ivi core.
     * @return void
     */
    public function boot(Router $router): void
    {
        // Register view namespace (market::...)
        \App\Controllers\Controller::addViewNamespace('market', __DIR__ . '/views');

        // Load module routes dynamically
        $routes = __DIR__ . '/routes/web.php';
        if (is_file($routes)) {
            require $routes;
        }

        // Register migrations safely
        $mig = __DIR__ . '/Database/Migrations';
        if (is_dir($mig)) {
            if (function_exists('migrations')) {
                $mgr = migrations();
                if (is_object($mgr) && method_exists($mgr, 'addPath')) {
                    $mgr->addPath($mig);
                } else {
                    // Fallback: store migration path for later CLI processing
                    $GLOBALS['__ivi_migration_paths'] ??= [];
                    if (!in_array($mig, $GLOBALS['__ivi_migration_paths'], true)) {
                        $GLOBALS['__ivi_migration_paths'][] = $mig;
                    }
                }
            } else {
                // Fallback if no migrations() helper is defined
                $GLOBALS['__ivi_migration_paths'] ??= [];
                if (!in_array($mig, $GLOBALS['__ivi_migration_paths'], true)) {
                    $GLOBALS['__ivi_migration_paths'][] = $mig;
                }
            }
        }

        // Seeders (safe fallback registry)
        $seed = __DIR__ . '/Database/Seeders';
        if (is_dir($seed)) {
            $GLOBALS['__ivi_seeder_paths'] ??= [];
            if (!in_array($seed, $GLOBALS['__ivi_seeder_paths'], true)) {
                $GLOBALS['__ivi_seeder_paths'][] = $seed;
            }
        }
    }
};
