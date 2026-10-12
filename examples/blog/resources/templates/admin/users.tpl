{% extends "layouts/admin" %}

{% block admin %}
<h1>Users</h1>
<table>
    <tr><th>#</th><th>Name</th><th>Email</th><th>Posts</th><th>Role</th><th></th></tr>
    {% for user in users %}
    <tr>
        <td>{{ user.id }}</td>
        <td>{{ user.name }}</td>
        <td>{{ user.email }}</td>
        <td>{{ user.posts }}</td>
        <td>
            <form method="post" action="/admin/users/{{ user.id }}/role" style="display:inline">
                <input type="hidden" name="_token" value="{{ csrf }}">
                <input type="hidden" name="role" value="{{ user.role == 'admin' ? 'user' : 'admin' }}">
                <span class="badge">{{ user.role }}</span>
                <button class="link" type="submit">{{ user.role == 'admin' ? 'Make regular user' : 'Make admin' }}</button>
            </form>
        </td>
        <td>
            <form method="post" action="/admin/users/{{ user.id }}/delete" style="display:inline" onsubmit="return confirm('Delete this user and all their posts?')"><input type="hidden" name="_token" value="{{ csrf }}"><button class="link" type="submit">Delete</button></form>
        </td>
    </tr>
    {% endfor %}
</table>
{% endblock %}
