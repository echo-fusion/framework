# Framework

[![CI](https://github.com/echo-fusion/framework/actions/workflows/ci.yml/badge.svg)](https://github.com/echo-fusion/framework/actions/workflows/ci.yml) [![Packagist](https://img.shields.io/packagist/v/echo-fusion/framework.svg)](https://packagist.org/packages/echo-fusion/framework) [![PHP](https://img.shields.io/badge/php-8.1%20%7C%208.2%20%7C%208.3-777bb4.svg)](https://www.php.net) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

The EchoFusion framework: a small kernel that boots configuration, modules, service providers, events and plugins, and turns a PSR-7 request into a response. Everything it uses is a separate EchoFusion package behind an interface from [echo-fusion/contracts](https://github.com/echo-fusion/contracts), so any part can be replaced.

> Part of [EchoFusion](https://github.com/echo-fusion), a modular PHP framework built from small packages.

## Start a project

Most people want the skeleton, which contains the folders, config files and front controller:

```bash
composer create-project echo-fusion/echo-fusion my-app
cd my-app
composer serve          # http://localhost:8000
```

To use the framework in an existing project, require it and create the application yourself (see [The front controller](#the-front-controller)):

```bash
composer require echo-fusion/framework
```

## Requirements

* PHP 8.1, 8.2 or 8.3
* ext-mbstring

## What is inside

| Part | Package | Replace it by binding another implementation of |
| --- | --- | --- |
| Container | [servicemanager](https://github.com/echo-fusion/servicemanager) | the container is the composition root, not replaced |
| Configuration | [configmanager](https://github.com/echo-fusion/configmanager) | `ConfigInterface` |
| Router | [routemanager](https://github.com/echo-fusion/routemanager) | `RouterInterface`, `RouteMatcherInterface` |
| Middleware | [middlewaremanager](https://github.com/echo-fusion/middlewaremanager) | `MiddlewareManagerInterface` |
| Events (PSR-14) | [eventmanager](https://github.com/echo-fusion/eventmanager) | `EventManagerInterface`, or any PSR-14 dispatcher |
| Plugins | [pluginmanager](https://github.com/echo-fusion/pluginmanager) | `PluginManagerInterface` |
| Modules | [modulemanager](https://github.com/echo-fusion/modulemanager) | `ModuleManagerInterface` |
| Sessions | [sessionmanager](https://github.com/echo-fusion/sessionmanager) | `SessionInterface` |
| Responses | [responsemanager](https://github.com/echo-fusion/responsemanager) | PSR-7 |
| Templates | [templatemanager](https://github.com/echo-fusion/templatemanager) | `TemplateManagerInterface` |
| Database | [databasemanager](https://github.com/echo-fusion/databasemanager) | `ConnectionInterface` (PDO, Doctrine DBAL, your ORM) |
| Authentication | [authmanager](https://github.com/echo-fusion/authmanager) | `GuardInterface`, `UserProviderInterface` |

## The front controller

```php
// public/index.php
require __DIR__ . '/../vendor/autoload.php';

EchoFusion\Framework\Application::create(__DIR__ . '/..')->run();
```

`Application::create($basePath)` boots the application; `run()` reads the request from PHP's globals, handles it and sends the response. In tests you skip the web server:

```php
$app = Application::create($basePath, 'testing');
$response = $app->handle(new ServerRequest('GET', '/posts/5'));   // a PSR-7 response, nothing is sent
```

## How it boots

Booting happens once, in this order. Knowing it tells you where to hook in.

1. **Environment.** `.env` is loaded (variables already set in the real environment win) and the environment name is chosen: the constructor argument, else `APP_ENV`, else `production`.
2. **Configuration.** Every `config/<name>.php` returns an array and becomes the section `<name>`. `config/environments/<environment>.php` is merged on top. See [Configuration](#configuration).
3. **Modules** listed in `app.modules` contribute configuration (below the application's own), providers, routes and template directories.
4. **Container and core services.** The container is created and the services the framework itself provides are registered (configuration, events, plugins, session, router, middleware, error handler, PSR-17 factories).
5. **Service providers.** The core providers (templates, database, auth), then the providers of the modules, then `app.providers`. **All providers are registered first, and only then booted**, each with its configuration section, so a provider can rely on every other provider's services.
6. **Application wiring.** Routes are loaded (modules first, then your `config/routes.php`), module templates are added, services from `config/services.php` are defined.
7. **Listeners** from `config/events.php` are attached.
8. **Plugins** from `app.plugins` are registered for the current environment.
9. **`ApplicationBooted`** is dispatched.

## How a request is handled

```
Request ─► RequestReceived ─► global middleware (middleware.core)
                                   └─► route ─► middleware of that route ─► action ─► response
Response ◄─ ResponsePrepared ◄──────────────────────────────────────────────────────────┘
        any exception ─► ExceptionRaised ─► error handler
```

* The router matches the request. A placeholder matches letters, digits, `_` and `-` unless you give it a constraint (`'constraints' => ['name' => '[^/]+']`). No match is a 404, a wrong method on an existing path a 405 with an `Allow` header.
* The route's middleware runs inside the global middleware. A name is looked up in `middleware.route`; a class name works too.
* The **action** is a closure, `[Controller::class, 'method']` (the controller is built by the container) or the name of an invokable class. Its arguments come from the signature:
  * a parameter named like a route placeholder gets the value, URL-decoded (`/hello/caf%C3%A9` gives `café`) and converted to the parameter's type (`/posts/{id}` with `int $id` gives an int; a value that is not a number is a 404);
  * `Request` (the helper described below, or your subclass of it) or `ServerRequestInterface` (raw PSR-7) gets the request; the placeholders are also request attributes (`route`, `route.params`, `id`);
  * any other class type is taken from the container;
  * otherwise the default value is used.
* What the action returns becomes the response: a PSR-7 response as it is, text as HTML, `null` as an empty 204, anything else (array, object, number) as JSON.

## Reading the request: `Request`

Ask for `Request` as an action argument and read GET, POST and JSON data without touching PSR-7:

```php
use EchoFusion\Framework\Http\Request;

public function store(Request $request): array
{
    $title = $request->input('title');          // form field, JSON field or query string, in that order of priority
    $page  = $request->query('page', 1);        // only ?page=
    $email = $request->post('email');           // only the submitted form
    $tag   = $request->json('tags.0');          // only the JSON body, dot keys reach inside
    $data  = $request->only('title', 'body');   // just these keys
    $age   = $request->int('age');              // typed: int(), float(), string(), bool()

    $request->method();  $request->path();  $request->header('Accept');  $request->cookie('theme');
    $request->file('avatar');                   // a PSR-7 UploadedFileInterface
    $request->bearerToken();  $request->wantsJson();  $request->ip();
    $request->user();                           // the authenticated user (after the 'auth' middleware)
    $request->attribute('id');                  // a route placeholder
    $request->psr();                            // the PSR-7 request itself
}
```

* `input()` merges the query string under the body, so the body wins. `all()`, `has()`, `filled()`, `only()` and `except()` work on that merged data and accept dot keys (`filter.status`).
* Forms of `PUT`, `PATCH` and `DELETE` are read from the body too (PHP only fills `$_POST` for `POST`). A body marked as JSON that is not valid JSON answers `400`.
* Need the raw PSR-7 request? Type-hint `ServerRequestInterface` instead.
* **Your own request type:** `final class StorePostRequest extends Request { public function title(): string { ... } }` and type-hint that in the action; the framework builds it. It is a good place for validation.

## CSRF protection

`Http\Middleware\VerifyCsrfToken` protects every form against cross-site request forgery. Every session gets a random token; a request that changes something (not `GET`, `HEAD` or `OPTIONS`) must send it back as the field `_token` or the header `X-CSRF-TOKEN`, otherwise the answer is `419 Page Expired`.

```php
// config/middleware.php
'core' => [StartSessionMiddleware::class, VerifyCsrfToken::class],
```

```php
// config/app.php: paths that need no token. APIs that authenticate with a header token are not exposed to CSRF.
'csrf' => ['except' => ['/api/*']],
```

```html
<form method="post" action="/posts">
    <input type="hidden" name="_token" value="{{ csrf }}">   <!-- the action passes ['csrf' => $request->csrfToken()] -->
    ...
</form>
```

JavaScript calls send the token in the `X-CSRF-TOKEN` header.

## Configuration

```
config/
  app.php             name, debug, timezone, modules, providers, plugins
  middleware.php      core (global middleware) and route (alias => class)
  routes.php          the routes (an array, or a function that receives the router)
  events.php          listeners
  services.php        extra container definitions
  templates.php       settings of templatemanager
  database.php        settings of databasemanager
  auth.php            settings of authmanager
  session.php         cookie and storage settings of the session
  environments/
    production.php    partial config merged on top in production
```

Use `env()` in config files, and nowhere else:

```php
use function EchoFusion\Framework\env;

return [
    'name' => env('APP_NAME', 'EchoFusion'),
    'debug' => env('APP_DEBUG', false),        // "true" / "false" / "null" become real values
    'timezone' => 'UTC',
    'modules' => [],                            // classes implementing ModuleInterface
    'providers' => [],                          // see below
    'plugins' => [],                            // classes implementing PluginInterface
];
```

### Routes

```php
// config/routes.php: an array ...
return [
    'home' => ['method' => 'GET', 'path' => '/', 'action' => [HomeController::class, 'index']],
    'post' => ['method' => 'GET', 'path' => '/posts/{id}', 'action' => [PostController::class, 'show'],
               'constraints' => ['id' => '[0-9]+'], 'middlewares' => ['auth']],
];
```

```php
// ... or a function, when you prefer code
use EchoFusion\Contracts\RouteManager\RouteRegistrarInterface;

return static function (RouteRegistrarInterface $router): void {
    $router->get('home', '/', [HomeController::class, 'index']);
};
```

### Middleware

```php
// config/middleware.php
return [
    'core' => [StartSessionMiddleware::class],                  // runs for every request, in this order
    'route' => ['auth' => AuthenticateMiddleware::class],       // names you can use in a route's 'middlewares'
];
```

Middleware is built from the container when a request reaches it.

### Events

```php
// config/events.php
return ['listeners' => [
    UserRegistered::class => [
        SendWelcomeMail::class,                                  // an invokable class, built by the container
        [AuditLog::class, 'onRegistered'],                       // [class, method]
        ['listener' => ProfileUpdater::class, 'priority' => -10] // lower priority runs later
    ],
]];
```

The framework dispatches `ApplicationBooted`, `RequestReceived` (replace `$event->request`), `ResponsePrepared` (replace `$event->response`) and `ExceptionRaised` (set `$event->response` to take over error handling).

### Providers

A service provider registers services in the container and is configured by one section. Core providers are always there; add yours:

```php
'providers' => [
    AppServiceProvider::class,                      // booted with an empty configuration
    PaymentServiceProvider::class => 'payments',    // booted with the section "payments" (config/payments.php)
],
```

### Services

```php
// config/services.php
return [
    'dependencies' => [
        MailerInterface::class => SmtpMailer::class,                 // a class name ...
        Clock::class => static fn () => new Clock('UTC'),            // ... or a factory that receives the container
    ],
    'shared' => [MailerInterface::class],                            // built once instead of on every get()
];
```

Auto-wiring builds any class whose constructor asks for classes the container knows (or has defaults for).

### Modules and plugins

A **module** declares data: config, providers, routes and a template directory (see [modulemanager](https://github.com/echo-fusion/modulemanager)). A **plugin** runs code at start-up for chosen environments and receives the container (see [pluginmanager](https://github.com/echo-fusion/pluginmanager)); use one for things like a debug bar in `dev` or an error tracker in `production`.

## Errors

Nothing escapes `handle()`: every exception becomes a response by the error handler.

* `Exceptions\HttpException` answers with its own status, so a controller can `throw new HttpException(403, 'You cannot edit this post.')`.
* API clients (`Accept: application/json`) get JSON; everyone else gets HTML, from the template `errors/<status>` or `errors/error` when they exist, otherwise from a small built-in page.
* With `app.debug` on, the message, class, file and trace are shown. With it off, a server error only says "Internal Server Error".
* A request that PSR-7 cannot represent (for example a malformed header name sent by the client) is answered with `400 Bad Request` by `run()`, instead of a PHP error that would show file paths. The request is built by `Http\GlobalsRequestFactory`, a service you can replace.
* Bind a PSR-3 `LoggerInterface` in the container and server errors are logged.
* Replace everything by binding your own `Http\ErrorHandlerInterface`.

## Replacing a part

Every service is looked up by an interface id, and providers run after the core services, so a provider can rebind it:

```php
final class AppServiceProvider implements ServiceProviderInterface
{
    public function register(SettableContainerInterface $container): void
    {
        $container->set(ConnectionInterface::class, fn () => new DoctrineConnection($dbalConnection), true);
    }

    public function boot(SettableContainerInterface $container, array $config = []): void
    {
    }
}
```

## Testing

```bash
composer test
```

The test suite builds small throw-away applications on disk and sends requests through `Application::handle()`.

## Credits

- [Amir Shadanfar](https://github.com/amir-shadanfar)

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
