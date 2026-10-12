{% extends "layouts/app" %}

{% block content %}
<h1>Latest posts</h1>
{% for post in posts %}
<article class="post">
    <h2><a href="/posts/{{ post.id }}">{{ post.title }}</a></h2>
    <div class="muted">by {{ post.author }} on {{ post.created_at|date('j M Y') }}</div>
    <p>{{ post.body|truncate(160) }}</p>
</article>
{% else %}
<p>No posts yet. {% if me %}<a href="/posts/create">Write the first one.</a>{% else %}<a href="/register">Register</a> and write the first one.{% endif %}</p>
{% endfor %}
<div class="pager">
    <span>{% if previous %}<a href="/?page={{ previous }}">&larr; Newer</a>{% endif %}</span>
    <span>{% if next %}<a href="/?page={{ next }}">Older &rarr;</a>{% endif %}</span>
</div>
{% endblock %}
