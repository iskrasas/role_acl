@extends(config('acl.layout'))

@section('title', 'Маршруты ACL')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Маршруты ACL</li>
@endsection

@section('content')
    <style>
        #routes-table thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            box-shadow: 0 2px 0 #e2e8f0;
        }
    </style>

    <h4 class="mb-1">Маршруты и шаблоны</h4>
    <p class="text-muted mb-3">
        Имя маршрута (<code>users.index</code>) или шаблон-префикс (<code>users.*</code>, <code>*</code>).
        «Совпадений» — сколько существующих именованных маршрутов покрывает шаблон; 0 значит, что маршрут
        переименован или удалён. Удаление шаблона удаляет и все его правила.
        В боковом меню показываются разрешённые маршруты <code>*.index</code> с заполненной иконкой, по возрастанию порядка.
    </p>

    @if (session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger py-2">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="row g-2 mb-3">
        <div class="col-4">
            <input type="search" id="filter-q" class="form-control form-control-sm" placeholder="Поиск по шаблону...">
        </div>
        <div class="col-3">
            <select id="filter-state" class="form-select form-select-sm">
                <option value="">Все</option>
                <option value="stale">Без совпадений</option>
                <option value="wildcard">Только шаблоны</option>
                <option value="menu">Только в меню</option>
            </select>
        </div>
    </div>

    <table class="table table-hover align-middle" id="routes-table">
        <thead>
        <tr>
            <th>Маршрут / шаблон</th>
            <th>Описание</th>
            <th>Иконка</th>
            <th class="text-end">Порядок</th>
            <th class="text-end">Совпадений</th>
            <th class="text-end">Правил (групп / сотр.)</th>
            <th class="text-end">
                <a href="{{ route('acl.routes.create') }}" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus me-1"></i>Добавить
                </a>
            </th>
        </tr>
        </thead>
        <tbody>
        @foreach ($routes as $route)
            @php($count = $matches[$route->pattern] ?? 0)
            @php($isWildcard = str_contains($route->pattern, '*'))
            <tr data-pattern="{{ $route->pattern }}" data-stale="{{ $count === 0 ? 1 : 0 }}"
                data-wildcard="{{ $isWildcard ? 1 : 0 }}" data-menu="{{ filled($route->icon) ? 1 : 0 }}">
                <td class="text-nowrap">
                    <code>{{ $route->pattern }}</code>
                    @if ($isWildcard)
                        <span class="badge bg-secondary ms-1">шаблон</span>
                    @endif
                </td>
                <td class="text-muted text-break" style="max-width: 32rem">{{ $route->description }}</td>
                <td class="text-nowrap">
                    @if (filled($route->icon))
                        <i class="fas {{ $route->icon }} me-1"></i><code>{{ $route->icon }}</code>
                    @endif
                </td>
                <td class="text-end">{{ $route->order_index }}</td>
                <td class="text-end">
                    <span class="badge {{ $count === 0 ? 'bg-warning text-dark' : 'bg-success' }}">{{ $count }}</span>
                </td>
                <td class="text-end">{{ $route->group_permissions_count }} / {{ $route->staff_permissions_count }}</td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('acl.routes.edit', $route) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-pen"></i>
                    </a>
                    <form method="POST" action="{{ route('acl.routes.destroy', $route) }}" class="d-inline"
                          data-confirm="Удалить «{{ $route->pattern }}»? Будут удалены правила: {{ $route->group_permissions_count + $route->staff_permissions_count }}"
                          onsubmit="return confirm(this.dataset.confirm)">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection

@section('scripts')
    <script>
        (function () {
            const q = document.getElementById('filter-q');
            const kind = document.getElementById('filter-state');
            const rows = document.querySelectorAll('#routes-table tbody tr[data-pattern]');

            function apply() {
                const text = q.value.trim().toLowerCase();
                rows.forEach(function (row) {
                    const okText = !text || row.dataset.pattern.toLowerCase().includes(text);
                    const okKind = !kind.value
                        || (kind.value === 'stale' && row.dataset.stale === '1')
                        || (kind.value === 'wildcard' && row.dataset.wildcard === '1')
                        || (kind.value === 'menu' && row.dataset.menu === '1');
                    row.hidden = !(okText && okKind);
                });
            }

            q.addEventListener('input', apply);
            kind.addEventListener('change', apply);
        })();
    </script>
@endsection
