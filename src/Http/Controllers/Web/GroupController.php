<?php

namespace RoleAccess\Acl\Http\Controllers\Web;

use RoleAccess\Acl\Http\Controllers\Controller;
use RoleAccess\Acl\Models\Group;
use RoleAccess\Acl\Services\AclRules;
use RoleAccess\Acl\Services\AclService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * CRUD of acl_groups. Admin only (see the AdminOnly middleware on the routes).
 * The list lives in GroupPermissionController@index.
 */
class GroupController extends Controller
{
    public function create(): View
    {
        return view('acl::groups.form', ['group' => new Group()]);
    }

    public function store(Request $request, AclService $acl): RedirectResponse
    {
        $group = $acl->saveGroup(new Group(), $this->validated($request, null));

        return redirect()->route('acl.groups.index')->with('success', "Группа «{$group->name}» создана");
    }

    public function edit(Group $group): View
    {
        return view('acl::groups.form', ['group' => $group]);
    }

    public function update(Request $request, Group $group, AclService $acl): RedirectResponse
    {
        $acl->saveGroup($group, $this->validated($request, $group));

        return redirect()->route('acl.groups.index')->with('success', "Группа «{$group->name}» сохранена");
    }

    public function destroy(Request $request, Group $group, AclService $acl): RedirectResponse
    {
        $acl->deleteGroup($group, $request->user());

        return redirect()->route('acl.groups.index')->with('success', "Группа «{$group->name}» удалена");
    }

    /**
     * @return array{name: string, description: string|null}
     */
    private function validated(Request $request, ?Group $group): array
    {
        $validated = $request->validate(AclRules::group($group?->id));
        $validated['description'] ??= null;

        return $validated;
    }
}
