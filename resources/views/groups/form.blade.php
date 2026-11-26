@extends(config('acl.layout'))

@section('title', $group->exists ? 'Редактирование группы' : 'Новая группа')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a class="text-decoration-none text-black-50"
                                   href="{{ route('acl.groups.index') }}">Группы и права</a></li>
    <li class="breadcrumb-item active">{{ $group->exists ? $group->name : 'Новая' }}</li>
@endsection

@section('content')
    <h4 class="mb-3">{{ $group->exists ? 'Редактирование группы' : 'Новая группа' }}</h4>

    <form method="POST" style="max-width: 640px"
          action="{{ $group->exists ? route('acl.groups.update', $group) : route('acl.groups.store') }}">
        @csrf
        @if ($group->exists)
            @method('PUT')
        @endif

        <div class="mb-3">
            <label for="name" class="form-label">Имя</label>
            <input type="text" id="name" name="name" maxlength="64" required autofocus
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $group->name) }}">
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Латиница, цифры, <code>_</code>, <code>-</code>, <code>.</code>. Регистр важен.</div>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Описание</label>
            <input type="text" id="description" name="description" maxlength="255"
                   class="form-control @error('description') is-invalid @enderror"
                   value="{{ old('description', $group->description) }}">
            @error('description')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Сохранить</button>
        <a href="{{ route('acl.groups.index') }}" class="btn btn-outline-secondary">Отмена</a>
    </form>
@endsection
