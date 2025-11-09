<?php

namespace App\Modules;

use Ivi\Core\Router\Router;

/**
 * -----------------------------------------------------------------------------
 * Interface: ModuleContract
 * -----------------------------------------------------------------------------
 *
 * Defines the standard interface for all Ivi Framework modules.
 * Any module that integrates with the framework must implement this contract
 * to ensure a consistent and predictable lifecycle.
 *
 * ## Lifecycle Overview
 * Each module implements three core responsibilities:
 *
 * 1. **name()**
 *    - Returns the canonical module name (e.g. `Market/Core`).
 *    - Used internally for identification, debugging, and registry tracking.
 *
 * 2. **register()**
 *    - Called during the **registration phase**.
 *    - Used to merge configuration, bind services, and prepare resources
 *      before routes or views are loaded.
 *    - Must be safe to call without relying on other modules.
 *
 * 3. **boot(Router $router)**
 *    - Called during the **boot phase**, after all modules are registered.
 *    - Used to define routes, register views, and declare migrations.
 *    - Receives the framework’s `Router` instance for dynamic route binding.
 *
 * ## Design Notes
 * - Keeps the module lifecycle deterministic and framework-agnostic.
 * - Supports optional modular activation (modules can be loaded or skipped).
 * - Promotes clean separation between configuration and runtime logic.
 *
 * ## Example Implementation
 * ```php
 * return new class implements ModuleContract {
 *     public function name(): string
 *     {
 *         return 'Blog/Core';
 *     }
 *
 *     public function register(): void
 *     {
 *         // merge config
 *     }
 *
 *     public function boot(Router $router): void
 *     {
 *         $router->get('/blog', [HomeController::class, 'index']);
 *     }
 * };
 * ```
 *
 * @package  Ivi\Modules
 * @category Contracts
 * @version  1.0.0
 * @since    Ivi Framework v1.1
 */
interface ModuleContract
{
    /**
     * Return the canonical name of the module.
     *
     * @return string The unique identifier of the module (e.g. "Market/Core").
     */
    public function name(): string;

    /**
     * Register the module during the initial loading phase.
     *
     * Responsible for binding services, merging configuration files,
     * and preparing the module for bootstrapping.
     *
     * @return void
     */
    public function register(): void;

    /**
     * Boot the module after registration.
     *
     * Called once the router and core framework services are initialized.
     * Should be used to declare routes, register view namespaces,
     * and link migration paths.
     *
     * @param  Router  $router  The router instance for route registration.
     * @return void
     */
    public function boot(Router $router): void;
}
