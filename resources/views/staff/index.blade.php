@extends(config('acl.layout'))

@section('title', 'Сотрудники в группах')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Сотрудники в группах</li>
@endsection

@section('content')
    <h4 class="mb-3">Сотрудники и группы доступа</h4>

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

    <form method="POST" action="{{ route('acl.staff.store') }}" class="row g-2 align-items-end mb-4">
        @csrf
        <div class="col-auto">
            <label for="email" class="form-label mb-1">Добавить сотрудника (email пользователя)</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required
                   class="form-control @error('email') is-invalid @enderror" style="min-width: 22rem">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-success"><i class="fas fa-user-plus me-1"></i>Добавить</button>
        </div>
    </form>

    @unless ($isAdmin)
        <p class="text-muted">Вы видите и назначаете только свои группы.</p>
    @endunless

    <table class="table table-hover align-middle">
        <thead>
        <tr>
            <th>Сотрудник</th>
            <th>Email</th>
            <th>Группы</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($staff as $member)
            <tr>
                <td>{{ $member->user?->name }}</td>
                <td class="text-muted">{{ $member->user?->email }}</td>
                <td>
                    <div class="d-flex flex-wrap gap-1">
                    @forelse ($member->groups->filter(fn ($g) => $visibleGroupIds === null || in_array($g->id, $visibleGroupIds, true))->sortBy('name') as $group)
                        <span class="badge bg-secondary text-wrap text-break">{{ $group->name }}</span>
                    @empty
                        <span class="text-muted">нет групп — доступа нет</span>
                    @endforelse
                    </div>
                </td>
                <td class="text-end">
                    <a class="btn btn-sm btn-primary" href="{{ route('acl.staff.edit', $member) }}">
                        <i class="fas fa-edit me-1"></i>Группы
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="text-center text-muted py-4">Сотрудников нет</td>
            </tr>
        @endforelse
        </tbody>
    </table>
@endsection
