<?php

/**
 * -----------------------------------------------------------------------------
 * Routes: Market/Core Module
 * -----------------------------------------------------------------------------
 *
 * Defines the HTTP routes for the **Market/Core** module.
 * These routes are automatically registered when the module is loaded through
 * the Ivi Framework's modular system. The module remains optional and can be
 * activated or disabled independently.
 *
 * ## Routes
 * - `GET /market`
 *   → Renders the Market home page via `HomeController@index`.
 *
 * - `GET /market/ping`
 *   → Returns a simple JSON response for health checking and testing
 *     whether the module is properly loaded.
 *
 * ## Design Notes
 * - Fully compatible with the Ivi router (`Ivi\Core\Router\Router`).
 * - Uses controller-based routing for maintainability and anonymous closures
 *   for lightweight diagnostics.
 * - Safe to remove or extend — modules can define their own isolated route sets.
 *
 * @package  Market\Core\Routes
 * @category Routing
 * @version  1.0.0
 * @since    Ivi Framework v1.1
 */

use Modules\Market\Core\Http\Controllers\HomeController;
use Ivi\Http\JsonResponse;

/** @var \Ivi\Core\Router\Router $router */
$router->get('/market', [HomeController::class, 'index']);
$router->get('/market/ping', fn() => new JsonResponse([
    'ok' => true,
    'module' => 'Market/Core'
]));
