<?php

namespace RoleAccess\Acl\Http\Controllers\Api;

use RoleAccess\Acl\Models\AclRoute;
use RoleAccess\Acl\Services\AclResolver;
use RoleAccess\Acl\Services\AclRules;
use RoleAccess\Acl\Services\AclService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * acl_routes: the patterns the rules refer to (+ sidebar icon and order).
 */
class AclRouteController extends AclApiController
{
    public function index(): JsonResponse
    {
        $matches = AclResolver::matchCounts();

        return $this->answer([
            'routes' => AclRoute::query()
                ->withCount(['groupPermissions', 'staffPermissions'])
                ->orderBy('pattern')
                ->get()
                ->map(fn (AclRoute $route): array => $this->item($route) + [
                    'group_permissions_count' => $route->group_permissions_count,
                    'staff_permissions_count' => $route->staff_permissions_count,
                    'matched_routes' => $matches[$route->pattern] ?? 0,
                ])->all(),
        ]);
    }

    public function show(AclRoute $aclRoute): JsonResponse
    {
        return $this->answer(['route' => $this->item($aclRoute)]);
    }

    public function store(Request $request, AclService $acl): JsonResponse
    {
        $route = $acl->saveRoute(new AclRoute(), $this->validated($request), $request->user());

        return $this->answer(['route' => $this->item($route)]);
    }

    public function update(Request $request, AclRoute $aclRoute, AclService $acl): JsonResponse
    {
        $route = $acl->saveRoute($aclRoute, $this->validated($request, $aclRoute), $request->user());

        return $this->answer(['route' => $this->item($route)]);
    }

    public function delete(Request $request, AclRoute $aclRoute, AclService $acl): JsonResponse
    {
        $acl->deleteRoute($aclRoute, $request->user());

        return $this->answer(['id' => $aclRoute->id, 'deleted' => true]);
    }

    /**
     * @return array{id: int, pattern: string, description: string|null, icon: string|null, order_index: int}
     */
    private function item(AclRoute $route): array
    {
        return [
            'id' => $route->id,
            'pattern' => $route->pattern,
            'description' => $route->description,
            'icon' => $route->icon,
            'order_index' => (int) $route->order_index,
        ];
    }

    /**
     * @return array{pattern: string, description: string|null, icon: string|null, order_index: int}
     */
    private function validated(Request $request, ?AclRoute $current = null): array
    {
        $data = $request->validate(AclRules::route($current?->id));

        $data['description'] ??= null;
        $data['icon'] ??= null;
        $data['order_index'] ??= 0;

        return $data;
    }
}
