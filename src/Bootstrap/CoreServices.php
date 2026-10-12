<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Bootstrap;

use EchoFusion\Contracts\ConfigManager\ConfigInterface;
use EchoFusion\Contracts\EventManager\EventManagerInterface;
use EchoFusion\Contracts\MiddlewareManager\MiddlewareManagerInterface;
use EchoFusion\Contracts\ModuleManager\ModuleManagerInterface;
use EchoFusion\Contracts\PluginManager\PluginManagerInterface;
use EchoFusion\Contracts\RouteManager\RouteMatcherInterface;
use EchoFusion\Contracts\RouteManager\RouteRegistrarInterface;
use EchoFusion\Contracts\RouteManager\RouterInterface;
use EchoFusion\Contracts\ServiceManager\ServiceManagerInterface;
use EchoFusion\Contracts\ServiceManager\SettableContainerInterface;
use EchoFusion\Contracts\SessionManager\SessionInterface;
use EchoFusion\Contracts\TemplateManager\TemplateManagerInterface;
use EchoFusion\EventManager\EventManager;
use EchoFusion\Framework\Application;
use EchoFusion\Framework\Http\ActionInvoker;
use EchoFusion\Framework\Http\ErrorHandler;
use EchoFusion\Framework\Http\ErrorHandlerInterface;
use EchoFusion\Framework\Http\ResponseConverter;
use EchoFusion\Framework\Http\ResponseEmitter;
use EchoFusion\Framework\Http\RouteHandler;
use EchoFusion\MiddlewareManager\MiddlewareManager;
use EchoFusion\MiddlewareManager\MiddlewarePipeline;
use EchoFusion\ModuleManager\ModuleManager;
use EchoFusion\PluginManager\PluginManager;
use EchoFusion\RouteManager\RouteMatcher;
use EchoFusion\RouteManager\Router;
use EchoFusion\SessionManager\NativeSession;
use EchoFusion\SessionManager\SessionOptions;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use function array_values;
use function is_array;
use function is_int;
use function is_string;

/**
 * The services the framework itself provides: everything that has no service provider of its own.
 *
 * Packages that do have a provider (templates, database, auth) are registered through it instead; see {@see Application}.
 * Every service can be replaced by binding another one under the same id in a provider.
 */
final class CoreServices
{
    public function __construct(
        private readonly Application $application,
        private readonly ConfigInterface $config,
        private readonly ModuleManager $modules,
    ) {
    }

    public function register(SettableContainerInterface $container): void
    {
        $application = $this->application;
        $config = $this->config;
        $modules = $this->modules;

        // the application, its configuration and the container itself
        $container->set(Application::class, static fn (): Application => $application, true);
        $container->set(ConfigInterface::class, static fn (): ConfigInterface => $config, true);
        $container->set(ContainerInterface::class, static fn () => $container, true);
        $container->set(SettableContainerInterface::class, static fn () => $container, true);
        $container->set(ServiceManagerInterface::class, static fn () => $container, true);
        $container->set(ModuleManagerInterface::class, static fn (): ModuleManagerInterface => $modules, true);

        // PSR-17 factories (one object implements them all)
        $container->set(HttpFactory::class, static fn (): HttpFactory => new HttpFactory(), true);
        foreach ([ResponseFactoryInterface::class, ServerRequestFactoryInterface::class, StreamFactoryInterface::class, UploadedFileFactoryInterface::class, UriFactoryInterface::class] as $factory) {
            $container->set($factory, static fn (ContainerInterface $c) => $c->get(HttpFactory::class), true);
        }

        // events: our manager is both the PSR-14 dispatcher and its listener provider
        $container->set(EventManagerInterface::class, static fn (): EventManager => new EventManager(), true);
        $container->set(EventDispatcherInterface::class, static fn (ContainerInterface $c) => $c->get(EventManagerInterface::class), true);
        $container->set(ListenerProviderInterface::class, static fn (ContainerInterface $c) => $c->get(EventManagerInterface::class), true);

        // plugins
        $container->set(PluginManagerInterface::class, static function () use ($config, $container): PluginManagerInterface {
            $plugins = $config->get('app.plugins', []);

            /** @var list<class-string> $list */
            $list = is_array($plugins) ? array_values($plugins) : [];

            return new PluginManager($container, $list);
        }, true);

        // session
        $container->set(SessionInterface::class, static function () use ($config): SessionInterface {
            $session = $config->get('session', []);
            $session = is_array($session) ? $session : [];
            $name = $session['name'] ?? null;
            $savePath = $session['save_path'] ?? null;
            $sameSite = $session['same_site'] ?? 'Lax';

            return new NativeSession(new SessionOptions(
                name: is_string($name) && $name !== '' ? $name : null,
                lifetime: is_int($session['lifetime'] ?? null) ? $session['lifetime'] : 0,
                path: is_string($session['path'] ?? null) ? $session['path'] : '/',
                domain: is_string($session['domain'] ?? null) ? $session['domain'] : '',
                secure: (bool) ($session['secure'] ?? true),
                httpOnly: (bool) ($session['http_only'] ?? true),
                sameSite: is_string($sameSite) ? $sameSite : 'Lax',
                savePath: is_string($savePath) && $savePath !== '' ? $savePath : null,
            ));
        }, true);

        // routing
        $container->set(RouteMatcherInterface::class, static fn (): RouteMatcher => new RouteMatcher(), true);
        $container->set(RouterInterface::class, static fn (ContainerInterface $c): Router => new Router($c->get(RouteMatcherInterface::class)), true);
        $container->set(RouteRegistrarInterface::class, static fn (ContainerInterface $c) => $c->get(RouterInterface::class), true);

        // HTTP handling
        $container->set(ActionInvoker::class, static fn (ContainerInterface $c): ActionInvoker => new ActionInvoker($c), true);
        $container->set(ResponseConverter::class, static fn (): ResponseConverter => new ResponseConverter(), true);
        $container->set(ResponseEmitter::class, static fn (): ResponseEmitter => new ResponseEmitter(), true);

        $container->set(MiddlewareManagerInterface::class, static function (ContainerInterface $c) use ($config): MiddlewareManagerInterface {
            $core = $config->get('middleware.core', []);
            $aliases = $config->get('middleware.route', []);

            /** @var list<class-string> $coreList */
            $coreList = is_array($core) ? array_values($core) : [];
            /** @var list<class-string> $routeList */
            $routeList = is_array($aliases) ? array_values($aliases) : [];

            return new MiddlewareManager($c, new MiddlewarePipeline(), $coreList, $routeList);
        }, true);

        $container->set(RouteHandler::class, static function (ContainerInterface $c) use ($config): RouteHandler {
            $aliases = $config->get('middleware.route', []);

            /** @var array<string, class-string> $map */
            $map = is_array($aliases) ? $aliases : [];

            return new RouteHandler($c->get(RouterInterface::class), $c, $c->get(ActionInvoker::class), $c->get(ResponseConverter::class), $map);
        }, true);

        $container->set(ErrorHandlerInterface::class, static function (ContainerInterface $c) use ($config): ErrorHandlerInterface {
            return new ErrorHandler(
                (bool) $config->get('app.debug', false),
                $c->has(TemplateManagerInterface::class) ? $c->get(TemplateManagerInterface::class) : null,
                $c->has(LoggerInterface::class) ? $c->get(LoggerInterface::class) : null,
            );
        }, true);
    }
}
