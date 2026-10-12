# Authentication

Authentication is configured in `config/auth.php`: **guards** (how a request is authenticated) and **providers** (where users are stored).

```php
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'users'],      // browsers: the user id lives in the session
    'api' => ['driver' => 'token',   'provider' => 'users', 'header' => 'Authorization', 'scheme' => 'Bearer'],
],
'providers' => [
    'users' => ['driver' => 'database', 'table' => 'users', 'id' => 'id', 'password' => 'password', 'token' => 'api_token'],
],
'hasher' => ['algorithm' => 'bcrypt', 'options' => ['cost' => 12]],     // or argon2id
```

The `users` table needs `id`, `password` and (for tokens) `api_token`. The example's [migration](../examples/blog/database/migrations/2026_01_01_000001_create_users_table.php) shows it.

## Register and sign in (browsers)

```php
public function __construct(private readonly AuthManagerInterface $auth) {}

// register: create() hashes the password
$user = $provider->create(['name' => $name, 'email' => $email, 'password' => $password]);
$this->auth->statefulGuard('web')->login($user);

// sign in
if (!$this->auth->statefulGuard('web')->attempt(['email' => $email, 'password' => $password])) { /* wrong */ }

// sign out
$this->auth->statefulGuard('web')->logout();
```

The session id is regenerated on login and logout. Never store or compare passwords yourself: `attempt()` verifies the bcrypt/argon2id hash.

## Protect routes

```php
'posts.create' => [..., 'middlewares' => ['auth']],     // signed in, or redirect to /login (JSON clients get 401)
'login'        => [..., 'middlewares' => ['guest']],    // only for visitors who are not signed in
```

Roles are your own column and middleware: the example has an `admin` middleware ([RequireAdmin](../examples/blog/app/Http/Middleware/RequireAdmin.php)) used as `['auth', 'admin']`, and an `Access` class for the per-post rules.

Where they redirect is `auth.middleware` in `config/auth.php`. In a route with `auth`, `$request->user()` is the user. Authorization (may *this* user change *this* post?) is your code: compare ids and throw `HttpException(403)`, as in `PostController::owned()`.

## API tokens

The `api` guard reads `Authorization: Bearer <token>` and looks up `sha256(token)` in `users.api_token`. Store only the hash:

```php
$token = bin2hex(random_bytes(24));
$db->execute('UPDATE users SET api_token = ? WHERE id = ?', [hash('sha256', $token), $id]);   // show $token to the user once
```

The `auth` alias uses the `web` guard. For API routes add a middleware that uses the `api` guard, as the example does with [AuthenticateApi](../examples/blog/app/Http/Middleware/AuthenticateApi.php), and register it under a name in `config/middleware.php`. API routes should be excluded from CSRF (`app.csrf.except`), because they are authenticated by a header, not a cookie.

## Other users and guards

`array` provider (users in config, for demos and tests), your own `UserProviderInterface`, your own guard: all are replaced through configuration or the container. See the [authmanager](https://github.com/echo-fusion/authmanager) README.
