@extends(config('acl.layout'))

@section('title', 'Права группы ' . $group->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a class="text-decoration-none text-black-50"
                                   href="{{ route('acl.groups.index') }}">Группы и права</a></li>
    <li class="breadcrumb-item active">{{ $group->name }}</li>
@endsection

@section('content')
    <style>
        /* header row stays visible while scrolling the rules */
        #rules-table thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            box-shadow: 0 2px 0 #e2e8f0;
        }
    </style>

    <h4 class="mb-1">Права группы <code>{{ $group->name }}</code></h4>
    <p class="text-muted mb-3">
        «—» — правила нет (наследуется от более общего шаблона). Побеждает самый конкретный шаблон;
        при равной конкретности запрет сильнее разрешения. Без правил доступ закрыт.
        Изменения сразу применяются ко всем сотрудникам группы.
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
            <input type="search" id="filter-q" class="form-control form-control-sm" placeholder="Поиск по маршруту...">
        </div>
        <div class="col-3">
            <select id="filter-state" class="form-select form-select-sm">
                <option value="">Все правила</option>
                <option value="allow">Только разрешённые</option>
                <option value="deny">Только запрещённые</option>
                <option value="inherit">Без правила</option>
            </select>
        </div>
    </div>

    <form method="POST" action="{{ route('acl.groups.permissions.update', $group) }}">
        @csrf
        @method('PUT')

        <table class="table table-hover align-middle" id="rules-table">
            <thead>
            <tr>
                <th>Маршрут / шаблон</th>
                <th>Описание</th>
                <th class="text-end text-nowrap">
                    <a href="{{ route('acl.groups.index') }}" class="btn btn-sm btn-outline-secondary me-1">Отмена</a>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-floppy-disk me-1"></i>Сохранить
                    </button>
                </th>
            </tr>
            </thead>
            <tbody>
            @foreach ($routes as $route)
                @php($state = $states[$route->id] ?? 'inherit')
                <tr data-pattern="{{ $route->pattern }}">
                    <td class="text-nowrap">
                        <code>{{ $route->pattern }}</code>
                        @if (str_contains($route->pattern, '*'))
                            <span class="badge bg-secondary ms-1">шаблон</span>
                        @endif
                    </td>
                    <td class="text-muted text-break" style="max-width: 32rem">{{ $route->description }}</td>
                    <td class="text-end text-nowrap">
                        <div class="btn-group btn-group-sm" role="group">
                            @foreach (['inherit' => ['—', 'secondary'], 'allow' => ['Разрешить', 'success'], 'deny' => ['Запретить', 'danger']] as $value => [$label, $color])
                                <input type="radio" class="btn-check" name="rules[{{ $route->id }}]"
                                       id="rule-{{ $route->id }}-{{ $value }}" value="{{ $value }}"
                                       @checked($state === $value)>
                                <label class="btn btn-outline-{{ $color }}"
                                       for="rule-{{ $route->id }}-{{ $value }}">{{ $label }}</label>
                            @endforeach
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

    </form>
@endsection

@section('scripts')
    <script>
        (function () {
            const q = document.getElementById('filter-q');
            const state = document.getElementById('filter-state');
            const rows = document.querySelectorAll('#rules-table tbody tr[data-pattern]');

            function apply() {
                const text = q.value.trim().toLowerCase();
                rows.forEach(function (row) {
                    const current = row.querySelector('input:checked');
                    const okText = !text || row.dataset.pattern.toLowerCase().includes(text);
                    const okState = !state.value || (current && current.value === state.value);
                    row.hidden = !(okText && okState);
                });
            }

            q.addEventListener('input', apply);
            state.addEventListener('change', apply);
        })();
    </script>
@endsection
