<?php

namespace RoleAccess\Acl\Http\Controllers\Api;

use RoleAccess\Acl\Models\Group;
use RoleAccess\Acl\Models\Staff;
use RoleAccess\Acl\Services\AclService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Staff members and their group memberships. Admins manage every group,
 * everyone else sees and changes only the groups they belong to.
 */
class StaffGroupController extends AclApiController
{
    public function index(Request $request, AclService $acl): JsonResponse
    {
        $visible = $acl->assignableGroupIds($request->user());

        return $this->answer([
            'is_admin' => $visible === null,
            'staff' => Staff::query()
                ->with(['user:id,name,email', 'groups:id,name'])
                ->get()
                ->sortBy(fn (Staff $staff): string => mb_strtolower((string) $staff->user?->name))
                ->map(fn (Staff $staff): array => $this->item($staff, $visible))
                ->values()
                ->all(),
        ]);
    }

    /**
     * Registers an existing user (by email) as staff. Grants no groups.
     */
    public function store(Request $request, AclService $acl): JsonResponse
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userModel */
        $userModel = config('acl.user_model');

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', Rule::exists((new $userModel())->getTable(), 'email')],
        ]);

        $staff = $acl->addStaff($userModel::query()->where('email', $validated['email'])->firstOrFail());
        $staff->load(['user:id,name,email', 'groups:id,name']);

        return $this->answer(['staff' => $this->item($staff, $acl->assignableGroupIds($request->user()))]);
    }

    /**
     * Groups the actor may assign, with a flag for the current memberships.
     */
    public function groups(Request $request, Staff $staff, AclService $acl): JsonResponse
    {
        $scope = $acl->assignableGroupIds($request->user());
        $memberOf = $staff->groups()->pluck('acl_groups.id')->all();

        return $this->answer([
            'staff' => ['id' => $staff->id, 'name' => $staff->user?->name, 'email' => $staff->user?->email],
            'groups' => Group::query()
                ->when($scope !== null, fn ($query) => $query->whereIn('id', $scope))
                ->orderBy('name')
                ->get()
                ->map(fn (Group $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'member' => in_array($group->id, $memberOf, true),
                ])->all(),
        ]);
    }

    /**
     * Body: {"groups": [ids]}. A non-admin controls only their own groups; the rest stay untouched.
     */
    public function updateGroups(Request $request, Staff $staff, AclService $acl): JsonResponse
    {
        $validated = $request->validate([
            'groups' => ['nullable', 'array'],
            'groups.*' => ['integer', 'exists:acl_groups,id'],
        ]);

        $changed = $acl->syncStaffGroups($staff, array_map('intval', $validated['groups'] ?? []), $request->user());

        return $this->answer(['changed' => $changed]);
    }

    /**
     * @param  list<int>|null  $visibleGroupIds  null = all groups
     * @return array{id: int, user_id: int, name: string|null, email: string|null, groups: list<array{id: int, name: string}>}
     */
    private function item(Staff $staff, ?array $visibleGroupIds): array
    {
        return [
            'id' => $staff->id,
            'user_id' => $staff->user_id,
            'name' => $staff->user?->name,
            'email' => $staff->user?->email,
            'groups' => $staff->groups
                ->filter(fn (Group $group): bool => $visibleGroupIds === null || in_array($group->id, $visibleGroupIds, true))
                ->sortBy('name')
                ->map(fn (Group $group): array => ['id' => $group->id, 'name' => $group->name])
                ->values()
                ->all(),
        ];
    }
}
