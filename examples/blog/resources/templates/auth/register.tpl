{% extends "layouts/app" %}

{% block content %}
<h1>Register</h1>
<form method="post" action="/register">
    <input type="hidden" name="_token" value="{{ csrf }}">
    <label for="name">Name</label>
    <input id="name" type="text" name="name" value="{{ old.name }}" required autofocus>
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="{{ old.email }}" required>
    <label for="password">Password (at least 8 characters)</label>
    <input id="password" type="password" name="password" minlength="8" required>
    <button type="submit">Create account</button>
</form>
<p class="muted">Already registered? <a href="/login">Sign in</a>.</p>
{% endblock %}
