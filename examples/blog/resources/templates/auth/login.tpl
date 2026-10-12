{% extends "layouts/app" %}

{% block content %}
<h1>Sign in</h1>
<form method="post" action="/login">
    <input type="hidden" name="_token" value="{{ csrf }}">
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="{{ old.email }}" required autofocus>
    <label for="password">Password</label>
    <input id="password" type="password" name="password" required>
    <button type="submit">Sign in</button>
</form>
<p class="muted">No account yet? <a href="/register">Register</a>.</p>
{% endblock %}
