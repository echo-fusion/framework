# EchoFusion Blog

A small, complete application built with the [EchoFusion framework](../../README.md): accounts, posts with create / edit / delete, CSRF-protected forms, flash messages, validation, a SQLite database filled by migrations, and a JSON API with token authentication.

Read the code in this order: `config/routes.php`, `app/Http/Controllers/PostController.php`, `app/Repositories/PostRepository.php`, `resources/templates/posts/`.

## Run it

```bash
cd examples/blog
composer install
cp .env.example .env
php bin/console migrate
php bin/console serve          # http://localhost:8000
```

Open <http://localhost:8000>, register, write a post. (`php bin/console serve --port=8080` picks another port.)

## What to try

| In the browser | What it shows |
| --- | --- |
| `/register`, `/login` | forms with CSRF token, validation errors that refill the form, session guard |
| `/posts/create`, edit and delete on a post | routes behind the `auth` middleware, ownership check (403 for other people's posts) |
| `/?page=2` | `$request->int('page')` |
| `/account` | creates an API token (shown once, stored as a SHA-256 hash) |
| a post that does not exist | the error page template |

## The JSON API

```bash
TOKEN=...   # from /account
curl http://localhost:8000/api/posts?limit=5
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/me

# JSON body
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
     -d '{"title":"Hello","body":"From curl"}' http://localhost:8000/api/posts

# PUT / PATCH / DELETE: JSON or form-encoded bodies, both are read by $request->input()
curl -X PUT   -H "Authorization: Bearer $TOKEN" -d 'title=New title&body=New text' http://localhost:8000/api/posts/1
curl -X PATCH -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"title":"Only the title"}' http://localhost:8000/api/posts/1
curl -X DELETE -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/posts/1
```

Add `-H "Accept: application/json"` to get errors as JSON instead of an HTML page.

## Where the data of a request comes from

A controller action asks for `EchoFusion\Framework\Http\Request` in its signature; the framework passes it in. No middleware is needed:

```php
public function update(Request $request, int $id): array   // $id is the {id} of the route
{
    $request->query('page');          // ?page=2
    $request->post('title');          // form field (POST, and also PUT / PATCH / DELETE forms)
    $request->json('title');          // JSON body
    $request->input('title');         // form, JSON or query: whatever the client sent
    $request->only('title', 'body');  // several at once
    $request->user();                 // the signed-in user (routes with the auth middleware)
    $request->csrfToken();            // for forms
}
```

## Tests

```bash
composer test
```

`tests/ApiTest.php` sends requests through the whole application (routing, middleware, controller, database) without a web server. It uses its own `storage/testing.sqlite`.

## Layout

```
app/Http/Controllers/    PostController, AuthController, AccountController, ApiController
app/Http/Middleware/     AuthenticateApi: the `auth` middleware with the token guard
app/Repositories/        SQL lives here
app/Support/             View (adds csrf, user, flash messages to every page), Redirect
config/                  routes, middleware, auth guards, database ...
database/migrations/     users and posts tables
resources/templates/     layout, posts, auth, account
```
