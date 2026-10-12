# Request lifecycle

`public/index.php` creates the application and runs it:

```php
Application::create(dirname(__DIR__))->run();
```

## Booting (once per PHP request)

1. `.env` is read; `APP_ENV` picks the environment (default `production`).
2. Every `config/*.php` becomes a config section; `config/environments/<env>.php` is merged on top.
3. Modules listed in `app.modules` add config, providers, routes and template folders.
4. The container is created and the core services are registered.
5. Service providers: first all `register()`, then all `boot()`.
6. Routes, module templates and `config/services.php` are loaded.
7. Listeners from `config/events.php` are attached.
8. Plugins are registered for the environment.
9. The event `ApplicationBooted` is dispatched.

## Handling a request

```
browser -> RequestReceived event
        -> global middleware        (config/middleware.php 'core', in order)
        -> router finds the route   (404 / 405 are produced here)
        -> the route's middleware   ('middlewares' => ['auth'])
        -> your action              (arguments filled from placeholders, Request, services)
        -> response conversion      (string = HTML, array = JSON, null = 204, PSR-7 response as is)
        -> ResponsePrepared event
        -> emitted to the browser
```

An exception at any point becomes a response: it goes through the `ExceptionRaised` event, then the error handler (JSON for API clients, an HTML page otherwise). Throw `new HttpException(403, 'message')` from a controller to answer with a status.

Everything in this picture is replaceable, because every part is looked up in the container by an interface. See [Extending](extending.md).
