# Getting started

## Install

```bash
composer create-project echo-fusion/echo-fusion my-app
cd my-app
php bin/console migrate     # only if you use the database
composer serve              # http://localhost:8000
```

You need PHP 8.1, 8.2 or 8.3 with `mbstring` and `pdo` (plus the PDO driver of your database).

## What is in the folder

```
app/                    your code (namespace App\)
bin/console             the command line
config/                 one file per section: app, routes, middleware, database, auth ...
database/migrations/    migration files
public/index.php        the only file the web server runs
resources/templates/    templates
storage/                cache, sessions, logs (must be writable)
tests/                  your tests
.env                    settings of this machine (never commit it)
```

## Your first page

A route, a controller and a template.

```php
// config/routes.php
'about' => ['method' => 'GET', 'path' => '/about', 'action' => [PageController::class, 'about']],
```

```php
// app/Http/Controllers/PageController.php
final class PageController
{
    public function __construct(private readonly TemplateManagerInterface $templates) {}

    public function about(): string
    {
        return $this->templates->render('about', ['team' => ['Ann', 'Bob']]);
    }
}
```

```twig
{# resources/templates/about.tpl #}
{% extends "layouts/app" %}
{% block content %}
<h1>About us</h1>
<ul>{% for person in team %}<li>{{ person }}</li>{% endfor %}</ul>
{% endblock %}
```

Open `/about`. Generate the controller with `php bin/console make:controller Page`, and see every route with `php bin/console route:list`.

## Configuration and `.env`

Settings that differ between machines (database password, debug flag) go in `.env`, and config files read them with `env('NAME', 'default')`. Settings that differ between environments (production, testing) go in `config/environments/<APP_ENV>.php`, which is merged on top.

```php
use function EchoFusion\Framework\env;

return ['debug' => env('APP_DEBUG', false)];
```

Use `env()` in `config/` only. Everywhere else read configuration through the container or inject what you need.

Next: [how a request is handled](lifecycle.md).
