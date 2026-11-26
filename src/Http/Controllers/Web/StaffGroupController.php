<?php

namespace RoleAccess\Acl\Http\Controllers\Web;

use RoleAccess\Acl\Http\Controllers\Controller;
use RoleAccess\Acl\Models\Group;
use RoleAccess\Acl\Models\Staff;
use RoleAccess\Acl\Services\AclService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffGroupController extends Controller
{
    public function index(Request $request, AclService $acl): View
    {
        $isAdmin = $acl->isAdmin($request->user());
        return view('acl::staff.index', [
            'isAdmin' => $isAdmin,
            'visibleGroupIds' => $acl->assignableGroupIds($request->user()),
            'staff' => Staff::query()
                ->with(['user:id,name,email', 'groups:id,name'])
                ->get()
                ->sortBy(fn (Staff $staff): string => mb_strtolower((string) $staff->user?->name))
                ->values(),
        ]);
    }

    /**
     * Registers an existing user as staff, then goes straight to the group selection.
     */
    public function store(Request $request, AclService $acl): RedirectResponse
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userModel */
        $userModel = config('acl.user_model');

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', Rule::exists((new $userModel())->getTable(), 'email')],
        ]);

        $user = $userModel::query()->where('email', $validated['email'])->firstOrFail();
        $staff = $acl->addStaff($user);

        return redirect()->route('acl.staff.edit', $staff);
    }

    public function edit(Request $request, Staff $staff, AclService $acl): View
    {
        $scope = $acl->assignableGroupIds($request->user());

        return view('acl::staff.edit', [
            'staff' => $staff->load('user:id,name,email'),
            'groups' => Group::query()
                ->when($scope !== null, fn ($query) => $query->whereIn('id', $scope))
                ->orderBy('name')
                ->get(),
            'memberOf' => $staff->groups()->pluck('acl_groups.id')->all(),
        ]);
    }

    public function update(Request $request, Staff $staff, AclService $acl): RedirectResponse
    {
        $validated = $request->validate([
            'groups' => ['nullable', 'array'],
            'groups.*' => ['integer', 'exists:acl_groups,id'],
        ]);

        $changed = $acl->syncStaffGroups(
            $staff,
            array_map('intval', $validated['groups'] ?? []),
            $request->user(),
        );

        return redirect()
            ->route('acl.staff.edit', $staff)
            ->with('success', $changed > 0 ? "Сохранено, изменено групп: {$changed}" : 'Изменений нет');
    }
}
