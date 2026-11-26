@extends(config('acl.layout'))

@section('title', 'Запись журнала #'.$log->id)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('acl.logs.index') }}">Журнал доступа</a></li>
    <li class="breadcrumb-item active">#{{ $log->id }}</li>
@endsection

@section('content')
    <h4 class="mb-3">Запись журнала #{{ $log->id }}</h4>

    <table class="table table-sm w-auto">
        <tr><th class="pe-4">Время</th><td>{{ $log->created_at->timezone(config('acl_log.timezone'))->format('d.m.Y H:i:s') }}</td></tr>
        <tr><th>Сотрудник</th><td>{{ $log->staff?->user?->name }} <span class="text-muted">{{ $log->staff?->user?->email }}</span></td></tr>
        <tr><th>Маршрут</th><td><code>{{ $log->route?->pattern }}</code></td></tr>
        <tr><th>Запрос</th><td>{{ $log->method }} <span class="text-break">{{ $log->uri }}</span></td></tr>
        <tr><th>ID объекта</th><td>{{ $log->object_id }}</td></tr>
        <tr><th>Статус ответа</th><td>{{ $log->status_code ?? 'нет ответа (запрос не завершился)' }}</td></tr>
    </table>

    <h5 class="mt-4">Параметры запроса</h5>
    @if ($log->payload)
        <pre class="bg-light border rounded p-3">{{ json_encode($log->payload->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    @else
        <p class="text-muted">Параметров нет или они уже удалены (хранятся короче самой записи).</p>
    @endif

    <a href="{{ url()->previous() === url()->current() ? route('acl.logs.index') : url()->previous() }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Назад
    </a>
@endsection
