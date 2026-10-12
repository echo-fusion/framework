# EchoFusion Blog

A small, complete application built with the [EchoFusion framework](../../README.md): accounts, posts with create / edit / delete and drafts, search and pagination, an administration panel under `/admin`, CSRF-protected forms, flash messages, validation, a SQLite database filled by migrations, demo data, and a JSON API with token authentication.

Read the code in this order: `config/routes.php`, `app/Http/Controllers/PostController.php`, `app/Repositories/PostRepository.php`, `resources/templates/posts/`.

## Run it

```bash
cd examples/blog
composer install
cp .env.example .env
php bin/console migrate
php bin/console db:seed        # 3 demo accounts and 12 posts
php bin/console serve          # http://localhost:8000
```

Open <http://localhost:8000>. (`php bin/console serve --port=8080` picks another port.)

### Demo accounts

`db:seed` creates these, all with the password `password` (local demos only):

| Email | Role |
| --- | --- |
| `admin@example.com` | administrator: can open `/admin` |
| `ann@example.com`, `bob@example.com` | authors |

Or register your own account at `/register`: it is a regular user.

## What to try

| In the browser | What it shows |
| --- | --- |
| `/?q=routing&page=2` | search and pagination with `$request->string('q')` and `$request->int('page')` |
| `/my-posts` | an author's own posts, drafts included |
| the "Publish" checkbox | a draft is visible only to its author and to admins (everyone else gets 404) |
| `/admin` (as admin) | dashboard, **posts** (search, create, edit, publish / unpublish, delete), **users** (make admin, delete) |
| `/admin` (as author) | `403`: the `admin` middleware |
| `/register`, `/login` | forms with CSRF token, validation errors that refill the form, session guard |
| `/posts/create`, edit and delete on a post | routes behind the `auth` middleware, ownership check (403 for other people's posts) |
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

## The admin area, step by step

`config/routes.php` builds the admin routes with a small helper that adds the `/admin` prefix and the `['auth', 'admin']` middleware to a list of routes, so the rule lives in one place. `auth` makes sure somebody is signed in; `admin` (`App\Http\Middleware\RequireAdmin`) checks the role and throws `HttpException(403)`. Public pages that behave differently for admins (a draft, the Edit link) ask `App\Support\Access`.

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

`tests/AccessTest.php` covers the permission rules and the admin middleware. `tests/ApiTest.php` sends requests through the whole application (routing, middleware, controller, database) without a web server. It uses its own `storage/testing.sqlite`.

## Layout

```
app/Console/Commands/    db:seed
app/Http/Controllers/    PostController, AuthController, AccountController, ApiController
app/Http/Controllers/Admin/   the panel under /admin: Dashboard, Post, User
app/Http/Middleware/     AuthenticateApi (the `auth` middleware with the token guard), RequireAdmin
app/Repositories/        SQL lives here
app/Support/             View (adds csrf, user, flash messages to every page), Redirect, Access (who may change what), PostValidator
config/                  routes, middleware, auth guards, database ...
database/migrations/     users and posts tables
resources/templates/     layout, posts, auth, account
```
