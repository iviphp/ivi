<?php

declare(strict_types=1);

namespace Ivi\Core\Bootstrap;

use Ivi\Http\Request;
use Ivi\Router\Router;

final class App
{
    private string $baseDir;
    public Router $router;
    public Request $request;
    public Kernel $kernel;

    /** @var callable|null fn(string $class): object */
    private $resolver = null;

    public function __construct(string $baseDir, ?callable $resolver = null)
    {
        $this->baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR);
        $this->resolver = $resolver;

        // 1) Bootstrap env + constants + services externes
        Loader::bootstrap($this->baseDir);

        // 2) Core components
        $this->request = Request::fromGlobals();
        $this->router  = new Router($this->resolver);
        $this->kernel  = new Kernel();

        // 3) Register routes
        $this->registerRoutes();
    }

    public function run(): void
    {
        try {
            $response = $this->kernel->handle($this->router, $this->request);
            $this->kernel->terminate($response);
        } catch (\Throwable $e) {
            $this->kernel->handleException($e);
        }
    }

    private function registerRoutes(): void
    {
        // Si un fichier config/routes.php existe, on le charge
        $routesFile = $this->baseDir . '/config/routes.php';
        if (is_file($routesFile)) {
            /** @var \Ivi\Router\Router $router */
            $router = $this->router;
            require $routesFile;
            return;
        }

        // Sinon, routes par défaut
        $this->router->get('/', 'App\\Controllers\\Product\\HomeController@home');

        // ✅ Utiliser $this->router (et non $router)
        $this->router->get('/ping', function () {
            return new \Ivi\Http\Response('pong');
        });

        $this->router->get(
            '/api/ping',
            fn() =>
            new \Ivi\Http\JsonResponse(['ok' => true, 'framework' => 'ivi.php'])
        );
    }
}
