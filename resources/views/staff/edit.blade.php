@extends(config('acl.layout'))

@section('title', 'Группы сотрудника ' . $staff->user?->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a class="text-decoration-none text-black-50"
                                   href="{{ route('acl.staff.index') }}">Сотрудники в группах</a></li>
    <li class="breadcrumb-item active">{{ $staff->user?->name }}</li>
@endsection

@section('content')
    <h4 class="mb-1">{{ $staff->user?->name }}</h4>
    <p class="text-muted mb-3">{{ $staff->user?->email }}</p>

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

    <form method="POST" action="{{ route('acl.staff.update', $staff) }}">
        @csrf
        @method('PUT')

        <div class="list-group mb-3" style="max-width: 40rem">
            @forelse ($groups as $group)
                @php($checked = in_array($group->id, old('groups', $memberOf)))
                <label class="list-group-item d-flex align-items-start gap-2">
                    <input type="checkbox" class="form-check-input mt-1" name="groups[]" value="{{ $group->id }}" @checked($checked)>
                    <span class="text-break">
                        <code>{{ $group->name }}</code>
                        @if ($group->description)
                            <span class="d-block text-muted small">{{ $group->description }}</span>
                        @endif
                    </span>
                </label>
            @empty
                <div class="list-group-item text-muted">Групп нет</div>
            @endforelse
        </div>

        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Сохранить</button>
        <a href="{{ route('acl.staff.index') }}" class="btn btn-outline-secondary">Отмена</a>
    </form>
@endsection
