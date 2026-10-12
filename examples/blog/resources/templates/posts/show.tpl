{% extends "layouts/app" %}

{% block title %}{{ post.title }} - {{ appName }}{% endblock %}

{% block content %}
<h1>{{ post.title }} {% if post.status == 'draft' %}<span class="badge draft">draft</span>{% endif %}</h1>
<div class="muted">by {{ post.author }} on {{ post.created_at|date('j M Y') }}</div>
<p class="body">{{ post.body }}</p>
{% if canManage %}
<p>
    <a href="/posts/{{ post.id }}/edit">Edit</a>
    <form method="post" action="/posts/{{ post.id }}/delete" style="display:inline" onsubmit="return confirm('Delete this post?')">
        <input type="hidden" name="_token" value="{{ csrf }}">
        <button class="link" type="submit">Delete</button>
    </form>
</p>
{% endif %}
<p><a href="/">&larr; All posts</a></p>
{% endblock %}
