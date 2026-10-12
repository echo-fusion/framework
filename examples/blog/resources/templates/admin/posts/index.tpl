{% extends "layouts/admin" %}

{% block admin %}
<h1>Posts ({{ total }})</h1>
<form class="search" method="get" action="/admin/posts">
    <input type="text" name="q" value="{{ search }}" placeholder="Search title or text">
    <button type="submit">Search</button>
</form>
<table>
    <tr><th>#</th><th>Title</th><th>Author</th><th>Status</th><th>Actions</th></tr>
    {% for post in posts %}
    <tr>
        <td>{{ post.id }}</td>
        <td><a href="/posts/{{ post.id }}">{{ post.title|truncate(60) }}</a></td>
        <td>{{ post.author }}</td>
        <td><span class="badge {{ post.status }}">{{ post.status }}</span></td>
        <td>
            <a href="/admin/posts/{{ post.id }}/edit">Edit</a>
            <form method="post" action="/admin/posts/{{ post.id }}/toggle" style="display:inline"><input type="hidden" name="_token" value="{{ csrf }}"><button class="link" type="submit">{{ post.status == 'published' ? 'Unpublish' : 'Publish' }}</button></form>
            <form method="post" action="/admin/posts/{{ post.id }}/delete" style="display:inline" onsubmit="return confirm('Delete this post?')"><input type="hidden" name="_token" value="{{ csrf }}"><button class="link" type="submit">Delete</button></form>
        </td>
    </tr>
    {% else %}
    <tr><td colspan="5">No posts.</td></tr>
    {% endfor %}
</table>
<div class="pager">
    <span>{% if previous %}<a href="/admin/posts?page={{ previous }}&amp;q={{ search|url_encode }}">&larr; Newer</a>{% endif %}</span>
    <span>{% if next %}<a href="/admin/posts?page={{ next }}&amp;q={{ search|url_encode }}">Older &rarr;</a>{% endif %}</span>
</div>
<p><a class="button" href="/admin/posts/create">New post</a></p>
{% endblock %}
