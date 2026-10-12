{% extends "layouts/app" %}

{% block title %}{{ status }}{% endblock %}

{% block content %}
<h1>{{ status }}</h1>
<p>{{ message }}</p>
{% if exception %}<pre>{{ exception.message }}</pre>{% endif %}
<p><a href="/">Back to the home page</a></p>
{% endblock %}
