# Events, providers, modules and plugins

## Events

```php
// config/events.php
return ['listeners' => [
    UserRegistered::class => [
        SendWelcomeMail::class,                                  // invokable class, built by the container
        [AuditLog::class, 'onRegistered'],
        ['listener' => ProfileUpdater::class, 'priority' => -10] // lower priority runs later
    ],
]];
```

Dispatch your own event from anywhere that can ask for `EventManagerInterface`:

```php
$this->events->dispatch(new UserRegistered($user));
```

Framework events: `ApplicationBooted`, `RequestReceived` (replace `$event->request`), `ResponsePrepared` (replace `$event->response`), `ExceptionRaised` (set `$event->response` to take over).

## Container and services

Classes are built by autowiring: ask for classes in the constructor and they appear. To bind an interface to an implementation, or to build something with settings:

```php
// config/services.php
return [
    'dependencies' => [
        MailerInterface::class => SmtpMailer::class,
        Clock::class => static fn () => new Clock('UTC'),
    ],
    'shared' => [MailerInterface::class],        // built once
];
```

## Providers

A provider registers services and is configured by a config section:

```php
final class PaymentProvider implements ServiceProviderInterface
{
    public function register(SettableContainerInterface $container): void
    {
        $container->set(Gateway::class, fn () => new Gateway(), true);
    }

    public function boot(SettableContainerInterface $container, array $config = []): void { /* $config is config/payments.php */ }
}

// config/app.php
'providers' => [PaymentProvider::class => 'payments'],
```

All providers `register()` first, then all `boot()`, so in `boot()` every service exists. A provider can **replace** any core service (the error handler, the database connection ...) by setting its interface again.

## Modules

A module is a reusable bundle: config, providers, routes and a template folder, declared in one class implementing `ModuleInterface`, and listed in `app.modules`. Use it to share a feature (a blog, an admin area) between applications. See [modulemanager](https://github.com/echo-fusion/modulemanager).

## Plugins

A plugin runs code at start-up, only for the environments it supports (a debug toolbar in `development`, an error tracker in `production`). See [pluginmanager](https://github.com/echo-fusion/pluginmanager).
