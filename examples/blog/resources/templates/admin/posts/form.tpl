{% extends "layouts/admin" %}

{% block admin %}
<h1>{{ heading }}</h1>
<form method="post" action="{{ action }}">
    {% include "posts/_fields" %}
</form>
{% endblock %}
