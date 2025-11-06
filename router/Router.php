<?php

declare(strict_types=1);

namespace Ivi\Router;

use Ivi\Http\Request;
use Ivi\Http\Exceptions\NotFoundHttpException;
use Ivi\Http\Exceptions\MethodNotAllowedHttpException;
use RuntimeException;
use Ivi\Core\Debug\Callsite;

final class Router
{
    /** @var array<string, Route[]> */
    private array $routes = [
        'GET'     => [],
        'POST'    => [],
        'PUT'     => [],
        'PATCH'   => [],
        'DELETE'  => [],
        'OPTIONS' => [],
    ];

    /** @var callable|null fn(string $class): object */
    private $resolver = null;

    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver;
    }

    public function get(string $path, \Closure|array|string $action): Route
    {
        return $this->add(['GET'],    $path, $action);
    }
    public function post(string $path, \Closure|array|string $action): Route
    {
        return $this->add(['POST'],   $path, $action);
    }
    public function put(string $path, \Closure|array|string $action): Route
    {
        return $this->add(['PUT'],    $path, $action);
    }
    public function patch(string $path, \Closure|array|string $action): Route
    {
        return $this->add(['PATCH'],  $path, $action);
    }
    public function delete(string $path, \Closure|array|string $action): Route
    {
        return $this->add(['DELETE'], $path, $action);
    }

    public function any(string $path, \Closure|array|string $action): Route
    {
        return $this->add(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $path, $action);
    }

    /** @param string[] $methods */
    private function add(array $methods, string $path, \Closure|array|string $action): Route
    {
        $route = new Route($path, $action, $methods);
        foreach ($methods as $m) {
            $this->routes[$m][] = $route;
        }
        return $route;
    }

    public function dispatch(Request $request): mixed
    {
        $method = strtoupper($request->method());
        $path   = $request->path();

        // HEAD → tenter GET si pas de handler HEAD explicite
        $tryMethods = ($method === 'HEAD') ? ['HEAD', 'GET'] : [$method];

        foreach ($tryMethods as $m) {
            if (!isset($this->routes[$m])) continue;

            foreach ($this->routes[$m] as $route) {
                if ($route->matches($m, $path)) {
                    try {
                        return $route->execute(
                            resolver: $this->resolver,
                            queryParams: $request->query(),
                            bodyParams: $this->parseBody($request),
                            request: $request
                        );
                    } finally {
                        \Ivi\Core\Debug\Callsite::clear();
                    }
                }
            }
        }

        // 405 si le pattern existe avec d’autres méthodes
        $allowed = $this->allowedMethodsForPath($path, $method);
        if (!empty($allowed)) {
            throw new \Ivi\Http\Exceptions\MethodNotAllowedHttpException($allowed);
        }

        // 🔎 DEBUG context avant 404 — (retirables ensuite)
        \Ivi\Core\Debug\Logger::dump('Router debug', [
            'method'   => $method,
            'path'     => $path,
            'routes'   => array_map(
                fn($kv) => [$kv[0], array_map(fn($r) => $r->getPath(), $kv[1])],
                array_map(null, array_keys($this->routes), array_values($this->routes))
            ),
        ], ['exit' => false, 'show_trace' => false]);

        throw new \Ivi\Http\Exceptions\NotFoundHttpException('Route not found.');
    }

    private function allowedMethodsForPath(string $path, string $currentMethod): array
    {
        $allowed = [];
        foreach ($this->routes as $m => $list) {
            foreach ($list as $route) {
                if ($route->patternMatches($path)) {
                    foreach ($route->getMethods() as $rm) {
                        if ($rm !== $currentMethod) $allowed[] = $rm;
                    }
                }
            }
        }
        $allowed = array_values(array_unique($allowed));
        sort($allowed);
        return $allowed;
    }


    private function parseBody(Request $request): array
    {
        $contentType = strtolower($request->header('content-type', ''));

        if (str_contains($contentType, 'application/json')) {
            return $request->json();
        }
        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            return $request->post();
        }
        // TODO: multipart/form-data -> $request->files()
        return [];
    }
}
