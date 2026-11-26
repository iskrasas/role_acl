@extends(config('acl.layout'))

@section('title', 'Журнал доступа')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Журнал доступа</li>
@endsection

@section('content')
    <h4 class="mb-3">Журнал доступа</h4>

    @if ($errors->any())
        <div class="alert alert-danger py-2">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('acl.logs.index') }}" class="row g-2 mb-3">
        <div class="col-md-3">
            <label for="staff_id" class="form-label mb-1">Сотрудник</label>
            <select id="staff_id" name="staff_id" class="form-select form-select-sm">
                <option value="">все</option>
                @foreach ($staff as $member)
                    <option value="{{ $member->id }}" @selected((string) ($filters['staff_id'] ?? '') === (string) $member->id)>{{ $member->user?->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label for="route" class="form-label mb-1">Маршрут</label>
            <input type="text" id="route" name="route" value="{{ $filters['route'] ?? '' }}" class="form-control form-control-sm" placeholder="users.update">
        </div>
        <div class="col-md-2">
            <label for="method" class="form-label mb-1">Метод</label>
            <select id="method" name="method" class="form-select form-select-sm">
                <option value="">все</option>
                @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                    <option value="{{ $method }}" @selected(($filters['method'] ?? '') === $method)>{{ $method }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="status" class="form-label mb-1">Статус ответа</label>
            <input type="number" id="status" name="status" value="{{ $filters['status'] ?? '' }}" class="form-control form-control-sm" placeholder="403">
        </div>
        <div class="col-md-2">
            <label for="object_id" class="form-label mb-1">ID объекта</label>
            <input type="text" id="object_id" name="object_id" value="{{ $filters['object_id'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-3">
            <label for="uri" class="form-label mb-1">URL содержит</label>
            <input type="text" id="uri" name="uri" value="{{ $filters['uri'] ?? '' }}" class="form-control form-control-sm" placeholder="/users/5">
        </div>
        <div class="col-md-3">
            <label for="q" class="form-label mb-1">Параметры запроса содержат</label>
            <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label for="from" class="form-label mb-1">С даты</label>
            <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label for="to" class="form-label mb-1">По дату</label>
            <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search me-1"></i>Найти</button>
            <a href="{{ route('acl.logs.index') }}" class="btn btn-sm btn-outline-secondary">Сбросить</a>
        </div>
    </form>

    <table class="table table-sm table-hover align-middle">
        <thead>
        <tr>
            <th>Время</th>
            <th>Сотрудник</th>
            <th>Маршрут</th>
            <th>Метод</th>
            <th>URL</th>
            <th>Объект</th>
            <th>Статус</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td class="text-nowrap">{{ $log->created_at->timezone(config('acl_log.timezone'))->format('d.m.Y H:i:s') }}</td>
                <td>{{ $log->staff?->user?->name }}</td>
                <td><code>{{ $log->route?->pattern }}</code></td>
                <td>{{ $log->method }}</td>
                <td class="text-break">{{ $log->uri }}</td>
                <td>{{ $log->object_id }}</td>
                <td>
                    @if ($log->status_code === null)
                        <span class="badge bg-secondary" title="ответ не записан">нет ответа</span>
                    @else
                        <span class="badge {{ $log->status_code >= 500 ? 'bg-danger' : ($log->status_code >= 400 ? 'bg-warning text-dark' : 'bg-success') }}">{{ $log->status_code }}</span>
                    @endif
                </td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('acl.logs.show', $log) }}"><i class="fas fa-eye"></i></a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center text-muted py-4">Записей нет</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    {{ $logs->links() }}
@endsection
