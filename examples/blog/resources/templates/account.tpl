{% extends "layouts/app" %}

{% block content %}
<h1>API token</h1>
{% if token %}
<div class="flash"><p>Copy your token now. It is not shown again, only its hash is stored.</p></div>
<pre>{{ token }}</pre>
<p>Try it:</p>
<pre>curl -H "Authorization: Bearer {{ token }}" {{ 'http://localhost:8000/api/me' }}</pre>
{% else %}
<p>{% if hasToken %}You have a token. Creating a new one replaces it.{% else %}You have no token yet.{% endif %}</p>
{% endif %}
<form method="post" action="/account/token">
    <input type="hidden" name="_token" value="{{ csrf }}">
    <button type="submit">{{ hasToken ? 'Create a new token' : 'Create a token' }}</button>
</form>
<h2>The API</h2>
<ul>
    <li><code>GET /api/posts?limit=10&amp;offset=0</code> - public</li>
    <li><code>GET /api/posts/{id}</code> - public</li>
    <li><code>GET /api/me</code> - needs the token</li>
    <li><code>POST /api/posts</code> with JSON <code>{"title": "...", "body": "..."}</code> - needs the token</li>
</ul>
{% endblock %}
