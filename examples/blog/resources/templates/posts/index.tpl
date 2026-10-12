{% extends "layouts/app" %}

{% block content %}
<h1>{{ heading }}</h1>
{% if base == '/' %}
<form class="search" method="get" action="/">
    <input type="text" name="q" value="{{ search }}" placeholder="Search posts">
    <button type="submit">Search</button>
</form>
{% endif %}
{% for post in posts %}
<article class="post">
    <h2><a href="/posts/{{ post.id }}">{{ post.title }}</a> {% if post.status == 'draft' %}<span class="badge draft">draft</span>{% endif %}</h2>
    <div class="muted">by {{ post.author }} on {{ post.created_at|date('j M Y') }}</div>
    <p>{{ post.body|truncate(160) }}</p>
</article>
{% else %}
<p>No posts here. {% if me %}<a href="/posts/create">Write one.</a>{% else %}<a href="/register">Register</a> to write one.{% endif %}</p>
{% endfor %}
<div class="pager">
    <span>{% if previous %}<a href="{{ base }}?page={{ previous }}&amp;q={{ search|url_encode }}">&larr; Newer</a>{% endif %}</span>
    <span>{% if next %}<a href="{{ base }}?page={{ next }}&amp;q={{ search|url_encode }}">Older &rarr;</a>{% endif %}</span>
</div>
{% endblock %}
