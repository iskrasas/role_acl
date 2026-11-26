@extends(config('acl.layout'))

@section('title', 'Группы и права')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Группы и права</li>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Группы доступа</h4>
        @if ($isAdmin)
            <a class="btn btn-success" href="{{ route('acl.groups.create') }}"><i class="fas fa-plus me-1"></i>Новая группа</a>
        @endif
    </div>

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

    <table class="table table-hover align-middle">
        <thead>
        <tr>
            <th>Группа</th>
            <th>Описание</th>
            <th class="text-end">Сотрудников</th>
            <th class="text-end">Правил</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($groups as $group)
            <tr>
                <td><code>{{ $group->name }}</code></td>
                <td class="text-muted text-break" style="max-width: 32rem">{{ $group->description }}</td>
                <td class="text-end">{{ $group->staff_count }}</td>
                <td class="text-end">{{ $group->permissions_count }}</td>
                <td class="text-end">
                    <a class="btn btn-sm btn-primary" href="{{ route('acl.groups.permissions.edit', $group) }}">
                        <i class="fas fa-key me-1"></i>Права
                    </a>
                    @if ($isAdmin)
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('acl.groups.edit', $group) }}">
                            <i class="fas fa-pen"></i>
                        </a>
                        <form method="POST" action="{{ route('acl.groups.destroy', $group) }}" class="d-inline"
                              data-confirm="Удалить группу «{{ $group->name }}»? Будут удалены членства ({{ $group->staff_count }}) и правила ({{ $group->permissions_count }})"
                              onsubmit="return confirm(this.dataset.confirm)">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-muted py-4">Групп нет</td>
            </tr>
        @endforelse
        </tbody>
    </table>
@endsection
