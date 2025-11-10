<?php

declare(strict_types=1);

namespace Ivi\Core\Bootstrap;

use Ivi\Http\Request;
use Ivi\Core\Router\Router;
use Ivi\Core\Debug\Logger;
use Ivi\Core\Exceptions\ExceptionHandler;
use App\Modules\ModuleRegistry;

/**
 * Class App
 *
 * The main application bootstrapper for ivi.php.
 * Responsible for:
 *  - Initializing environment and global constants
 *  - Registering core services (Request, Router, Kernel)
 *  - Loading application routes
 *  - Handling the main request lifecycle
 */
final class App
{
    /** @var string Absolute path to the application root directory */
    private string $baseDir;

    /** @var Router The main HTTP router instance */
    public Router $router;

    /** @var Request Current HTTP request */
    public Request $request;

    /** @var Kernel The application kernel responsible for execution and termination */
    public Kernel $kernel;

    /** @var ExceptionHandler Handles all unhandled exceptions */
    private ExceptionHandler $exceptions;

    /** @var callable|null A custom resolver for controller dependencies */
    private $resolver = null;

    /**
     * Create a new ivi.php Application instance.
     *
     * @param string         $baseDir   The root path of the application
     * @param callable|null  $resolver  Optional dependency resolver for controllers
     */
    public function __construct(string $baseDir, ?callable $resolver = null)
    {
        $this->baseDir  = rtrim($baseDir, DIRECTORY_SEPARATOR);
        $this->resolver = $resolver;

        // 1) Bootstrap environment, constants, and external services
        Loader::bootstrap($this->baseDir);

        // 2) Configure the global Debug Logger (BASELINE, comme avant)
        Logger::configure([
            'app_namespaces' => ['Ivi\\Controllers\\', 'App\\'],
            'trace_strategy' => 'balanced',
            'max_trace'      => 10,
            // on ne met PAS brand ici (on le fera après avoir lu la config)
        ]);

        // 3) Load app configuration (debug/env/brand/theme/error_detail)
        $appConfig = is_file($this->baseDir . '/config/app.php')
            ? require $this->baseDir . '/config/app.php'
            : [
                'debug' => (($_ENV['APP_DEBUG'] ?? '0') === '1'),
                'env'   => ($_ENV['APP_ENV'] ?? 'production'),
            ];

        $debug  = (bool)($appConfig['debug'] ?? (($_ENV['APP_DEBUG'] ?? '0') === '1'));
        $env    = (string)($appConfig['env']   ?? ($_ENV['APP_ENV'] ?? 'production'));
        $detail = (string)($appConfig['error_detail'] ?? ($_ENV['APP_ERROR_DETAIL'] ?? 'safe')); // none|safe|full

        // Branding (Ivi | Softadastra)
        $brand = (string)($appConfig['brand']      ?? ($_ENV['APP_BRAND']      ?? 'ivi'));
        $theme = (string)($appConfig['theme']      ?? ($_ENV['APP_THEME']      ?? 'light'));
        $logo  = (string)($appConfig['brand_logo'] ?? ($_ENV['APP_BRAND_LOGO'] ?? '/assets/logo/ivi.png'));

        // Map error_detail -> Logger opts
        $loggerOpts = match ($detail) {
            'none' => ['show_trace' => false, 'show_context' => false, 'verbosity' => 'minimal', 'max_trace' => 0],
            'safe' => ['show_trace' => true,  'show_context' => false, 'verbosity' => 'normal',  'max_trace' => 5],
            default => ['show_trace' => true, 'show_context' => true,  'verbosity' => 'normal',  'max_trace' => 10], // full
        };

        // 3bis) Re-configure le Logger avec brand/theme + detail (on force aussi brand_name/page_title)
        $brandName = ($brand === 'softadastra') ? 'Softadastra' : 'Ivi.php';
        $pageTitle = ($brand === 'softadastra') ? 'Softadastra Debug Console' : 'ivi.php Debug Console';

        Logger::configure(array_replace([
            'brand'       => $brand,
            'brand_logo'  => $logo,
            'brand_name'  => $brandName,
            'page_title'  => $pageTitle,
            'theme'       => $theme,
        ], $loggerOpts));

        // (Optionnel) — Brancher VarDumper sur le Logger (dump() → panneau stylé)
        if (class_exists(\Symfony\Component\VarDumper\VarDumper::class)) {
            \Symfony\Component\VarDumper\VarDumper::setHandler(function ($var): void {
                \Ivi\Core\Debug\Logger::dump('Dump', $var, [
                    'exit'       => false,   // ne pas couper l’exécution
                    'show_trace' => false,
                ]);
            });
        }

        // 4) Initialize core services (comme avant)
        $this->exceptions = new ExceptionHandler([
            'debug'        => $debug,
            'env'          => $env,
            'error_detail' => $detail,
        ]);
        $this->request = Request::fromGlobals();

        // Ajustements API (si /api)
        $uri = $this->request->path();
        if (\str_starts_with($uri, '/api')) {
            $_SERVER['HTTP_ACCEPT'] = ($_SERVER['HTTP_ACCEPT'] ?? '');
            if (!str_contains(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json')) {
                $_SERVER['HTTP_ACCEPT'] .= (($_SERVER['HTTP_ACCEPT'] ?? '') ? ',' : '') . 'application/json';
            }
            $_SERVER['HTTP_X_IVI_EXPECT'] = 'json';
        }

        // 5) Router & Kernel (comme avant)
        $this->router = new Router($this->resolver);
        $this->kernel = new Kernel($this->exceptions);

        $this->loadModules();

        // 6) Load application routes (comme avant)
        $this->registerRoutes();
    }

    /**
     * -----------------------------------------------------------------------------
     * Load and initialize all declared application modules.
     * -----------------------------------------------------------------------------
     *
     * This method dynamically loads all modules listed in `config/modules.php`
     * and performs a two-phase initialization:
     *
     * 1. **Registration Phase (`register()`)**
     *    - Merges module-specific configuration files.
     *    - Binds service providers or container dependencies.
     *
     * 2. **Boot Phase (`boot()`)**
     *    - Registers routes, views, and database migrations.
     *    - Integrates each module with the application router.
     *
     * ## Design Notes
     * - Each module is represented by a `Module.php` file that returns an instance
     *   implementing the `App\Modules\ModuleContract` interface.
     * - Modules are discovered based on the `config/modules.php` load order.
     * - Safe defaults: missing or invalid module files are silently ignored.
     * - Fully compatible with the `ModuleRegistry`, which orchestrates the lifecycle.
     *
     * ## Example Structure
     * ```
     * config/modules.php
     * modules/
     * ├── Market/Core/Module.php
     * ├── Market/Products/Module.php
     * └── Blog/Core/Module.php
     * ```
     *
     * @internal
     * @return void
     * @throws \RuntimeException If the module configuration file is missing.
     * @see App\Modules\ModuleContract
     * @see App\Modules\ModuleRegistry
     */
    private function loadModules(): void
    {
        $configFile  = $this->baseDir . '/config/modules.php';
        $modulesCfg  = is_file($configFile) ? require $configFile : ['modules' => []];
        $modulesList = $modulesCfg['modules'] ?? [];

        $registry = new ModuleRegistry();

        foreach ($modulesList as $slug) {
            // Try Module.php at the module root
            $moduleFileRoot = $this->baseDir . "/modules/{$slug}/Module.php";

            // Try Module.php inside Core/ (legacy format)
            $moduleFileCore = $this->baseDir . "/modules/{$slug}/Core/Module.php";

            $moduleFile = null;
            if (is_file($moduleFileRoot)) {
                $moduleFile = $moduleFileRoot;
            } elseif (is_file($moduleFileCore)) {
                $moduleFile = $moduleFileCore;
            }

            if ($moduleFile) {
                /** @var \App\Modules\ModuleContract $module */
                $module = require $moduleFile; // returns a module instance
                $registry->add($module);
            } else {
                // Tolerant behavior: warn if no Module.php is found
                echo "[WARN] Module '{$slug}' has no Module.php file.\n";
            }
        }

        // 1) Configuration & service binding
        $registry->registerAll();

        // 2) Routes, views, migrations (with router context)
        $registry->bootAll($this->router);
    }

    /**
     * Run the application lifecycle.
     * - Dispatches the request through the router
     * - Terminates the response via the Kernel
     */
    public function run(): void
    {
        $response = $this->kernel->handle($this->router, $this->request);
        $this->kernel->terminate($response);
    }

    /**
     * Register the application's routes.
     * Loads `config/routes.php` if present, otherwise defines fallback routes.
     */
    private function registerRoutes(): void
    {
        $routesFile = $this->baseDir . '/config/routes.php';

        if (is_file($routesFile)) {
            /** @var \Ivi\Router\Router $router */
            $router = $this->router;
            // $routesFile = $this->baseDir . '/config/routes.php';
            // \Ivi\Core\Debug\Logger::dump('Including routes file', [
            //     'routesFile' => $routesFile,
            //     'exists'     => is_file($routesFile),
            // ], ['exit' => false, 'show_trace' => false]);

            // $router = $this->router;
            require $routesFile;
            return;
        }

        // Default fallback routes
        $this->router->get('/', 'App\\Controllers\\Product\\HomeController@home');

        $this->router->get('/ping', fn() => new \Ivi\Http\Response('pong'));

        $this->router->get(
            '/api/ping',
            fn() =>
            new \Ivi\Http\JsonResponse(['ok' => true, 'framework' => 'ivi.php'])
        );
    }
}
