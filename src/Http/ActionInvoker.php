<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Http;

use Closure;
use EchoFusion\Framework\Exceptions\HttpException;
use EchoFusion\Framework\Exceptions\InvalidActionException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;
use function array_key_exists;
use function class_exists;
use function count;
use function ctype_digit;
use function filter_var;
use function is_numeric;
use function is_string;
use function ltrim;
use function method_exists;
use function sprintf;
use function strtolower;

/**
 * Calls a route's action and works out its arguments from the signature:
 *
 * - a parameter named like a route placeholder gets its value, converted to `int`, `float`, `bool` or `string`
 *   according to the parameter's type (`/post/{id}` with `int $id` gives an int);
 * - a parameter typed `ServerRequestInterface` gets the request;
 * - any other class-typed parameter is taken from the container (method injection);
 * - otherwise the default value is used.
 *
 * An action is a closure, `[ControllerClass::class, 'method']` (the controller is built by the container), or
 * the name of a class with `__invoke()`.
 */
final class ActionInvoker
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * @param array<array-key, mixed>|Closure|string $action
     * @param array<string, string> $params route placeholder values
     *
     * @throws InvalidActionException when the action cannot be called or an argument cannot be provided
     * @throws HttpException 404 when a placeholder value cannot be converted to the type the action asks for
     */
    public function invoke(array|Closure|string $action, ServerRequestInterface $request, array $params): mixed
    {
        if ($action instanceof Closure) {
            $function = new ReflectionFunction($action);

            return $action(...$this->arguments($function, $request, $params));
        }

        [$class, $method] = $this->target($action);

        if (!class_exists($class) || !method_exists($class, $method)) {
            throw new InvalidActionException(sprintf('The action %s::%s() does not exist.', $class, $method));
        }

        $controller = $this->container->get($class);
        $reflection = new ReflectionMethod($class, $method);
        if (!$reflection->isPublic()) {
            throw new InvalidActionException(sprintf('The action %s::%s() must be public.', $class, $method));
        }

        return $reflection->invokeArgs($controller, $this->arguments($reflection, $request, $params));
    }

    /**
     * @param array<array-key, mixed>|string $action
     *
     * @return array{0: string, 1: string}
     */
    private function target(array|string $action): array
    {
        if (is_string($action)) {
            return [$action, '__invoke'];
        }

        if (count($action) === 2 && isset($action[0], $action[1]) && is_string($action[0]) && is_string($action[1])) {
            return [$action[0], $action[1]];
        }

        throw new InvalidActionException('An action must be a closure, [ControllerClass::class, \'method\'] or the name of an invokable class.');
    }

    /**
     * @param array<string, string> $params
     *
     * @return list<mixed>
     */
    private function arguments(ReflectionFunctionAbstract $function, ServerRequestInterface $request, array $params): array
    {
        $arguments = [];

        foreach ($function->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;
            $class = $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;

            if (array_key_exists($name, $params)) {
                $arguments[] = $this->convert($params[$name], $typeName, $name);
            } elseif ($class !== null && $request instanceof $class) {
                $arguments[] = $request;
            } elseif ($class !== null && $this->container->has($class)) {
                $arguments[] = $this->container->get($class);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull() && $type !== null) {
                $arguments[] = null;
            } else {
                throw new InvalidActionException(sprintf('Cannot provide the argument $%s of %s: %s', $name, $this->describe($function), $this->why($parameter)));
            }
        }

        return $arguments;
    }

    private function convert(string $value, ?string $type, string $name): mixed
    {
        switch ($type) {
            case 'int':
                if (!ctype_digit(ltrim($value, '-')) || $value === '' || $value === '-') {
                    throw new HttpException(404, sprintf('"%s" is not a valid integer for {%s}.', $value, $name));
                }

                return (int) $value;
            case 'float':
                if (!is_numeric($value)) {
                    throw new HttpException(404, sprintf('"%s" is not a valid number for {%s}.', $value, $name));
                }

                return (float) $value;
            case 'bool':
                $bool = filter_var(strtolower($value), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($bool === null) {
                    throw new HttpException(404, sprintf('"%s" is not a valid boolean for {%s}.', $value, $name));
                }

                return $bool;
            default:
                return $value;
        }
    }

    private function describe(ReflectionFunctionAbstract $function): string
    {
        return $function instanceof ReflectionMethod
            ? sprintf('%s::%s()', $function->class, $function->getName())
            : 'the route closure';
    }

    private function why(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType && !$type->isBuiltin()
            ? sprintf('the container has no "%s".', $type->getName())
            : 'it has no route placeholder with that name and no default value.';
    }
}
