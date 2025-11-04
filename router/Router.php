<?php

declare(strict_types=1);

namespace Ivi\Router;

use Ivi\Http\Request;
use Ivi\Http\Exceptions\NotFoundHttpException;
use Ivi\Http\Exceptions\MethodNotAllowedHttpException;
use RuntimeException;

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
        $method = $request->method();
        $path   = $request->path();

        if (!isset($this->routes[$method])) {
            // méthode HTTP inconnue de notre table → on laisse Kernel gérer (500)
            throw new RuntimeException("HTTP method not supported: $method");
        }

        // 1) Essaye de matcher sur la méthode demandée
        foreach ($this->routes[$method] as $route) {
            if ($route->matches($method, $path)) {
                return $route->execute(
                    resolver: $this->resolver,
                    queryParams: $request->query(),
                    bodyParams: $this->parseBody($request),
                    request: $request
                );
            }
        }

        // 2) Si pas trouvé: vérifier si le chemin existe sur d'autres méthodes → 405
        $allowed = $this->allowedMethodsForPath($path, $method);
        if (!empty($allowed)) {
            throw new MethodNotAllowedHttpException($allowed);
        }

        // 3) Sinon → 404
        throw new NotFoundHttpException('Route not found.');
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

    /**
     * Retourne la liste des méthodes autorisées pour ce chemin.
     * @return string[] ex: ['GET','POST']
     */
    private function allowedMethodsForPath(string $path, string $currentMethod): array
    {
        $allowed = [];
        foreach ($this->routes as $m => $list) {
            if ($m === $currentMethod) continue;
            foreach ($list as $route) {
                // on utilise matches() en “ignorant” la méthode en dur: Route::matches vérifie pattern + méthode
                // donc on check le pattern en le spoofant: si le pattern du route matche le path indépendamment,
                // il faut que Route::matches dispose d’un test du pattern séparé.
                //
                // Si ton Route::matches() est strict (méthode + pattern), on peut ajouter un helper sur Route:
                //   - $route->patternMatches($path) qui ignore la méthode.
                //
                // Si tu n'as pas ce helper, une solution simple:
                if ($route->matches($m, $path)) {
                    $allowed[] = $m;
                }
            }
        }

        // déduplique et tri
        $allowed = array_values(array_unique($allowed));
        sort($allowed);
        return $allowed;
    }
}
