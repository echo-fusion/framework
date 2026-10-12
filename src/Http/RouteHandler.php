<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Http;

use Closure;
use EchoFusion\Contracts\RouteManager\RouterInterface;
use EchoFusion\Framework\Exceptions\ConfigurationException;
use EchoFusion\Framework\Exceptions\HttpException;
use EchoFusion\MiddlewareManager\LazyMiddleware;
use EchoFusion\MiddlewareManager\MiddlewarePipeline;
use EchoFusion\RouteManager\Exceptions\MethodNotAllowedException;
use EchoFusion\RouteManager\Exceptions\RouteNotFoundException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function array_values;
use function implode;
use function is_subclass_of;
use function sprintf;

/**
 * The end of the global middleware pipeline: finds the route, runs the middleware of that route and calls its action.
 *
 * The request reaching the action (and the route's middleware) carries these attributes: `route` (the route
 * definition), `route.params` (all placeholder values) and one attribute per placeholder.
 */
final class RouteHandler implements RequestHandlerInterface
{
    /**
     * @param array<string, class-string> $middlewareAliases name used in a route => middleware class, e.g. `'auth' => AuthenticateMiddleware::class`
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly ContainerInterface $container,
        private readonly ActionInvoker $invoker,
        private readonly ResponseConverter $converter,
        private readonly array $middlewareAliases = [],
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $match = $this->router->dispatch($request);
        } catch (MethodNotAllowedException $e) {
            throw new HttpException(405, '', ['Allow' => implode(', ', $e->getAllowedMethods())], $e);
        } catch (RouteNotFoundException $e) {
            throw new HttpException(404, '', [], $e);
        }

        $route = $match->getRoute();
        $params = $match->getParams();

        $request = $request->withAttribute('route', $route)->withAttribute('route.params', $params);
        foreach ($params as $name => $value) {
            $request = $request->withAttribute((string) $name, $value);
        }

        $action = new class($route->getAction(), $params, $this->invoker, $this->converter) implements RequestHandlerInterface {
            /**
             * @param array<array-key, mixed>|Closure $action
             * @param array<string, string> $params
             */
            public function __construct(
                private readonly array|Closure $action,
                private readonly array $params,
                private readonly ActionInvoker $invoker,
                private readonly ResponseConverter $converter,
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->converter->convert($this->invoker->invoke($this->action, $request, $this->params));
            }
        };

        $middlewares = $route->getMiddlewares();
        if ($middlewares === []) {
            return $action->handle($request);
        }

        $pipeline = new MiddlewarePipeline();
        foreach (array_values($middlewares) as $name) {
            $pipeline->add(new LazyMiddleware($this->container, $this->resolveMiddleware($name)));
        }

        return $pipeline->process($request, $action);
    }

    /**
     * @return class-string<MiddlewareInterface>
     */
    private function resolveMiddleware(string $nameOrClass): string
    {
        $class = $this->middlewareAliases[$nameOrClass] ?? $nameOrClass;

        if (!is_subclass_of($class, MiddlewareInterface::class)) {
            throw new ConfigurationException(sprintf(
                'The route middleware "%s" is neither a class implementing %s nor a name listed in middleware.route.',
                $nameOrClass,
                MiddlewareInterface::class,
            ));
        }

        return $class;
    }
}
