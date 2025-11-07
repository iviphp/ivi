<?php

declare(strict_types=1);

namespace Ivi\Core\Router;

use Ivi\Http\Request;

/**
 * Class Router
 *
 * @package Ivi\Router
 *
 * @brief Minimal, fast HTTP router for Ivi.php with method dispatching, pattern
 *        matching, optional DI resolution, and graceful 404/405 handling.
 *
 * The `Router` coordinates one or more {@see Route} definitions and dispatches
 * incoming {@see Request} objects to the matching route/action. It supports:
 *
 * - **HTTP method maps**: GET/POST/PUT/PATCH/DELETE/OPTIONS (+ `any()` helper)
 * - **Path pattern matching** via {@see Route::matches()}
 * - **HEAD fallback**: attempts GET when HEAD is requested without a dedicated handler
 * - **Minimal DI**: optional `$resolver` callable to instantiate controllers/middleware
 * - **405 Method Not Allowed**: returned when path matches but method is not allowed
 * - **404 Not Found**: thrown when no route matches the requested path
 * - **Debug context**: optional dump before 404 to aid troubleshooting (removable)
 *
 * ### Typical Usage
 * ```php
 * $router = new Router(fn (string $class) => new $class());
 * $router->get('/users/:id', [UserController::class, 'show'])
 *        ->where('id', '\d+');
 *
 * // In Kernel:
 * $response = $router->dispatch($request);
 * ```
 *
 * ### Design Notes
 * - Body parsing is delegated to `parseBody()` with basic JSON/x-www-form-urlencoded support.
 * - Query params, body params, and the Request instance are passed to `Route::execute()`.
 * - When no method matches but the path does, the router computes and returns `405` with allowed methods.
 * - The router clears {@see \Ivi\Core\Debug\Callsite} after each dispatch attempt for clean error frames.
 *
 * @see Route
 * @see \Ivi\Http\Exceptions\NotFoundHttpException
 * @see \Ivi\Http\Exceptions\MethodNotAllowedHttpException
 * @since 1.0.0
 */
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

    /** @var callable|null fn(string $class): object Minimal DI resolver */
    private $resolver = null;

    /**
     * @param callable|null $resolver Minimal DI container: fn(string $class): object
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver;
    }

    /**
     * Register a GET route.
     */
    public function get(string $path, \Closure|array|string $action): Route
    {
        return $this->add(['GET'],    $path, $action);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, \Closure|array|string $action): Route
    {
        return $this->add(['POST'],   $path, $action);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $path, \Closure|array|string $action): Route
    {
        return $this->add(['PUT'],    $path, $action);
    }

    /**
     * Register a PATCH route.
     */
    public function patch(string $path, \Closure|array|string $action): Route
    {
        return $this->add(['PATCH'],  $path, $action);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $path, \Closure|array|string $action): Route
    {
        return $this->add(['DELETE'], $path, $action);
    }

    /**
     * Register a route that responds to all standard methods.
     */
    public function any(string $path, \Closure|array|string $action): Route
    {
        return $this->add(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $path, $action);
    }

    /**
     * Low-level route registration.
     *
     * @param string[]                   $methods
     * @param string                     $path
     * @param \Closure|array|string      $action
     */
    private function add(array $methods, string $path, \Closure|array|string $action): Route
    {
        $route = new Route($path, $action, $methods);
        foreach ($methods as $m) {
            $this->routes[$m][] = $route;
        }
        return $route;
    }

    /**
     * Dispatch the given HTTP request to the first matching route.
     *
     * - Tries HEAD → GET fallback if needed.
     * - Injects query/body params and the Request instance into the route action.
     * - Clears the debug callsite after execution to keep stack traces clean.
     *
     * @throws \Ivi\Http\Exceptions\NotFoundHttpException
     * @throws \Ivi\Http\Exceptions\MethodNotAllowedHttpException
     */
    public function dispatch(Request $request): mixed
    {
        $method = strtoupper($request->method());
        $path   = $request->path();

        // HEAD → try GET if there is no explicit HEAD handler
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

        // 405 if the pattern exists with other methods
        $allowed = $this->allowedMethodsForPath($path, $method);
        if (!empty($allowed)) {
            throw new \Ivi\Http\Exceptions\MethodNotAllowedHttpException($allowed);
        }

        // 🔎 Optional debug context before 404 (safe to remove in production)
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

    /**
     * Compute the list of allowed HTTP methods for a given path pattern.
     *
     * @return string[] Sorted unique methods other than the current one
     */
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

    /**
     * Best-effort body parsing.
     * - `application/json` → `$request->json()`
     * - `application/x-www-form-urlencoded` → `$request->post()`
     * - TODO: multipart/form-data (files)
     *
     * @return array<string,mixed>
     */
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
