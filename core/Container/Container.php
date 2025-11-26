<?php

declare(strict_types=1);

namespace Ivi\Core\Container;

use ReflectionClass;
use ReflectionParameter;
use RuntimeException;

final class Container
{
    /** @var array<string, callable|string> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $singletons = [];

    /**
     * Bind a class or interface to a concrete implementation
     */
    public function bind(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Bind a singleton
     */
    public function singleton(string $abstract, callable|string $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
    }

    /**
     * Check if a binding or singleton exists
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->singletons[$abstract]);
    }

    /**
     * Get an instance from the container (alias to make)
     */
    public function get(string $abstract): mixed
    {
        return $this->make($abstract);
    }

    /**
     * Create or resolve an instance
     */
    public function make(string $abstract): mixed
    {
        // ----- Singleton
        if (isset($this->singletons[$abstract])) {
            if (is_object($this->singletons[$abstract])) {
                return $this->singletons[$abstract];
            }
            $resolved = $this->resolve($this->singletons[$abstract]);
            $this->singletons[$abstract] = $resolved;
            return $resolved;
        }

        // ----- Binding normal
        if (isset($this->bindings[$abstract])) {
            return $this->resolve($this->bindings[$abstract]);
        }

        // ----- Auto-wiring
        return $this->resolve($abstract);
    }

    /**
     * Resolve a concrete class or callable
     */
    private function resolve(string|callable $concrete): mixed
    {
        if (is_callable($concrete)) {
            return $concrete($this);
        }

        $reflection = new ReflectionClass($concrete);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Class [$concrete] is not instantiable.");
        }

        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new $concrete();
        }

        $params = $constructor->getParameters();
        $dependencies = array_map(
            fn(ReflectionParameter $param) => $this->resolveParameter($param),
            $params
        );

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolve a single parameter
     */
    private function resolveParameter(ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if ($type && !$type->isBuiltin()) {
            return $this->make($type->getName());
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new RuntimeException(
            "Cannot resolve parameter $" . $param->getName()
        );
    }
}
