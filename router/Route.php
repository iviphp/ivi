<?php

declare(strict_types=1);

namespace Ivi\Router;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use InvalidArgumentException;
use RuntimeException;
use Ivi\Core\Debug\Callsite;

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

    public function __construct(string $path, Closure|array|string $action, array $methods = ['GET'])
    {
        $this->path   = ltrim(trim($path), '/');
        $this->action = $action;
        $this->methods = array_map('strtoupper', $methods);
        $this->compile();
    }

    public function patternMatches(string $path): bool
    {
        $raw = parse_url($path, PHP_URL_PATH) ?: '/';
        $url = '/' . ltrim($raw, '/');
        return (bool)preg_match($this->compiledPattern, $url);
    }

    // -----------------------------
    // Fluent configuration
    // -----------------------------

    public function methods(string ...$methods): self
    {
        $this->methods = array_map('strtoupper', $methods);
        return $this->compile();
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /** @param array<class-string|string> $middleware */
    public function middleware(array $middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    /** Constrain a param: ->where('id', '\d+') */
    public function where(string $param, string $regex): self
    {
        $this->wheres[$param] = $regex;
        return $this->compile();
    }

    /** Set default values for missing params */
    public function defaults(array $defaults): self
    {
        $this->defaults = $defaults + $this->defaults;
        return $this;
    }

    // -----------------------------
    // Matching & execution
    // -----------------------------

    public function matches(string $method, string $path): bool
    {
        $method = strtoupper($method);
        if (!in_array($method, $this->methods, true)) {
            return false;
        }

        // Toujours un slash de tête ; "/" reste "/"
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
     * Optionally accepts a minimal DI container callable: fn(string $class): object
     * to resolve controllers/middleware classes.
     */
    public function execute(
        ?callable $resolver = null,
        array $queryParams = [],
        array $bodyParams = [],
        ?\Ivi\Http\Request $request = null
    ): mixed {
        // construit la table des valeurs dispo pour injection
        $args = $this->resolveArguments($queryParams, $bodyParams);

        // Middleware (inchangé)
        foreach ($this->middleware as $mw) {
            $instance = is_string($mw) && $resolver ? $resolver($mw) : (is_string($mw) ? new $mw() : $mw);
            if (!method_exists($instance, 'handle')) {
                throw new RuntimeException("Middleware $mw must have a handle(\$params, \$next) method.");
            }
            $next = fn(array $p) => ($args = $p) && true;
            $instance->handle($args, $next);
        }

        // Closure
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

    private function resolveArguments(array $queryParams, array $bodyParams): array
    {
        // Priorité d’override: body > query > path (params extraits)
        // $this->params = path params déjà extraits dans matches()
        return $bodyParams + $queryParams + $this->params + $this->defaults;
    }

    private function invokeMethod(object $controller, string $method, array $args, ?\Ivi\Http\Request $request): mixed
    {
        if (!method_exists($controller, $method)) {
            throw new RuntimeException(get_class($controller) . "::$method not found.");
        }

        $ref = new \ReflectionMethod($controller, $method);

        // >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
        // Marque le vrai callsite (HomeController::home)
        Callsite::set([
            'class'  => get_class($controller),
            'method' => $method,
            'file'   => $ref->getFileName() ?: '',
            'line'   => (int)$ref->getStartLine(),
        ]);
        // <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<

        try {
            $ordered = $this->orderArgsForCallable($ref->getParameters(), $args, $request);
            return $controller->$method(...$ordered);
        } finally {
            Callsite::clear();
        }
    }

    private function invokeClosure(\Closure $closure, array $args, ?\Ivi\Http\Request $request): mixed
    {
        $ref = new \ReflectionFunction($closure);

        $fnFile = $ref->getFileName() ?: '';
        $fnLine = (int)$ref->getStartLine();

        // Heuristique : on n’écrase pas un callsite controller existant
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

    private function orderArgsForCallable(array $params, array $available, ?\Ivi\Http\Request $request): array
    {
        $ordered = [];
        foreach ($params as $param) {
            $name = $param->getName();

            // Injection Request
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

    private function coerceForParam(\ReflectionParameter $param, mixed $value): mixed
    {
        $type = $param->getType();

        // Null autorisé ?
        if ($value === null) {
            return null;
        }

        if ($type instanceof \ReflectionNamedType) {
            return $this->coerceNamedType($type, $value, $param->getName());
        }

        if ($type instanceof \ReflectionUnionType) {
            // si une des branches est "int", "float", "bool", "string", on essaie dans cet ordre
            foreach ($type->getTypes() as $branch) {
                if (!$branch instanceof \ReflectionNamedType) continue;
                $n = strtolower($branch->getName());
                if (in_array($n, ['int', 'float', 'bool', 'string'], true)) {
                    try {
                        return $this->coerceNamedType($branch, $value, $param->getName());
                    } catch (\Throwable) { /* essayer autre branche */
                    }
                }
            }
            // sinon, on laisse tel quel
            return $value;
        }

        return $value;
    }

    private function coerceNamedType(\ReflectionNamedType $type, mixed $value, string $name): mixed
    {
        $n = strtolower($type->getName());
        return match ($n) {
            'int'    => $this->filterInt($value, $name),
            'float'  => $this->filterFloat($value, $name),
            'bool'   => $this->filterBool($value, $name),
            'string' => (string)$value,
            default  => $value, // classes/array: on laisse tel quel
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
    public function getMethods(): array
    {
        return $this->methods;
    }
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
    public function getParams(): array
    {
        return $this->params;
    }

    // -----------------------------
    // Internals
    // -----------------------------

    private function compile(): self
    {
        // 0) Normalise le chemin (garde "/" si racine)
        $rawPath = '/' . trim($this->path, '/');

        // 1) Supporte {param} et {param:regex} (et option ?)
        //    → convertit en syntaxe native :param / :param?
        //    et stocke les regex inline dans $this->wheres
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

        // 2) Collecte des noms de paramètres en syntaxe native
        preg_match_all('#:([\w]+)\??#', $rawPath, $m);
        $this->paramNames = $m[1] ?? [];

        // 3) Construit le regex final à partir des :param / :param?
        $regex = preg_replace_callback(
            '#:([\w]+)(\?)?#',
            function ($matches) {
                $name     = $matches[1];
                $optional = (($matches[2] ?? '') === '?');
                $base     = $this->wheres[$name] ?? '[^/]+';

                $segment = "(?P<{$name}>{$base})";
                // ⬇️ FIX: ne pas ajouter un "/" ici car il est déjà dans le chemin source "/users/:id"
                return $optional ? "(?:/{$segment})?" : "{$segment}";
                // avant: return $optional ? "(?:/{$segment})?" : "/{$segment}";
            },
            $rawPath
        );
        // 4) Cas spécial racine
        $this->compiledPattern = ($regex === '/')
            ? '#^/$#'
            : '#^' . $regex . '$#';

        return $this;
    }


    private function sanitizePathValue(?string $value): ?string
    {
        if ($value === null) return null;
        // Keep raw value here; escaping is a view concern.
        // Still trim dangerous null bytes etc.
        return str_replace("\0", '', $value);
    }

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
