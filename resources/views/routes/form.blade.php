@extends(config('acl.layout'))

@section('title', $aclRoute->exists ? 'Редактирование шаблона' : 'Новый шаблон')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a class="text-decoration-none text-black-50"
                                   href="{{ route('acl.routes.index') }}">Маршруты ACL</a></li>
    <li class="breadcrumb-item active">{{ $aclRoute->exists ? $aclRoute->pattern : 'Новый' }}</li>
@endsection

@section('content')
    <h4 class="mb-3">{{ $aclRoute->exists ? 'Редактирование шаблона' : 'Новый шаблон' }}</h4>

    <form method="POST" style="max-width: 640px"
          action="{{ $aclRoute->exists ? route('acl.routes.update', $aclRoute) : route('acl.routes.store') }}">
        @csrf
        @if ($aclRoute->exists)
            @method('PUT')
        @endif

        <div class="mb-3">
            <label for="pattern" class="form-label">Маршрут или шаблон</label>
            <input type="text" id="pattern" name="pattern" maxlength="191" required autofocus
                   class="form-control @error('pattern') is-invalid @enderror"
                   value="{{ old('pattern', $aclRoute->pattern) }}" placeholder="users.index, users.*, *">
            @error('pattern')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">
                Имя маршрута точно, либо префикс со звёздочкой в конце: <code>users.*</code> покрывает
                <code>users.index</code>, <code>users.edit</code> и т. д.; <code>*</code> — все маршруты.
                Регистр важен.
            </div>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Описание</label>
            <input type="text" id="description" name="description" maxlength="255"
                   class="form-control @error('description') is-invalid @enderror"
                   value="{{ old('description', $aclRoute->description) }}">
            @error('description')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Название пункта в боковом меню (если задана иконка).</div>
        </div>

        <div class="row">
            <div class="col-8 mb-3">
                <label for="icon" class="form-label">Иконка в меню</label>
                <input type="text" id="icon" name="icon" maxlength="64" placeholder="fa-users"
                       class="form-control @error('icon') is-invalid @enderror"
                       value="{{ old('icon', $aclRoute->icon) }}">
                @error('icon')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    Имя класса Font Awesome (<code>fa-users</code>, <code>fa-newspaper</code>...).
                    Пусто — маршрута нет в боковом меню.
                </div>
            </div>
            <div class="col-4 mb-3">
                <label for="order_index" class="form-label">Порядок</label>
                <input type="number" id="order_index" name="order_index" min="0" max="65535"
                       class="form-control @error('order_index') is-invalid @enderror"
                       value="{{ old('order_index', $aclRoute->order_index ?? 0) }}">
                @error('order_index')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        @if ($aclRoute->exists)
            <div class="alert alert-warning py-2">
                Переименование меняет доступ: все правила остаются привязаны к этой записи.
            </div>
        @endif

        <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-1"></i>Сохранить</button>
        <a href="{{ route('acl.routes.index') }}" class="btn btn-outline-secondary">Отмена</a>
    </form>
@endsection
