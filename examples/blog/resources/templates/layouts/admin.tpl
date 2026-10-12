{% extends "layouts/app" %}

{% block content %}
<p class="muted"><a href="/admin">Dashboard</a> &middot; <a href="/admin/posts">Posts</a> &middot; <a href="/admin/users">Users</a> &middot; <a href="/">View the site</a></p>
{% block admin %}{% endblock %}
{% endblock %}
