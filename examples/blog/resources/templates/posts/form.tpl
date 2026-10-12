{% extends "layouts/app" %}

{% block content %}
<h1>{{ heading }}</h1>
<form method="post" action="{{ action }}">
    {% include "posts/_fields" %}
</form>
{% endblock %}
