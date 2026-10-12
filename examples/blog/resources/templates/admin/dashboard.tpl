{% extends "layouts/admin" %}

{% block admin %}
<h1>Administration</h1>
<table>
    <tr><th>Posts</th><td>{{ stats.total }} ({{ stats.published }} published, {{ stats.drafts }} drafts)</td></tr>
    <tr><th>Users</th><td>{{ stats.users }} ({{ stats.admins }} administrators)</td></tr>
</table>
<h2>Latest posts</h2>
{% for post in latest %}
<div class="post"><a href="/admin/posts/{{ post.id }}/edit">{{ post.title }}</a> {% if post.status == 'draft' %}<span class="badge draft">draft</span>{% endif %} <span class="muted">by {{ post.author }}</span></div>
{% endfor %}
<p><a class="button" href="/admin/posts/create">New post</a></p>
{% endblock %}
