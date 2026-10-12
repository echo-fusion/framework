# Templates

Templates live in `resources/templates`. `.tpl` is the built-in language (like Twig or Smarty, no Symfony or Laravel needed), `.php` is plain PHP. Render from a controller:

```php
return $this->templates->render('posts/show', ['post' => $post]);   // resources/templates/posts/show.tpl
```

## The language

```twig
{# comment #}
<h1>{{ post.title }}</h1>                        {# escaped for HTML automatically #}
<div>{{ post.html|raw }}</div>                    {# raw = not escaped: only for trusted HTML #}
{{ post.body|truncate(160) }}  {{ post.created_at|date('j M Y') }}  {{ name|default('anonymous') }}

{% if me %}Hello {{ me.name }}{% elseif guest %}Hi{% else %}Sign in{% endif %}

{% for post in posts %}
  {{ loop.index }}. {{ post.title }}
{% else %}
  Nothing here yet.
{% endfor %}

{% set total = price * qty %}
{% include "partials/footer" %}
```

* `a.b.c` reads array keys, `ArrayAccess`, public properties and getters (`getB()`, `isB()`). A missing value prints nothing.
* Operators: `+ - * / %`, `~` (join strings), comparison, `and or not`, `?:` and `??`. Function calls are not allowed; use filters.
* Filters: `upper lower capitalize title trim length default join keys first last reverse date json number truncate replace url_encode raw escape`; chain them: `{{ name|trim|upper }}`.
* Loops give `loop.index`, `loop.index0`, `loop.first`, `loop.last`, `loop.length`.

## Layouts

```twig
{# layouts/app.tpl #}
<title>{% block title %}{{ appName }}{% endblock %}</title>
<main>{% block content %}{% endblock %}</main>
```

```twig
{# posts/show.tpl #}
{% extends "layouts/app" %}
{% block content %}<h1>{{ post.title }}</h1>{% endblock %}
```

## Variables every template gets

`config/templates.php` has `'globals' => ['appName' => ...]`. Anything else (the signed-in user, flash messages, the CSRF token) you pass when rendering. The example application does this once in [`App\Support\View`](../examples/blog/app/Support/View.php) so no controller repeats it.

## Error pages

A template named `errors/<status>` (`errors/404.tpl`) or `errors/error.tpl` is used for errors. It receives `status`, `message` and, in debug mode, `exception`.

## Caching

Compiled templates go to `storage/cache/templates`. With `auto_reload` on (`APP_DEBUG=true`) they are rebuilt when the source changes; in production clear them after a deployment with `php bin/console cache:clear`.

## Twig or another engine

```bash
composer require twig/twig
```

```php
// config/templates.php
'engines' => ['tpl' => [...], 'php' => [], 'twig' => []],
```

`render('home.twig')` picks the engine by extension. Your own engine implements `TemplateEngineInterface`; see the [templatemanager](https://github.com/echo-fusion/templatemanager) README.
