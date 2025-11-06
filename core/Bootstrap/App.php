<?php

declare(strict_types=1);

namespace Ivi\Core\Bootstrap;

use Ivi\Http\Request;
use Ivi\Router\Router;
use Ivi\Core\Debug\Logger;
use Ivi\Core\Exceptions\ExceptionHandler;

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

        // 2) Configure the global Debug Logger
        Logger::configure([
            'app_namespaces' => ['Ivi\\Controllers\\', 'App\\'],
            'trace_strategy' => 'balanced',
            'max_trace'      => 10,
        ]);

        // 3) Load app configuration (debug/env)
        $appConfig = is_file($this->baseDir . '/config/app.php')
            ? require $this->baseDir . '/config/app.php'
            : [
                'debug' => (($_ENV['APP_DEBUG'] ?? '0') === '1'),
                'env'   => ($_ENV['APP_ENV'] ?? 'production'),
            ];

        // 4) Initialize core services
        $this->exceptions = new ExceptionHandler($appConfig);
        $this->request = Request::fromGlobals();

        $uri = $this->request->path();
        if (\str_starts_with($uri, '/api')) {
            $_SERVER['HTTP_ACCEPT'] = ($_SERVER['HTTP_ACCEPT'] ?? '');
            if (!str_contains(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json')) {
                $_SERVER['HTTP_ACCEPT'] .= (($_SERVER['HTTP_ACCEPT'] ?? '') ? ',' : '') . 'application/json';
            }
            $_SERVER['HTTP_X_IVI_EXPECT'] = 'json';
        }

        $this->router     = new Router($this->resolver);
        $this->kernel     = new Kernel($this->exceptions);

        // 5) Load application routes
        $this->registerRoutes();
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
