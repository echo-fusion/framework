<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests;

use EchoFusion\AuthManager\Middleware\AuthenticateMiddleware;
use EchoFusion\Contracts\ConfigManager\ConfigInterface;
use EchoFusion\Contracts\EventManager\EventManagerInterface;
use EchoFusion\Contracts\ModuleManager\ModuleManagerInterface;
use EchoFusion\Contracts\PluginManager\PluginManagerInterface;
use EchoFusion\Contracts\RouteManager\RouteRegistrarInterface;
use EchoFusion\Contracts\RouteManager\RouterInterface;
use EchoFusion\Contracts\ServiceManager\SettableContainerInterface;
use EchoFusion\Contracts\SessionManager\SessionInterface;
use EchoFusion\Framework\Application;
use EchoFusion\Framework\Events\ApplicationBooted;
use EchoFusion\Framework\Tests\Fixtures\BlogModule;
use EchoFusion\Framework\Tests\Fixtures\DevPlugin;
use EchoFusion\Framework\Tests\Fixtures\EventLog;
use EchoFusion\Framework\Tests\Fixtures\HeaderMiddleware;
use EchoFusion\Framework\Tests\Fixtures\HomeController;
use EchoFusion\Framework\Tests\Fixtures\InvokableAction;
use EchoFusion\Framework\Tests\Fixtures\ProdOnlyPlugin;
use EchoFusion\Framework\Tests\Fixtures\TagMiddleware;
use EchoFusion\SessionManager\NativeSession;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use stdClass;
use const JSON_THROW_ON_ERROR;
use const PASSWORD_BCRYPT;
use function json_decode;

final class ApplicationTest extends TestCase
{
    private const TEMPLATES = __DIR__ . '/Fixtures/templates';

    protected function setUp(): void
    {
        EventLog::reset();
    }

    protected function tearDown(): void
    {
        TestApp::cleanUp();
    }

    /**
     * @param array<string, string> $headers
     */
    private function get(Application $app, string $path, array $headers = [], string $method = 'GET'): ResponseInterface
    {
        $request = new ServerRequest($method, $path, $headers);

        return $app->handle($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<array-key, mixed> $routes
     * @param array<string, array<array-key, mixed>|string> $more
     */
    private function app(array $routes, array $more = []): Application
    {
        return TestApp::create(['routes' => $routes] + $more);
    }

    public function testAClosureRouteReturningTextIsHtml(): void
    {
        $app = TestApp::create(['routes' => "<?php return static function (\\EchoFusion\\Contracts\\RouteManager\\RouteRegistrarInterface \$router) { \$router->get('home', '/', fn () => 'hello'); };"]);

        $response = $this->get($app, '/');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('hello', (string) $response->getBody());
    }

    public function testAControllerGetsRoutePlaceholdersConvertedToTheirTypeAndInjectedServices(): void
    {
        $app = $this->app(['post' => ['method' => 'GET', 'path' => '/posts/{id}', 'action' => [HomeController::class, 'show']]]);

        $response = $this->get($app, '/posts/42');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(['id' => 42, 'title' => 'Post 42', 'format' => null], $this->json($response), 'an int placeholder arrives as an int');
    }

    public function testTheRequestIsInjectedAndRoutePlaceholdersAreRequestAttributes(): void
    {
        $app = $this->app(['inject' => ['method' => 'POST', 'path' => '/inject/{id}', 'action' => [HomeController::class, 'inject']]]);

        $data = $this->json($this->get($app, '/inject/9', [], 'POST'));

        $this->assertSame('POST', $data['method']);
        $this->assertTrue($data['sameRepositoryType']);
        $this->assertSame('9', $data['idAttribute']);
        $this->assertSame(['id' => '9'], $data['params']);
        $this->assertSame(9, $data['id']);
    }

    public function testTheReturnValueBecomesTheResponse(): void
    {
        $app = $this->app([
            'nothing' => ['method' => 'GET', 'path' => '/nothing', 'action' => [HomeController::class, 'nothing']],
            'custom' => ['method' => 'GET', 'path' => '/custom', 'action' => [HomeController::class, 'custom']],
            'invoke' => ['method' => 'GET', 'path' => '/invoke', 'action' => [InvokableAction::class, '__invoke']],
        ]);

        $this->assertSame(204, $this->get($app, '/nothing')->getStatusCode());
        $custom = $this->get($app, '/custom');
        $this->assertSame([201, 'yes', 'made'], [$custom->getStatusCode(), $custom->getHeaderLine('X-Custom'), (string) $custom->getBody()]);
        $this->assertSame('invoked', (string) $this->get($app, '/invoke')->getBody());
    }

    public function testNotFound(): void
    {
        $app = $this->app(['home' => ['method' => 'GET', 'path' => '/', 'action' => [HomeController::class, 'index']]]);

        $html = $this->get($app, '/missing');
        $this->assertSame(404, $html->getStatusCode());
        $this->assertStringContainsString('404 Not Found', (string) $html->getBody());

        $json = $this->get($app, '/missing', ['Accept' => 'application/json']);
        $this->assertSame(404, $json->getStatusCode());
        $this->assertSame(404, $this->json($json)['status']);
    }

    public function testMethodNotAllowedListsTheAllowedMethods(): void
    {
        $app = $this->app(['home' => ['method' => 'GET', 'path' => '/', 'action' => [HomeController::class, 'index']]]);

        $response = $this->get($app, '/', [], 'DELETE');

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET, HEAD', $response->getHeaderLine('Allow'));
    }

    public function testAPlaceholderThatCannotBeConvertedIsANotFound(): void
    {
        $app = $this->app(['post' => ['method' => 'GET', 'path' => '/posts/{id}', 'action' => [HomeController::class, 'show']]]);

        $this->assertSame(404, $this->get($app, '/posts/abc')->getStatusCode());
    }

    public function testControllersCanThrowHttpExceptions(): void
    {
        $app = $this->app(['edit' => ['method' => 'GET', 'path' => '/edit', 'action' => [HomeController::class, 'forbidden']]]);

        $response = $this->get($app, '/edit');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('You cannot edit this post.', (string) $response->getBody());
    }

    public function testDebugShowsTheErrorAndProductionHidesIt(): void
    {
        $routes = ['boom' => ['method' => 'GET', 'path' => '/boom', 'action' => [HomeController::class, 'boom']]];

        $debug = $this->get($this->app($routes, ['app' => ['debug' => true]]), '/boom', ['Accept' => 'application/json']);
        $this->assertSame(500, $debug->getStatusCode());
        $this->assertSame('secret database password', $this->json($debug)['message']);
        $this->assertArrayHasKey('trace', $this->json($debug));

        $production = $this->get($this->app($routes, ['app' => ['debug' => false]]), '/boom', ['Accept' => 'application/json']);
        $this->assertSame(500, $production->getStatusCode());
        $this->assertSame(['status' => 500, 'message' => 'Internal Server Error'], $this->json($production));
        $this->assertStringNotContainsString('secret', (string) $production->getBody());
    }

    public function testRouteMiddlewareByAliasAndByClassAndGlobalMiddlewareRunInOrder(): void
    {
        $app = $this->app(
            [
                'a' => ['method' => 'GET', 'path' => '/alias', 'action' => [HomeController::class, 'index'], 'middlewares' => ['tag']],
                'b' => ['method' => 'GET', 'path' => '/class', 'action' => [HomeController::class, 'index'], 'middlewares' => [TagMiddleware::class]],
                'c' => ['method' => 'GET', 'path' => '/plain', 'action' => [HomeController::class, 'index']],
            ],
            ['middleware' => ['core' => [HeaderMiddleware::class], 'route' => ['tag' => TagMiddleware::class]]],
        );

        $this->assertSame(['route', 'global'], $this->get($app, '/alias')->getHeader('X-Order'), 'the route middleware is inside the global one');
        $this->assertSame(['route', 'global'], $this->get($app, '/class')->getHeader('X-Order'));
        $this->assertSame(['global'], $this->get($app, '/plain')->getHeader('X-Order'));
    }

    public function testAnUnknownRouteMiddlewareIsAConfigurationError(): void
    {
        $app = $this->app(['a' => ['method' => 'GET', 'path' => '/', 'action' => [HomeController::class, 'index'], 'middlewares' => ['nope']]]);

        $response = $this->get($app, '/', ['Accept' => 'application/json']);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('nope', $this->json($response)['message']);
    }

    public function testAuthenticationProtectsARouteThroughAnAlias(): void
    {
        $hash = password_hash('secret', PASSWORD_BCRYPT, ['cost' => 4]);
        $app = $this->app(
            ['me' => ['method' => 'GET', 'path' => '/me', 'action' => [HomeController::class, 'index'], 'middlewares' => ['auth']]],
            [
                'middleware' => ['route' => ['auth' => AuthenticateMiddleware::class]],
                'auth' => [
                    'guards' => ['api' => ['driver' => 'token', 'provider' => 'users']],
                    'providers' => ['users' => ['driver' => 'array', 'users' => [['id' => 1, 'password' => $hash, 'token' => 'tok-1']]]],
                    'middleware' => ['authenticate' => ['guard' => 'api']],
                ],
            ],
        );

        $this->assertSame(401, $this->get($app, '/me')->getStatusCode());
        $this->assertSame(401, $this->get($app, '/me', ['Authorization' => 'Bearer wrong'])->getStatusCode());
        $ok = $this->get($app, '/me', ['Authorization' => 'Bearer tok-1']);
        $this->assertSame([200, 'home'], [$ok->getStatusCode(), (string) $ok->getBody()]);
    }

    public function testTemplatesDatabaseAndSessionAreWiredFromConfig(): void
    {
        $app = $this->app(
            [
                'tpl' => ['method' => 'GET', 'path' => '/tpl', 'action' => [HomeController::class, 'render']],
                'db' => ['method' => 'GET', 'path' => '/db', 'action' => [HomeController::class, 'database']],
            ],
            [
                'templates' => ['paths' => [self::TEMPLATES], 'globals' => ['app' => 'Echo'], 'engines' => ['tpl' => ['options' => ['cache' => sys_get_temp_dir() . '/ef-fw-templates']]]],
                'database' => ['connections' => ['main' => ['driver' => 'sqlite']]],
            ],
        );

        $this->assertSame("Hello Ann from Echo\n", (string) $this->get($app, '/tpl')->getBody());
        $this->assertSame(['one' => 1], $this->json($this->get($app, '/db')));
        $this->assertInstanceOf(NativeSession::class, $app->getContainer()->get(SessionInterface::class));
    }

    public function testAnErrorPageTemplateIsUsedWhenItExists(): void
    {
        $templates = ['templates' => ['paths' => [self::TEMPLATES], 'engines' => ['tpl' => ['options' => ['cache' => sys_get_temp_dir() . '/ef-fw-templates']]]]];

        $debug = TestApp::create($templates + ['app' => ['debug' => true]]);
        $this->assertSame("Custom 404: No route matches GET /missing.\n", (string) $this->get($debug, '/missing')->getBody(), 'developers see why');

        $production = TestApp::create($templates + ['app' => ['debug' => false]]);
        $this->assertSame("Custom 404: Not Found\n", (string) $this->get($production, '/missing')->getBody(), 'visitors do not');
    }

    public function testListenersFromConfigAreAttachedWithPriorities(): void
    {
        $app = $this->app(
            ['home' => ['method' => 'GET', 'path' => '/', 'action' => [HomeController::class, 'index']]],
            ['events' => ['listeners' => [
                ApplicationBooted::class => [EventLog::class],
                'custom' => [
                    ['listener' => [EventLog::class, 'low'], 'priority' => -5],
                    [EventLog::class, 'high'],
                ],
            ]]],
        );

        $app->boot();
        $this->assertSame(['invoked:' . ApplicationBooted::class], EventLog::$entries, 'listeners are attached before ApplicationBooted is dispatched');

        EventLog::reset();
        $app->getContainer()->get(EventManagerInterface::class)->dispatch(new \EchoFusion\EventManager\BaseEvent('custom'));
        $this->assertSame(['high', 'low'], EventLog::$entries);
    }

    public function testRequestAndResponseListenersCanChangeThem(): void
    {
        $app = $this->app(
            ['home' => ['method' => 'GET', 'path' => '/', 'action' => [HomeController::class, 'index']]],
            ['events' => ['listeners' => [
                \EchoFusion\Framework\Events\RequestReceived::class => [[EventLog::class, 'onRequest']],
                \EchoFusion\Framework\Events\ResponsePrepared::class => [[EventLog::class, 'onResponse']],
            ]]],
        );

        $response = $this->get($app, '/');

        $this->assertSame('yes', $response->getHeaderLine('X-Prepared'));
        $this->assertSame(['method:request', 'method:response'], EventLog::$entries);
    }

    public function testAListenerCanTakeOverErrorHandling(): void
    {
        $app = $this->app(
            ['boom' => ['method' => 'GET', 'path' => '/boom', 'action' => [HomeController::class, 'boom']]],
            ['events' => ['listeners' => [\EchoFusion\Framework\Events\ExceptionRaised::class => [[EventLog::class, 'onException']]]]],
        );

        $response = $this->get($app, '/boom');

        $this->assertSame([418, 'teapot: secret database password'], [$response->getStatusCode(), (string) $response->getBody()]);
    }

    public function testPluginsAreRegisteredOnlyForTheirEnvironment(): void
    {
        $app = TestApp::create(['app' => ['debug' => true, 'plugins' => [DevPlugin::class, ProdOnlyPlugin::class]]], [], 'testing');
        $container = $app->getContainer();

        $this->assertSame('dev plugin active', $container->get('plugin.dev'));
        $this->assertFalse($container->has('plugin.prod'));
        $this->assertTrue($container->get(PluginManagerInterface::class)->isRegistered(DevPlugin::class));

        $production = TestApp::create(['app' => ['plugins' => [DevPlugin::class, ProdOnlyPlugin::class]]], [], 'production');
        $this->assertSame('prod plugin active', $production->getContainer()->get('plugin.prod'));
        $this->assertFalse($production->getContainer()->has('plugin.dev'));
    }

    public function testAModuleContributesConfigProvidersRoutesAndTemplates(): void
    {
        $app = TestApp::create(
            [
                'app' => ['debug' => true, 'name' => 'the application wins', 'modules' => [BlogModule::class]],
                'templates' => ['engines' => ['tpl' => ['options' => ['cache' => sys_get_temp_dir() . '/ef-fw-templates']]]],
            ],
        );

        $response = $this->get($app, '/blog');

        $this->assertSame("Blog index, 3 posts\n", (string) $response->getBody(), 'a module route, a namespaced module template and module config');
        $this->assertSame('the application wins', $app->getConfig()->get('app.name'), 'application config overrides module config');
        $this->assertSame('From the module', $app->getConfig()->get('blog.title'));
        $this->assertSame('blog service', $app->getContainer()->get('blog.service'));
        $this->assertSame(['blog:register', 'blog:boot:empty'], EventLog::$entries);
        $this->assertTrue($app->getContainer()->get(ModuleManagerInterface::class)->has('blog'));
    }

    public function testTheApplicationOverridesAModuleRouteOfTheSameName(): void
    {
        $app = TestApp::create([
            'app' => ['debug' => true, 'modules' => [BlogModule::class]],
            'routes' => ['blog.index' => ['method' => 'GET', 'path' => '/blog', 'action' => [HomeController::class, 'index']]],
        ]);

        $this->assertSame('home', (string) $this->get($app, '/blog')->getBody());
    }

    public function testProvidersFromConfigAreRegisteredBeforeAnyIsBooted(): void
    {
        TestApp::create(['app' => ['debug' => true, 'providers' => [\EchoFusion\Framework\Tests\Fixtures\BlogProvider::class => 'blog']], 'blog' => ['x' => 1]])->boot();

        $this->assertSame(['blog:register', 'blog:boot:configured'], EventLog::$entries, 'the provider is booted with its configuration section');
    }

    public function testServicesFromConfig(): void
    {
        $app = TestApp::create(['services' => [
            'dependencies' => ['greeter' => stdClass::class, 'once' => stdClass::class],
            'shared' => ['once'],
        ]]);
        $container = $app->getContainer();

        $this->assertNotSame($container->get('greeter'), $container->get('greeter'));
        $this->assertSame($container->get('once'), $container->get('once'));
    }

    public function testTheCoreServicesAreInTheContainer(): void
    {
        $app = TestApp::create();
        $container = $app->getContainer();

        $this->assertSame($app, $container->get(Application::class));
        $this->assertInstanceOf(ConfigInterface::class, $container->get(ConfigInterface::class));
        $this->assertSame($container, $container->get(SettableContainerInterface::class));
        $this->assertSame($container->get(RouterInterface::class), $container->get(RouteRegistrarInterface::class), 'one router registers and dispatches');
        $this->assertSame($container->get(EventManagerInterface::class), $container->get(\Psr\EventDispatcher\EventDispatcherInterface::class));
    }

    public function testEnvironmentPathsAndBooting(): void
    {
        $directory = TestApp::directory(['app' => ['debug' => true]], ['.env' => "APP_ENV=staging\n"]);
        $fromDotEnv = new Application($directory);
        $explicit = new Application($directory, 'testing');

        $this->assertSame('testing', $explicit->getEnvironment());
        $this->assertTrue($explicit->isEnvironment('x', 'testing'));
        $this->assertFalse($explicit->isEnvironment('production'));
        $this->assertSame($directory . '/storage/cache', $explicit->path('storage/cache'));
        $this->assertSame($directory, $explicit->path());
        $this->assertTrue($explicit->isDebug());
        $this->assertSame($explicit, $explicit->boot()->boot(), 'booting twice is harmless');
        $created = Application::create($directory, 'testing');
        $this->assertInstanceOf(Application::class, $created);
        $this->assertSame('testing', $created->getEnvironment());
        $this->assertIsString($fromDotEnv->getEnvironment());
    }

    public function testEnvironmentSpecificConfigIsMergedOnTop(): void
    {
        $directory = TestApp::directory(
            ['app' => ['debug' => true, 'name' => 'base', 'plugins' => []]],
            ['config/environments/testing.php' => "<?php return ['app' => ['debug' => false, 'extra' => 'only in testing']];"],
        );

        $testing = new Application($directory, 'testing');
        $other = new Application($directory, 'other');

        $this->assertFalse($testing->isDebug());
        $this->assertSame('only in testing', $testing->getConfig()->get('app.extra'));
        $this->assertSame('base', $testing->getConfig()->get('app.name'), 'the rest of the section is kept');
        $this->assertTrue($other->isDebug());
        $this->assertNull($other->getConfig()->get('app.extra'));
    }

    public function testTheTimezoneIsAppliedFromConfig(): void
    {
        $before = date_default_timezone_get();
        TestApp::create(['app' => ['debug' => true, 'timezone' => 'Asia/Tehran']])->boot();

        $this->assertSame('Asia/Tehran', date_default_timezone_get());

        date_default_timezone_set($before);
    }

    public function testAnApplicationWithoutRoutesAnswers404NotACrash(): void
    {
        $this->assertSame(404, $this->get(TestApp::create(), '/')->getStatusCode());
    }
}
