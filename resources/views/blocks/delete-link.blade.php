<a href="{{ route($routePart . '.edit', $model) }}" class="card-link">Редактировать</a>
<a href="#" class="card-link link-danger"
    onclick="event.preventDefault(); if (confirm('Are you sure you want to delete?')) { document.getElementById('delete-form').submit(); }">Удалить</a>
<form id="delete-form" action="{{ route($routePart . '.destroy', $model) }}" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>