<input type="hidden" name="_token" value="{{ csrf }}">
<label for="title">Title</label>
<input id="title" type="text" name="title" value="{{ old.title ?? post.title }}" maxlength="200" required>
<label for="body">Text</label>
<textarea id="body" name="body" required>{{ old.body ?? post.body }}</textarea>
<label><input type="checkbox" name="publish" value="1" {% if (old.status ?? post.status) == 'published' %}checked{% endif %}> Publish (untick to keep it as a draft)</label>
<button type="submit">Save</button>
