<?php

declare(strict_types=1);

namespace Ivi\Core\Router;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use InvalidArgumentException;
use RuntimeException;
use Ivi\Core\Debug\Callsite;

/**
 * Class Route
 *
 * @package Ivi\Router
 *
 * @brief Declarative, regex-backed HTTP route with parameter binding, type coercion,
 *        middleware support, and flexible action invocation.
 *
 * The `Route` class represents a single HTTP route definition. It compiles a human-friendly
 * path pattern into a fast regular expression, extracts and sanitizes path parameters,
 * and then invokes the configured action (Closure, "Controller@method", or [Controller, method]).
 *
 * ### Key Features
 * - **Path patterns** with parameters and constraints:
 *   - Native syntax: `/users/:id`, `/posts/:slug?`
 *   - Curly syntax:  `/users/{id}`, `/files/{name:[a-z0-9_-]+}`, `/tags/{slug?}`
 *   - Optional segments via `?` (e.g., `:id?` or `{id?}`)
 * - **Parameter constraints** with `where('name','regex')` or inline `{name:regex}`
 * - **Defaults** for missing params via `defaults(['page' => 1])`
 * - **HTTP methods** filtering with `methods('GET','POST',...)`
 * - **Middleware** chain (`handle(array $params, callable $next)`) per route
 * - **Type coercion** for scalar controller parameters (int|float|bool|string)
 * - **Request injection**: parameters type-hinted as `\Ivi\Http\Request` are auto-injected
 * - **Callsite metadata** via `Ivi\Core\Debug\Callsite` for better error context
 *
 * ### Typical Usage
 * ```php
 * $route = new Route('/users/:id', [UserController::class, 'show'], ['GET']);
 * $route->where('id', '\d+')->name('users.show');
 *
 * if ($route->matches('GET', '/users/42')) {
 *     $response = $route->execute($resolver, $_GET, $_POST, $request);
 * }
 * ```
 *
 * ### Notes
 * - Matching supports both `:param` and `{param}` syntaxes interchangeably.
 * - Inline regex constraints using `{name:...}` are merged with `where()` rules.
 * - Query/body params override path params (body > query > path > defaults).
 * - Middleware instances may be class names resolved via `$resolver` or objects.
 *
 * @see \Ivi\Core\Debug\Callsite
 * @since 1.0.0
 */
final class Route
{
    /** @var string HTTP path pattern ex: "users/:id" */
    private string $path;

    /** @var string[] Allowed HTTP methods (e.g., ['GET','POST']) */
    private array $methods;

    /** @var Closure|array|string Action: Closure | [Controller, method] | "Controller@method" */
    private Closure|array|string $action;

    /** @var array<string,string> Custom regex by param name (via where()) */
    private array $wheres = [];

    /** @var array<string,mixed> Default values for params (via defaults()) */
    private array $defaults = [];

    /** @var string|null Route name (via name()) */
    private ?string $name = null;

    /** @var array<class-string|string> Middleware list (class names or string ids) */
    private array $middleware = [];

    /** @var array<string,string> Compiled param names in order */
    private array $paramNames = [];

    /** @var string Compiled regex pattern */
    private string $compiledPattern = '';

    /** @var array<string,mixed> Extracted params from last match */
    private array $params = [];

    /**
     * @param string                    $path    Route pattern (supports :param and {param[:regex][?]})
     * @param Closure|array|string      $action  Closure | [Controller, method] | "Controller@method"
     * @param array<int,string>         $methods Allowed HTTP verbs (defaults to ['GET'])
     */
    public function __construct(string $path, Closure|array|string $action, array $methods = ['GET'])
    {
        $this->path   = ltrim(trim($path), '/');
        $this->action = $action;
        $this->methods = array_map('strtoupper', $methods);
        $this->compile();
    }

    /**
     * Returns true if the given URL path matches the compiled pattern.
     */
    public function patternMatches(string $path): bool
    {
        $raw = parse_url($path, PHP_URL_PATH) ?: '/';
        $url = '/' . ltrim($raw, '/');
        return (bool)preg_match($this->compiledPattern, $url);
    }

    // -----------------------------
    // Fluent configuration
    // -----------------------------

    /**
     * Override allowed HTTP methods.
     */
    public function methods(string ...$methods): self
    {
        $this->methods = array_map('strtoupper', $methods);
        return $this->compile();
    }

    /**
     * Assign a name to the route for reverse lookups.
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Attach middleware list (class names or instances).
     *
     * @param array<class-string|string|object> $middleware
     */
    public function middleware(array $middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    /**
     * Constrain a parameter by regex; e.g. ->where('id', '\d+')
     */
    public function where(string $param, string $regex): self
    {
        $this->wheres[$param] = $regex;
        return $this->compile();
    }

    /**
     * Provide default values for missing parameters.
     *
     * @param array<string,mixed> $defaults
     */
    public function defaults(array $defaults): self
    {
        $this->defaults = $defaults + $this->defaults;
        return $this;
    }

    // -----------------------------
    // Matching & execution
    // -----------------------------

    /**
     * Checks if the route matches the given HTTP method and path.
     */
    public function matches(string $method, string $path): bool
    {
        $method = strtoupper($method);
        if (!in_array($method, $this->methods, true)) {
            return false;
        }

        // Always ensure a leading slash; "/" stays "/"
        $raw = parse_url($path, PHP_URL_PATH) ?: '/';
        $url = '/' . ltrim($raw, '/');

        if (!preg_match($this->compiledPattern, $url, $valueMatches)) {
            return false;
        }

        array_shift($valueMatches);
        $this->params = [];

        foreach ($this->paramNames as $i => $name) {
            $this->params[$name] = $this->sanitizePathValue($valueMatches[$i] ?? null);
        }

        foreach ($this->defaults as $k => $v) {
            if (!array_key_exists($k, $this->params)) {
                $this->params[$k] = $v;
            }
        }

        return true;
    }

    /**
     * Execute the route action.
     *
     * @param callable|null         $resolver    Minimal DI container: fn(string $class): object
     * @param array<string,mixed>   $queryParams Query string params
     * @param array<string,mixed>   $bodyParams  POST/JSON body params
     * @param \Ivi\Http\Request|null $request    Current request (auto-injected if type-hinted)
     */
    public function execute(
        ?callable $resolver = null,
        array $queryParams = [],
        array $bodyParams = [],
        ?\Ivi\Http\Request $request = null
    ): mixed {
        // Build the available argument table for injection
        $args = $this->resolveArguments($queryParams, $bodyParams);

        // Middleware chain
        foreach ($this->middleware as $mw) {
            $instance = is_string($mw) && $resolver ? $resolver($mw) : (is_string($mw) ? new $mw() : $mw);
            if (!method_exists($instance, 'handle')) {
                throw new RuntimeException("Middleware $mw must have a handle(\$params, \$next) method.");
            }
            $next = fn(array $p) => ($args = $p) && true;
            $instance->handle($args, $next);
        }

        // Closure action
        if ($this->action instanceof \Closure) {
            return $this->invokeClosure($this->action, $args, $request);
        }

        // "Controller@method"
        if (is_string($this->action) && str_contains($this->action, '@')) {
            [$class, $method] = explode('@', $this->action, 2);
            $controller = $this->resolveClass($class, $resolver);
            return $this->invokeMethod($controller, $method, $args, $request);
        }

        // [Controller, method]
        if (is_array($this->action) && count($this->action) === 2) {
            [$classOrObj, $method] = $this->action;
            $controller = is_string($classOrObj) ? $this->resolveClass($classOrObj, $resolver) : $classOrObj;
            return $this->invokeMethod($controller, $method, $args, $request);
        }

        throw new RuntimeException('Unsupported route action type.');
    }

    /**
     * Priority of values: body > query > path params > defaults.
     *
     * @param array<string,mixed> $queryParams
     * @param array<string,mixed> $bodyParams
     * @return array<string,mixed>
     */
    private function resolveArguments(array $queryParams, array $bodyParams): array
    {
        return $bodyParams + $queryParams + $this->params + $this->defaults;
    }

    /**
     * Invoke a controller method with ordered and coerced arguments.
     */
    private function invokeMethod(object $controller, string $method, array $args, ?\Ivi\Http\Request $request): mixed
    {
        if (!method_exists($controller, $method)) {
            throw new RuntimeException(get_class($controller) . "::$method not found.");
        }

        $ref = new \ReflectionMethod($controller, $method);

        // Mark real callsite (e.g., HomeController::home) for better error dumps
        Callsite::set([
            'class'  => get_class($controller),
            'method' => $method,
            'file'   => $ref->getFileName() ?: '',
            'line'   => (int)$ref->getStartLine(),
        ]);

        try {
            $ordered = $this->orderArgsForCallable($ref->getParameters(), $args, $request);
            return $controller->$method(...$ordered);
        } finally {
            Callsite::clear();
        }
    }

    /**
     * Invoke a closure with ordered and coerced arguments.
     */
    private function invokeClosure(\Closure $closure, array $args, ?\Ivi\Http\Request $request): mixed
    {
        $ref = new \ReflectionFunction($closure);

        $fnFile = $ref->getFileName() ?: '';
        $fnLine = (int)$ref->getStartLine();

        // Heuristic: don't overwrite an existing controller callsite
        if (\Ivi\Core\Debug\Callsite::get() === null) {
            Callsite::set([
                'class'  => '(closure)',
                'method' => $ref->getName() ?: '{closure}',
                'file'   => $fnFile,
                'line'   => $fnLine,
            ]);
        }

        try {
            $ordered = $this->orderArgsForCallable($ref->getParameters(), $args, $request);
            return $closure(...$ordered);
        } finally {
            Callsite::clear();
        }
    }

    /**
     * Build the final ordered argument list for a callable by name and type.
     *
     * - Injects \Ivi\Http\Request if a parameter is type-hinted as such
     * - Coerces basic scalar types (int, float, bool, string)
     *
     * @param array<int,\ReflectionParameter> $params
     * @param array<string,mixed>             $available
     * @return array<int,mixed>
     */
    private function orderArgsForCallable(array $params, array $available, ?\Ivi\Http\Request $request): array
    {
        $ordered = [];
        foreach ($params as $param) {
            $name = $param->getName();

            // Request injection
            $ptype = $param->getType();
            if ($ptype instanceof \ReflectionNamedType) {
                $tname = ltrim($ptype->getName(), '\\');
                if ($tname === \Ivi\Http\Request::class) {
                    $ordered[] = $request;
                    continue;
                }
            }

            $value = $available[$name] ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null);
            $ordered[] = $this->coerceForParam($param, $value);
        }
        return $ordered;
    }

    /**
     * Coerce a value to match a reflected parameter (supports union types).
     */
    private function coerceForParam(\ReflectionParameter $param, mixed $value): mixed
    {
        $type = $param->getType();

        // Allow null if value is absent
        if ($value === null) {
            return null;
        }

        if ($type instanceof \ReflectionNamedType) {
            return $this->coerceNamedType($type, $value, $param->getName());
        }

        if ($type instanceof \ReflectionUnionType) {
            // Try scalar branches first: int, float, bool, string
            foreach ($type->getTypes() as $branch) {
                if (!$branch instanceof \ReflectionNamedType) continue;
                $n = strtolower($branch->getName());
                if (in_array($n, ['int', 'float', 'bool', 'string'], true)) {
                    try {
                        return $this->coerceNamedType($branch, $value, $param->getName());
                    } catch (\Throwable) {
                        // try next branch
                    }
                }
            }
            // Otherwise, leave as-is
            return $value;
        }

        return $value;
    }

    /**
     * Coerce a value to a named scalar type (int|float|bool|string).
     */
    private function coerceNamedType(\ReflectionNamedType $type, mixed $value, string $name): mixed
    {
        $n = strtolower($type->getName());
        return match ($n) {
            'int'    => $this->filterInt($value, $name),
            'float'  => $this->filterFloat($value, $name),
            'bool'   => $this->filterBool($value, $name),
            'string' => (string)$value,
            default  => $value, // classes/array: leave as-is
        };
    }

    private function filterInt(mixed $v, string $name): int
    {
        if (filter_var($v, FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException("Parameter '$name' must be an integer.");
        }
        return (int)$v;
    }

    private function filterFloat(mixed $v, string $name): float
    {
        if (filter_var($v, FILTER_VALIDATE_FLOAT) === false) {
            throw new \InvalidArgumentException("Parameter '$name' must be a float.");
        }
        return (float)$v;
    }

    private function filterBool(mixed $v, string $name): bool
    {
        if (is_bool($v)) return $v;
        $norm = strtolower((string)$v);
        if (in_array($norm, ['1', 'true', 'on', 'yes'], true)) return true;
        if (in_array($norm, ['0', 'false', 'off', 'no'], true)) return false;
        throw new \InvalidArgumentException("Parameter '$name' must be a boolean.");
    }

    // -----------------------------
    // Accessors
    // -----------------------------

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /** @return string[] */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /** @return array<class-string|string> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /** @return array<string,mixed> */
    public function getParams(): array
    {
        return $this->params;
    }

    // -----------------------------
    // Internals
    // -----------------------------

    /**
     * Compile the human-readable path into a regex pattern and extract param names.
     *
     * Supports:
     * - Native tokens: `:id`, `:slug?`
     * - Curly tokens:  `{id}`, `{slug?}`, `{name:[a-z]+}`
     */
    private function compile(): self
    {
        // 0) Normalize path (keep "/" for root)
        $rawPath = '/' . trim($this->path, '/');

        // 1) Support {param} and {param:regex} (with optional ?)
        //    → convert to native :param / :param?
        //    and store inline regex in $this->wheres
        $rawPath = preg_replace_callback(
            '/\{(\w+)(?::([^}]+))?(\?)?\}/',
            function ($m) {
                $name     = $m[1];
                $inlineRx = $m[2] ?? null;
                $optional = (($m[3] ?? '') === '?');

                if ($inlineRx) {
                    $this->wheres[$name] = $inlineRx;
                }
                return $optional ? ":{$name}?" : ":{$name}";
            },
            $rawPath
        );

        // 2) Collect param names from native syntax
        preg_match_all('#:([\w]+)\??#', $rawPath, $m);
        $this->paramNames = $m[1] ?? [];

        // 3) Build final regex from :param / :param?
        $regex = preg_replace_callback(
            '#:([\w]+)(\?)?#',
            function ($matches) {
                $name     = $matches[1];
                $optional = (($matches[2] ?? '') === '?');
                $base     = $this->wheres[$name] ?? '[^/]+';

                $segment = "(?P<{$name}>{$base})";
                // Do not add an extra "/" here; it's already present in the source path
                return $optional ? "(?:/{$segment})?" : "{$segment}";
            },
            $rawPath
        );

        // 4) Root special-case
        $this->compiledPattern = ($regex === '/')
            ? '#^/$#'
            : '#^' . $regex . '$#';

        return $this;
    }

    /**
     * Minimal sanitization of extracted path values.
     */
    private function sanitizePathValue(?string $value): ?string
    {
        if ($value === null) return null;
        // Keep raw value; escaping is a view concern. Remove null bytes only.
        return str_replace("\0", '', $value);
    }

    /**
     * Resolve controller class via resolver or instantiate directly.
     */
    private function resolveClass(string $class, ?callable $resolver): object
    {
        if ($resolver) {
            return $resolver($class);
        }
        if (!class_exists($class)) {
            throw new RuntimeException("Controller class '$class' not found.");
        }
        return new $class();
    }

    /**
     * (Legacy) Scalar coercion helper. Kept for backward compatibility.
     *
     * Prefer the new reflection-driven `coerceForParam()` path.
     */
    private function coerceType(mixed $value, string $type, string $name): mixed
    {
        if ($value === null) {
            return null;
        }

        switch (strtolower($type)) {
            case 'int':
            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    throw new InvalidArgumentException("Parameter '$name' must be an integer.");
                }
                return (int)$value;

            case 'float':
            case 'double':
                if (filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
                    throw new InvalidArgumentException("Parameter '$name' must be a float.");
                }
                return (float)$value;

            case 'bool':
            case 'boolean':
                if (is_bool($value)) return $value;
                $normalized = strtolower((string)$value);
                if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) return true;
                if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) return false;
                throw new InvalidArgumentException("Parameter '$name' must be a boolean.");

            case 'string':
                return (string)$value;

            default:
                // For classes, arrays, etc. leave as-is
                return $value;
        }
    }
}
