<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{% block title %}{{ appName }}{% endblock %}</title>
    <style>
        :root { color-scheme: light dark; --fg: #1b1f24; --bg: #ffffff; --muted: #5b6672; --accent: #3b5bdb; --line: #e3e6ea; --ok: #2b8a3e; --bad: #c92a2a; }
        @media (prefers-color-scheme: dark) { :root { --fg: #e8eaed; --bg: #16181d; --muted: #9aa3ad; --accent: #8da2fb; --line: #2a2e36; --ok: #69db7c; --bad: #ff8787; } }
        body { margin: 0; font: 16px/1.6 system-ui, sans-serif; color: var(--fg); background: var(--bg); }
        header { border-bottom: 1px solid var(--line); }
        nav, main { max-width: 44rem; margin: 0 auto; padding: 1rem 1.25rem; }
        nav { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        nav .brand { font-weight: 700; margin-right: auto; text-decoration: none; color: var(--fg); }
        nav form { display: inline; margin: 0; }
        a { color: var(--accent); }
        h1 { margin: .5rem 0 1rem; font-size: 1.8rem; }
        .muted { color: var(--muted); font-size: .9rem; }
        .post { padding: 1rem 0; border-bottom: 1px solid var(--line); }
        .post h2 { margin: 0; font-size: 1.25rem; }
        .body { white-space: pre-wrap; }
        label { display: block; margin-top: 1rem; font-weight: 600; }
        input[type=text], input[type=email], input[type=password], textarea { width: 100%; box-sizing: border-box; padding: .5rem; font: inherit; color: inherit; background: transparent; border: 1px solid var(--line); border-radius: 6px; }
        textarea { min-height: 12rem; }
        button, .button { display: inline-block; margin-top: 1rem; padding: .5rem 1rem; font: inherit; color: #fff; background: var(--accent); border: 0; border-radius: 6px; cursor: pointer; text-decoration: none; }
        button.link { margin: 0; padding: 0; color: var(--accent); background: none; text-decoration: underline; }
        button.danger { background: var(--bad); }
        .flash { padding: .6rem .9rem; border-radius: 6px; margin: 1rem 0; border: 1px solid var(--ok); color: var(--ok); }
        .errors { border-color: var(--bad); color: var(--bad); }
        .errors p, .flash p { margin: 0; }
        code, pre { border: 1px solid var(--line); border-radius: 4px; padding: .1rem .3rem; }
        pre { padding: .75rem; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .4rem .5rem; border-bottom: 1px solid var(--line); vertical-align: top; }
        .badge { display: inline-block; padding: 0 .5rem; font-size: .75rem; border: 1px solid var(--line); border-radius: 999px; color: var(--muted); }
        .badge.draft { border-color: var(--bad); color: var(--bad); }
        .search { display: flex; gap: .5rem; margin: 0 0 1rem; }
        .search input { flex: 1; }
        .search button { margin: 0; }
        .pager { display: flex; justify-content: space-between; margin-top: 1rem; }
    </style>
</head>
<body>
<header>
    <nav>
        <a class="brand" href="/">{{ appName }}</a>
        {% if me %}
            <a href="/posts/create">Write</a>
            <a href="/my-posts">My posts</a>
            {% if me.admin %}<a href="/admin">Admin</a>{% endif %}
            <a href="/account">API token</a>
            <span class="muted">{{ me.name }}</span>
            <form method="post" action="/logout"><input type="hidden" name="_token" value="{{ csrf }}"><button class="link" type="submit">Sign out</button></form>
        {% else %}
            <a href="/login">Sign in</a>
            <a href="/register">Register</a>
        {% endif %}
    </nav>
</header>
<main>
{% for message in success %}<div class="flash"><p>{{ message }}</p></div>{% endfor %}
{% if errors %}<div class="flash errors">{% for message in errors %}<p>{{ message }}</p>{% endfor %}</div>{% endif %}
{% block content %}{% endblock %}
</main>
</body>
</html>
