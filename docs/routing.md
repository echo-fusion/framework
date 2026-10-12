# Routing

Routes live in `config/routes.php`. The array key is the route's name.

```php
return [
    'home'  => ['method' => 'GET',  'path' => '/', 'action' => [HomeController::class, 'index']],
    'post'  => ['method' => 'GET',  'path' => '/posts/{id}', 'action' => [PostController::class, 'show'],
                'constraints' => ['id' => '[0-9]+']],
    'store' => ['method' => 'POST', 'path' => '/posts', 'action' => [PostController::class, 'store'],
                'middlewares' => ['auth']],
];
```

| Key | Meaning |
| --- | --- |
| `method` | `GET`, `POST`, `PUT`, `PATCH`, `DELETE` or `OPTIONS` |
| `path` | `/posts/{id}`; `{id:\d+}` puts the regex inside the placeholder |
| `action` | `[Controller::class, 'method']`, an invokable class or a closure |
| `constraints` | regex per placeholder. Without one a placeholder matches letters, digits, `_` and `-` |
| `middlewares` | names from `config/middleware.php` |

## Things to know

* A placeholder becomes an argument of the action with the same name: `show(int $id)`. It is URL-decoded and cast to the declared scalar type.
* Put fixed paths before placeholders, or constrain the placeholder: `/posts/create` must not be swallowed by `/posts/{id}`. `[0-9]+` on `{id}` solves it.
* A path that exists with another method answers `405 Method Not Allowed` with an `Allow` header. `HEAD` is answered by `GET` routes. A trailing slash is ignored.
* Browsers can only send `GET` and `POST` from a form. Use `POST` routes for form actions such as `/posts/{id}/delete`; API clients can use `PUT`, `PATCH` and `DELETE` directly.

## Routes as code

```php
return static function (RouteRegistrarInterface $router): void {
    $router->get('home', '/', [HomeController::class, 'index']);
};
```

## Middleware names

```php
// config/middleware.php
return [
    'core'  => [StartSessionMiddleware::class, VerifyCsrfToken::class],   // every request, in order
    'route' => ['auth' => AuthenticateMiddleware::class, 'guest' => GuestMiddleware::class],
];
```

Any PSR-15 middleware works. It is built by the container when a request reaches it, so its constructor can ask for services. List routes with `php bin/console route:list --method=POST`.
