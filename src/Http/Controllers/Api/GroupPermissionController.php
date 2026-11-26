<?php

namespace RoleAccess\Acl\Http\Controllers\Api;

use RoleAccess\Acl\Models\AclRoute;
use RoleAccess\Acl\Models\Group;
use RoleAccess\Acl\Services\AclService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rules of one group: every acl_routes pattern with its state (inherit | allow | deny).
 */
class GroupPermissionController extends AclApiController
{
    public function show(Group $group): JsonResponse
    {
        $states = $group->permissions()->pluck('allowed', 'route_id');

        return $this->answer([
            'group' => ['id' => $group->id, 'name' => $group->name],
            'routes' => AclRoute::query()->orderBy('pattern')->get()->map(fn (AclRoute $route): array => [
                'id' => $route->id,
                'pattern' => $route->pattern,
                'description' => $route->description,
                'state' => $states->has($route->id) ? ($states[$route->id] ? 'allow' : 'deny') : 'inherit',
            ])->all(),
        ]);
    }

    /**
     * Body: {"rules": {"<route_id>": "allow|deny|inherit", ...}}; routes not listed stay as they are.
     */
    public function update(Request $request, Group $group, AclService $acl): JsonResponse
    {
        $validated = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*' => ['in:inherit,allow,deny'],
        ]);

        $rules = [];
        foreach (AclRoute::query()->whereIn('id', array_keys($validated['rules']))->pluck('id') as $routeId) {
            $rules[(int) $routeId] = $validated['rules'][$routeId];
        }

        return $this->answer(['changed' => $acl->syncGroupPermissions($group, $rules, $request->user())]);
    }
}
