<?php

namespace RoleAccess\Acl\Http\Controllers\Web;

use RoleAccess\Acl\Http\Controllers\Controller;
use RoleAccess\Acl\Models\AclRoute;
use RoleAccess\Acl\Models\Group;
use RoleAccess\Acl\Services\AclService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupPermissionController extends Controller
{
    public function index(Request $request, AclService $acl): View
    {
        return view('acl::groups.index', [
            'isAdmin' => $acl->isAdmin($request->user()),
            'groups' => Group::query()->withCount(['staff', 'permissions'])->orderBy('name')->get(),
        ]);
    }

    public function edit(Group $group): View
    {
        return view('acl::groups.permissions', [
            'group' => $group,
            'routes' => AclRoute::query()->orderBy('pattern')->get(),
            'states' => $group->permissions()
                ->pluck('allowed', 'route_id')
                ->map(fn ($allowed): string => $allowed ? 'allow' : 'deny'),
        ]);
    }

    public function update(Request $request, Group $group, AclService $acl): RedirectResponse
    {
        $validated = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*' => ['in:inherit,allow,deny'],
        ]);

        $rules = [];
        foreach (AclRoute::query()->whereIn('id', array_keys($validated['rules']))->pluck('id') as $routeId) {
            $rules[(int) $routeId] = $validated['rules'][$routeId];
        }

        $changed = $acl->syncGroupPermissions($group, $rules, $request->user());

        return redirect()
            ->route('acl.groups.permissions.edit', $group)
            ->with('success', $changed > 0 ? "Сохранено, изменено правил: {$changed}" : 'Изменений нет');
    }
}
