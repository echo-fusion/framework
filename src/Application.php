<?php

declare(strict_types=1);

namespace EchoFusion\Framework;

use Closure;
use Dotenv\Dotenv;
use EchoFusion\AuthManager\Providers\AuthManagerProvider;
use EchoFusion\ConfigManager\Config;
use EchoFusion\Contracts\ConfigManager\ConfigInterface;
use EchoFusion\Contracts\EventManager\EventManagerInterface;
use EchoFusion\Contracts\MiddlewareManager\MiddlewareManagerInterface;
use EchoFusion\Contracts\ModuleManager\ModuleInterface;
use EchoFusion\Contracts\PluginManager\PluginManagerInterface;
use EchoFusion\Contracts\RouteManager\RouteLoaderInterface;
use EchoFusion\Contracts\RouteManager\RouteRegistrarInterface;
use EchoFusion\Contracts\RouteManager\RouterInterface;
use EchoFusion\Contracts\ServiceProvider\ServiceProviderInterface;
use EchoFusion\Contracts\TemplateManager\TemplateManagerInterface;
use EchoFusion\DatabaseManager\Providers\DatabaseManagerProvider;
use EchoFusion\Framework\Bootstrap\ConfigLoader;
use EchoFusion\Framework\Bootstrap\CoreServices;
use EchoFusion\Framework\Events\ApplicationBooted;
use EchoFusion\Framework\Events\ExceptionRaised;
use EchoFusion\Framework\Events\RequestReceived;
use EchoFusion\Framework\Events\ResponsePrepared;
use EchoFusion\Framework\Exceptions\ConfigurationException;
use EchoFusion\Framework\Http\ErrorHandlerInterface;
use EchoFusion\Framework\Http\ResponseEmitter;
use EchoFusion\Framework\Http\RouteHandler;
use EchoFusion\ModuleManager\ModuleManager;
use EchoFusion\ServiceManager\ServiceManager;
use EchoFusion\TemplateManager\Providers\TemplateManagerProvider;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use function array_is_list;
use function array_key_exists;
use function array_replace;
use function class_exists;
use function count;
use function date_default_timezone_set;
use function get_debug_type;
use function in_array;
use function is_array;
use function is_callable;
use function is_int;
use function is_string;
use function is_subclass_of;
use function ltrim;
use function rtrim;
use function sprintf;

/**
 * The kernel. It turns configuration into a running application and a PSR-7 request into a response.
 *
 * Booting happens once, in this order:
 *
 *  1. `.env` is read and the environment is chosen (`APP_ENV`, default "production").
 *  2. Config: every `config/*.php` is a section; `config/environments/<env>.php` is merged on top.
 *  3. Modules listed in `app.modules` contribute config, providers, routes and template paths.
 *  4. The container is created and the core services are registered.
 *  5. Service providers: core ones (templates, database, auth), then those of the modules, then `app.providers`.
 *     Every provider is **registered** first and only then **booted** with its configuration section.
 *  6. Routes are loaded (modules first, then `config/routes.php`), module templates are added, services from
 *     `config/services.php` are defined.
 *  7. Listeners from `config/events.php` are attached.
 *  8. Plugins from `app.plugins` are registered for the environment.
 *  9. {@see ApplicationBooted} is dispatched.
 *
 * Handling a request: {@see RequestReceived}, global middleware (`middleware.core`), the route, the middleware
 * of that route, the action, then {@see ResponsePrepared}. Anything thrown goes through {@see ExceptionRaised}
 * and the {@see ErrorHandlerInterface}.
 */
final class Application
{
    /**
     * Providers every application gets, with the configuration section each one is booted with.
     *
     * @var array<class-string<ServiceProviderInterface>, string>
     */
    public const CORE_PROVIDERS = [
        TemplateManagerProvider::class => 'templates',
        DatabaseManagerProvider::class => 'database',
        AuthManagerProvider::class => 'auth',
    ];

    private readonly string $basePath;

    private readonly string $environment;

    private bool $booted = false;

    private ServiceManager $container;

    private ConfigInterface $config;

    public function __construct(string $basePath, ?string $environment = null)
    {
        $this->basePath = rtrim($basePath, '/\\');

        // values already in the real environment win over the .env file
        Dotenv::createImmutable($this->basePath)->safeLoad();

        $env = $environment ?? env('APP_ENV', 'production');
        $this->environment = is_string($env) && $env !== '' ? $env : 'production';
    }

    /**
     * Create the application and boot it.
     */
    public static function create(string $basePath, ?string $environment = null): self
    {
        return (new self($basePath, $environment))->boot();
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        $sections = ConfigLoader::load($this->path('config'), $this->environment);

        $app = isset($sections['app']) && is_array($sections['app']) ? $sections['app'] : [];
        $timezone = $app['timezone'] ?? null;
        if (is_string($timezone) && $timezone !== '') {
            date_default_timezone_set($timezone);
        }

        $modules = $this->loadModules($app['modules'] ?? []);

        // module config is the base, the application's own config wins
        $routes = $sections['routes'] ?? [];
        $merged = (new Config($modules->getConfig(), $sections))->all();
        $merged['routes'] = is_array($routes) ? array_replace($modules->getRoutes(), $routes) : $modules->getRoutes();
        $this->config = new Config($merged);

        $this->container = new ServiceManager();
        $this->container->enableOverride(true); // providers replace the service they registered earlier with a configured one

        (new CoreServices($this, $this->config, $modules))->register($this->container);

        $providers = $this->providers($modules);
        foreach ($providers as $provider => $_) {
            $this->providerInstance($provider)->register($this->container);
        }
        foreach ($providers as $provider => $section) {
            $settings = $section === null ? [] : $this->config->get($section, []);
            $this->providerInstance($provider)->boot($this->container, is_array($settings) ? $settings : []);
        }

        $this->defineServices();
        $this->loadRoutes($routes);
        $this->addModuleTemplates($modules);
        $this->attachListeners();

        $this->container->get(PluginManagerInterface::class)->register($this->environment);

        $this->booted = true;
        $this->container->get(EventManagerInterface::class)->dispatch(new ApplicationBooted($this));

        return $this;
    }

    /**
     * Turn a request into a response. Errors never escape: they become responses.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->boot();

        $events = $this->container->get(EventManagerInterface::class);

        try {
            $received = new RequestReceived($request);
            $events->dispatch($received);
            $request = $received->request;

            $response = $this->container->get(MiddlewareManagerInterface::class)->dispatch(
                $request,
                $this->container->get(RouteHandler::class),
            );
        } catch (Throwable $exception) {
            $raised = new ExceptionRaised($exception, $request);
            $events->dispatch($raised);

            $response = $raised->response ?? $this->container->get(ErrorHandlerInterface::class)->handle($exception, $request);
        }

        $prepared = new ResponsePrepared($request, $response);
        $events->dispatch($prepared);

        return $prepared->response;
    }

    /**
     * Handle the request of this PHP request (or the one you pass) and send the response.
     */
    public function run(?ServerRequestInterface $request = null): void
    {
        $request ??= ServerRequest::fromGlobals();

        $this->boot();
        $this->container->get(ResponseEmitter::class)->emit($this->handle($request), $request);
    }

    public function getContainer(): ServiceManager
    {
        $this->boot();

        return $this->container;
    }

    public function getConfig(): ConfigInterface
    {
        $this->boot();

        return $this->config;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function isDebug(): bool
    {
        return (bool) $this->getConfig()->get('app.debug', false);
    }

    public function isEnvironment(string ...$environments): bool
    {
        return in_array($this->environment, $environments, true);
    }

    /**
     * An absolute path inside the application: `path('storage/cache')`.
     */
    public function path(string $relative = ''): string
    {
        return $relative === '' ? $this->basePath : $this->basePath . '/' . ltrim($relative, '/\\');
    }

    /**
     * @param mixed $listed the `app.modules` setting
     *
     * @throws ConfigurationException
     */
    private function loadModules(mixed $listed): ModuleManager
    {
        $manager = new ModuleManager();

        foreach (is_array($listed) ? $listed : [] as $class) {
            if (!is_string($class) || !class_exists($class) || !is_subclass_of($class, ModuleInterface::class)) {
                throw new ConfigurationException(sprintf('app.modules lists "%s", which is not a class implementing %s.', is_string($class) ? $class : get_debug_type($class), ModuleInterface::class));
            }

            $manager->register(new $class());
        }

        return $manager;
    }

    /**
     * @return array<class-string<ServiceProviderInterface>, string|null> provider => its configuration section
     *
     * @throws ConfigurationException
     */
    private function providers(ModuleManager $modules): array
    {
        $list = self::CORE_PROVIDERS;

        foreach ($modules->getProviders() as $provider) {
            $list[$provider] = null;
        }

        $configured = $this->config->get('app.providers', []);
        foreach (is_array($configured) ? $configured : [] as $key => $value) {
            // either `Provider::class` or `Provider::class => 'config.section'`
            $class = is_int($key) ? $value : $key;
            if (!is_string($class) || !class_exists($class) || !is_subclass_of($class, ServiceProviderInterface::class)) {
                throw new ConfigurationException(sprintf('app.providers lists "%s", which is not a class implementing %s.', is_string($class) ? $class : get_debug_type($class), ServiceProviderInterface::class));
            }

            $list[$class] = is_int($key) ? null : (is_string($value) ? $value : null);
        }

        return $list;
    }

    /**
     * @param class-string<ServiceProviderInterface> $class
     */
    private function providerInstance(string $class): ServiceProviderInterface
    {
        return $this->providerInstances[$class] ??= new $class();
    }

    /**
     * @var array<class-string<ServiceProviderInterface>, ServiceProviderInterface>
     */
    private array $providerInstances = [];

    /**
     * `config/services.php`: `'dependencies'` maps an id to a class name or a factory; ids listed in `'shared'` are built once.
     */
    private function defineServices(): void
    {
        $services = $this->config->get('services', []);
        $services = is_array($services) ? $services : [];

        $shared = $services['shared'] ?? [];
        $dependencies = $services['dependencies'] ?? [];

        foreach (is_array($dependencies) ? $dependencies : [] as $id => $definition) {
            if (!is_string($definition) && !is_callable($definition)) {
                throw new ConfigurationException(sprintf('services.dependencies["%s"] must be a class name or a callable.', (string) $id));
            }

            $this->container->set((string) $id, $definition, is_array($shared) && in_array($id, $shared, true));
        }
    }

    /**
     * Routes come from the `routes` section (module routes merged below the application's), given as an array in the
     * format of `RouteLoaderInterface::fromArray()`, or from a function in `config/routes.php` that receives the registrar.
     *
     * @param mixed $declared what `config/routes.php` returned
     */
    private function loadRoutes(mixed $declared): void
    {
        $router = $this->container->get(RouterInterface::class);
        $routes = $this->config->get('routes', []);

        if ($router instanceof RouteLoaderInterface && is_array($routes) && $routes !== []) {
            $router->fromArray($routes);
        }

        if ($declared instanceof Closure) {
            $declared($this->container->get(RouteRegistrarInterface::class), $this);
        }
    }

    private function addModuleTemplates(ModuleManager $modules): void
    {
        $paths = $modules->getTemplatePaths();
        if ($paths === []) {
            return;
        }

        $templates = $this->container->get(TemplateManagerInterface::class);
        foreach ($paths as $module => $path) {
            $templates->addPath($path, $module);
        }
    }

    /**
     * `config/events.php`:
     *
     * <code>
     * return ['listeners' => [
     *     UserRegistered::class => [SendWelcomeMail::class, [AuditLog::class, 'onRegistered'], ['listener' => Slow::class, 'priority' => -10]],
     * ]];
     * </code>
     *
     * A listener is a callable, the class name of an invokable class, or `[class, method]`; classes are built by the
     * container the first time the event happens.
     */
    private function attachListeners(): void
    {
        $listeners = $this->config->get('events.listeners', []);
        $events = $this->container->get(EventManagerInterface::class);

        foreach (is_array($listeners) ? $listeners : [] as $event => $entries) {
            foreach (is_array($entries) && !$this->isMethodReference($entries) && !isset($entries['listener']) ? $entries : [$entries] as $entry) {
                $priority = 0;
                if (is_array($entry) && array_key_exists('listener', $entry)) {
                    $priority = is_int($entry['priority'] ?? null) ? $entry['priority'] : 0;
                    $entry = $entry['listener'];
                }

                $events->attach((string) $event, $this->listener($entry), $priority);
            }
        }
    }

    private function listener(mixed $entry): callable
    {
        $container = $this->container;

        if ($entry instanceof Closure) {
            return $entry;
        }

        if (is_string($entry) && class_exists($entry)) {
            return static fn (object $event): mixed => $container->get($entry)($event);
        }

        if ($this->isMethodReference($entry) && is_array($entry) && class_exists((string) $entry[0])) {
            [$class, $method] = $entry;

            return static fn (object $event): mixed => $container->get($class)->$method($event);
        }

        if (is_callable($entry)) {
            return $entry;
        }

        throw new ConfigurationException('An event listener must be a callable, the name of an invokable class, or [class, method].');
    }

    private function isMethodReference(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && count($value) === 2 && is_string($value[0]) && is_string($value[1]);
    }
}
