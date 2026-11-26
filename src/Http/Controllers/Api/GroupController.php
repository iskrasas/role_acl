<?php

namespace RoleAccess\Acl\Http\Controllers\Api;

use RoleAccess\Acl\Models\Group;
use RoleAccess\Acl\Services\AclRules;
use RoleAccess\Acl\Services\AclService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Access groups. Reading is open to anyone who passes roleAccess,
 * writing is admin-only (aclAdmin middleware on the routes).
 */
class GroupController extends AclApiController
{
    public function index(Request $request, AclService $acl): JsonResponse
    {
        $groups = Group::query()->withCount(['staff', 'permissions'])->orderBy('name')->get();

        return $this->answer([
            'is_admin' => $acl->isAdmin($request->user()),
            'groups' => $groups->map(fn (Group $group): array => $this->item($group) + [
                'staff_count' => $group->staff_count,
                'permissions_count' => $group->permissions_count,
            ])->all(),
        ]);
    }

    public function show(Group $group): JsonResponse
    {
        return $this->answer(['group' => $this->item($group)]);
    }

    public function store(Request $request, AclService $acl): JsonResponse
    {
        $group = $acl->saveGroup(new Group(), $this->validated($request));

        return $this->answer(['group' => $this->item($group)]);
    }

    public function update(Request $request, Group $group, AclService $acl): JsonResponse
    {
        $acl->saveGroup($group, $this->validated($request, $group));

        return $this->answer(['group' => $this->item($group)]);
    }

    public function delete(Request $request, Group $group, AclService $acl): JsonResponse
    {
        $acl->deleteGroup($group, $request->user());

        return $this->answer(['id' => $group->id, 'deleted' => true]);
    }

    /**
     * @return array{id: int, name: string, description: string|null}
     */
    private function item(Group $group): array
    {
        return ['id' => $group->id, 'name' => $group->name, 'description' => $group->description];
    }

    /**
     * @return array{name: string, description: string|null}
     */
    private function validated(Request $request, ?Group $group = null): array
    {
        $validated = $request->validate(AclRules::group($group?->id));
        $validated['description'] ??= null;

        return $validated;
    }
}
