<?php

namespace App\Modules;

use Ivi\Core\Router\Router;

/**
 * -----------------------------------------------------------------------------
 * Class: ModuleRegistry
 * -----------------------------------------------------------------------------
 *
 * Manages the lifecycle of all modules registered within the Ivi Framework.
 * The registry stores active module instances and coordinates their
 * initialization through two main phases: `register()` and `boot()`.
 *
 * ## Responsibilities
 * - Store module instances implementing {@see ModuleContract}.
 * - Call `register()` on each module to bind configuration and services.
 * - Call `boot()` on each module to load routes, views, and migrations.
 *
 * ## Design Notes
 * - Keeps the module loading process deterministic and ordered.
 * - Ensures modules are booted only after successful registration.
 * - Decouples the module management logic from the core application.
 *
 * ## Lifecycle Overview
 * 1. **add()** — Add a module instance to the registry.
 * 2. **registerAll()** — Execute the registration phase for all modules.
 * 3. **bootAll()** — Execute the boot phase for all modules.
 *
 * ## Example Usage
 * ```php
 * $registry = new ModuleRegistry();
 * $registry->add(new BlogModule());
 * $registry->registerAll();
 * $registry->bootAll($router);
 * ```
 *
 * @package  Ivi\Modules
 * @category Core
 * @version  1.0.0
 * @since    Ivi Framework v1.1
 */
final class ModuleRegistry
{
    /** @var ModuleContract[] List of registered module instances. */
    private array $modules = [];

    /**
     * Add a module instance to the registry.
     *
     * @param  ModuleContract  $m  The module instance to register.
     * @return void
     */
    public function add(ModuleContract $m): void
    {
        $this->modules[] = $m;
    }

    /**
     * Register all modules currently stored in the registry.
     *
     * Calls each module’s `register()` method to merge configuration,
     * bind services, or perform early initialization tasks.
     *
     * @return void
     */
    public function registerAll(): void
    {
        foreach ($this->modules as $m) {
            $m->register();
        }
    }

    /**
     * Boot all modules after registration.
     *
     * Calls each module’s `boot()` method to load routes, register view
     * namespaces, and attach migration paths. The application router
     * instance is injected for dynamic route binding.
     *
     * @param  Router  $router  The active router instance.
     * @return void
     */
    public function bootAll(Router $router): void
    {
        foreach ($this->modules as $m) {
            $m->boot($router);
        }
    }
}
