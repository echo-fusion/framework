{% extends "layouts/app" %}

{% block content %}
<h1>{{ heading }}</h1>
<form method="post" action="{{ action }}">
    <input type="hidden" name="_token" value="{{ csrf }}">
    <label for="title">Title</label>
    <input id="title" type="text" name="title" value="{{ old.title ?? post.title }}" maxlength="200" required>
    <label for="body">Text</label>
    <textarea id="body" name="body" required>{{ old.body ?? post.body }}</textarea>
    <button type="submit">Save</button>
</form>
{% endblock %}
