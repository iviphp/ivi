<?php

/**
 * -----------------------------------------------------------------------------
 * Configuration: Modules
 * -----------------------------------------------------------------------------
 *
 * Defines the list and load order of all registered modules within the Ivi
 * Framework. Each module listed here can extend or enhance the framework by
 * adding its own configuration, routes, controllers, views, migrations, and
 * other domain-specific logic.
 *
 * ## Purpose
 * - Centralized declaration of all application modules.
 * - Controls the load priority (top to bottom).
 * - Supports optional modular activation: developers can comment out or remove
 *   modules they do not wish to load.
 *
 * ## Example
 * Each module corresponds to a directory under `/modules` or `/src/Modules`
 * implementing the `ModuleContract` interface, e.g.:
 *
 * ```
 * /modules/
 * ├── Market/Core/
 * ├── Market/Products/
 * └── Blog/Core/
 * ```
 *
 * ## Notes
 * - Modules are loaded sequentially; earlier entries take precedence when
 *   merging configurations or defining routes.
 * - Missing modules will be safely ignored by the framework at runtime.
 *
 * @package  Ivi\Config
 * @category Configuration
 * @version  1.0.0
 * @since    Ivi Framework v1.1
 */

return [
    // Load order = priority
    'modules' => [
        'Market/Core',
        'Market/Products',
        'Blog/Core',
    ],
];
