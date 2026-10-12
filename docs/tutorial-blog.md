# Tutorial: how the example blog works

The [example blog](../examples/blog) is a finished application. This page walks through it feature by feature and points at the code, so you can see how the pieces of the framework fit together. Install it first and click around:

```bash
composer create-project echo-fusion/echo-fusion my-blog dev-blog
cd my-blog
composer serve        # http://localhost:8000
```

Demo accounts (password `password`): `admin@example.com`, `ann@example.com`, `bob@example.com`.

## 1. A request, from URL to page

Open `/posts/3`.

1. `public/index.php` creates the application and runs it ([lifecycle](lifecycle.md)).
2. `config/routes.php` has the route `'posts.show' => ['method' => 'GET', 'path' => '/posts/{id}', 'action' => [PostController::class, 'show'], 'constraints' => ['id' => '[0-9]+']]`.
3. The framework builds `PostController` (its constructor asks for the repository, the validator and the view helper; the container supplies them) and calls `show(Request $request, int $id)`: `$id` is `3`, already an `int`.
4. `show()` asks `PostRepository` for the row, renders `resources/templates/posts/show.tpl` and returns the string, which becomes the HTML response.
5. A post that does not exist throws `HttpException(404)`; the error handler renders the error page.

Try it: `php bin/console route:list` prints every route with its middleware.

## 2. Reading what the user sent

`PostController::index()` shows the three most used calls:

```php
$page = max(1, $request->int('page', 1));         // /?page=2
$search = trim($request->string('q'));            // /?q=routing
```

A form works the same way, and `PostValidator` reads the fields with `$request->string('title')` and `$request->bool('publish')` (a checkbox that is not ticked is not sent). The JSON API reads `$request->input('title')`, which finds the value in a JSON body, a form body or the query string, for `POST`, `PUT` and `PATCH` alike. See [Controllers and the Request](controllers.md).

## 3. Forms that are safe

Look at `resources/templates/posts/_fields.tpl`: every form contains `<input type="hidden" name="_token" value="{{ csrf }}">`. The `VerifyCsrfToken` middleware (listed in `config/middleware.php`) rejects a post without the right token with `419`. Remove the line, submit the form, and see it happen. The token is added to every page by `App\Support\View`, so no controller repeats it. See [Sessions and CSRF](sessions-and-csrf.md).

After a successful form the controller answers with a **redirect**, never with a page:

```php
return $this->redirect->to('/posts/' . $id, 'Your post was saved.');   // 303 + a flash message
```

When validation fails it redirects back with the errors and what the user typed, so the form refills:

```php
return $this->redirect->withErrors('/posts/create', $errors, $this->validator->old($request));
```

## 4. Accounts

`AuthController` registers, signs in and signs out:

* **register**: validates, then `DatabaseUserProvider::create()` hashes the password (bcrypt) and inserts the row; the user is signed in with `statefulGuard('web')->login($user)`.
* **sign in**: `statefulGuard('web')->attempt(['email' => ..., 'password' => ...])` finds the user and verifies the hash.
* **sign out**: `->logout()`.

Which pages need a signed-in user is declared in the routes: `'middlewares' => ['auth']` (and `['guest']` for the sign-in and register pages). Settings are in `config/auth.php`. See [Authentication](authentication.md).

## 5. Who may change what

Being signed in is not enough to edit a post. `App\Support\Access` holds the rule (the author or an admin), and the controller checks it:

```php
if (!$this->access->canManage($request->user(), $post)) {
    throw new HttpException(403, 'You can only change your own posts.');
}
```

Drafts follow the same rule: `PostController::show()` answers `404` for a draft unless `canManage()` is true. Test it: sign in as Ann, open a post by Bob, change the URL to `/posts/2/edit`.

## 6. The admin panel

Everything under `/admin` is defined in one place at the top of `config/routes.php`:

```php
$admin = static function (array $routes): array {
    // adds the /admin prefix and ['auth', 'admin'] to each route
};
```

`auth` makes sure somebody is signed in; `admin` is `App\Http\Middleware\RequireAdmin`, a normal PSR-15 middleware that checks the role and throws `403`. The controllers are in `app/Http/Controllers/Admin/`: dashboard, posts (create, edit, publish or unpublish, delete, search) and users (change the role, delete; the last administrator cannot be demoted and you cannot delete yourself). To add another admin page, add a controller and one line in the `$admin([...])` list.

## 7. The database

SQL lives in `app/Repositories/`; controllers never write queries. The tables are created by `database/migrations/*.php`, which you ran with `php bin/console migrate`. To add a column:

```bash
php bin/console make:migration add_slug_to_posts
```

edit `up()` and `down()` in the new file, then `php bin/console migrate`. `php bin/console migrate:status` shows what has run. `php bin/console db:seed` (your own command, `app/Console/Commands/SeedCommand.php`) creates the demo data. See [Database and migrations](database.md).

## 8. The JSON API

`/api/*` is a second front door to the same data. It uses the `api` middleware, which is the `auth` middleware with the token guard:

```bash
curl http://localhost:8000/api/posts                                   # public
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/me    # token from /account
curl -X PUT -H "Authorization: Bearer $TOKEN" -d 'title=New&body=Text' http://localhost:8000/api/posts/1
```

An action that returns an array is answered as JSON, and one that returns nothing as `204`. API paths are excluded from CSRF in `config/app.php` because they are authenticated by a header, not a cookie.

## 9. Tests

```bash
composer test
```

`tests/ApiTest.php` sends requests through the whole application (routing, middleware, controller, database) without a web server, against a throw-away SQLite file built by the real migrations. Start there when you add a feature: write the request you expect to work, then the code. See [Testing](testing.md).

## Your turn

* Add a `comments` table (`make:migration`), a `CommentRepository`, a route that posts a comment (`['auth']`), and show the comments in `posts/show.tpl`.
* Add a console command `posts:publish-all` (`make:command`).
* Replace the database layer with Doctrine DBAL: see [Database](database.md#another-library-doctrine-an-orm-).
