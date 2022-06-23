<?php

namespace Dmpty\Container;

use Closure;
use ReflectionClass;

class Container
{
    protected static ?Container $instance = null;

    const INJECT_IGNORE = [
        'string',
        'int',
        'float',
        'bool',
        'array',
    ];

    protected array $binds = [];

    protected array $instances = [];

    public function bind(string $abstract, $concrete)
    {
        if ($concrete instanceof Closure) {
            $this->binds[$abstract] = $concrete;
        } else {
            $this->instances[$abstract] = $concrete;
        }
    }

    public function singleton(string $abstract, $concrete)
    {
        if ($concrete instanceof Closure) {
            $this->instances[$abstract] = $concrete($this);
        } else {
            $this->instances[$abstract] = $concrete;
        }
    }

    public function has(string $abstract): bool
    {
        return isset($this->binds[$abstract]) || isset($this->instances[$abstract]);
    }

    public function make(string $abstract, ...$args)
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        if (isset($this->binds[$abstract])) {
            return $this->binds[$abstract]($this, ...$args);
        }
        if (class_exists($abstract)) {
            return $this->makeClass($abstract, ...$args);
        }
        return null;
    }

    private function makeClass(string $abstract, ...$args)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $reflector = new ReflectionClass($abstract);
        $constructor = $reflector->getConstructor();
        if ($constructor === null) {
            return new $abstract;
        }
        $parameters = $constructor->getParameters();
        $paramsInjecting = [];
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if ($type === null) {
                break;
            }
            $typeName = $type->getName();
            if (in_array($typeName, static::INJECT_IGNORE)) {
                break;
            }
            $instance = $this->make($typeName);
            if ($instance === null) {
                break;
            }
            $paramsInjecting[] = $instance;
        }
        $newArgs = array_merge($paramsInjecting, $args);
        return new $abstract(...$newArgs);
    }

    private function __construct()
    {
        //
    }

    private function __clone()
    {
        //
    }

    public static function getInstance(): Container
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }
}
