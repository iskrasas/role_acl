<?php

namespace RoleAccess\Acl\Services;

use RoleAccess\Acl\Models\AclRoute;
use RoleAccess\Acl\Models\Group;
use RoleAccess\Acl\Models\GroupPermission;
use RoleAccess\Acl\Models\Staff;
use Illuminate\Contracts\Auth\Authenticatable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single write path for ACL data: keeps the resolver cache consistent and
 * refuses changes that would lock the acting user out of the ACL screens.
 */
class AclService
{
    /** Routes the acting user must not lose (if they had them) through their own change. */
    private const GUARDED_ROUTES = [
        'acl.groups.permissions.update',
        'acl.routes.update',
        'acl.staff.update',
        'acl.groups.update',
    ];

    public function __construct(private readonly AclResolver $resolver)
    {
    }

    /**
     * @param  array<int, string>  $rules  route_id => 'allow' | 'deny' | 'inherit' ('inherit' = no rule)
     * @return int number of rules that actually changed
     *
     * @throws ValidationException when the change would lock $actor out
     */
    public function syncGroupPermissions(Group $group, array $rules, Authenticatable $actor): int
    {
        $userIds = $group->staff()->pluck('acl_staff.user_id')->push((int) $actor->getAuthIdentifier())->unique()->values()->all();
        $changed = 0;

        $this->guarded($actor, $userIds, 'rules', function () use ($group, $rules, &$changed): void {
            $existing = GroupPermission::query()
                ->where('group_id', $group->id)
                ->pluck('allowed', 'route_id');

            foreach ($rules as $routeId => $state) {
                $current = $existing->has($routeId) ? ($existing[$routeId] ? 'allow' : 'deny') : 'inherit';

                if ($current === $state) {
                    continue;
                }

                if ($state === 'inherit') {
                    GroupPermission::query()
                        ->where('group_id', $group->id)
                        ->where('route_id', $routeId)
                        ->delete();
                } else {
                    GroupPermission::query()->updateOrCreate(
                        ['group_id' => $group->id, 'route_id' => $routeId],
                        ['allowed' => $state === 'allow'],
                    );
                }

                $changed++;
            }
        });

        return $changed;
    }

    /**
     * Creates or renames a group. The admin group's name is reserved: nobody else may take it
     * (that would make its members admins) and the admin group itself cannot be renamed.
     *
     * @param  array{name: string, description?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function saveGroup(Group $group, array $data): Group
    {
        $admin = (string) config('acl.admin_group');

        if ($data['name'] === $admin && $group->name !== $admin) {
            throw ValidationException::withMessages(['name' => "Имя «{$admin}» зарезервировано для группы администраторов."]);
        }

        if ($group->exists && $group->name === $admin && $data['name'] !== $admin) {
            throw ValidationException::withMessages(['name' => 'Группу администраторов нельзя переименовать.']);
        }

        // the global ConvertEmptyStringsToNull middleware is not active in this app
        $data['description'] = filled($data['description'] ?? null) ? $data['description'] : null;

        $group->fill($data)->save();

        return $group;
    }

    /**
     * Deletes the group with its memberships and rules (FK cascade).
     *
     * @throws ValidationException for the admin group or when the change would lock $actor out
     */
    public function deleteGroup(Group $group, Authenticatable $actor): void
    {
        if ($group->name === config('acl.admin_group')) {
            throw ValidationException::withMessages(['group' => 'Группу администраторов нельзя удалить.']);
        }

        $userIds = $group->staff()->pluck('acl_staff.user_id')->push((int) $actor->getAuthIdentifier())
            ->map(fn ($id): int => (int) $id)->unique()->values()->all();

        $this->guarded($actor, $userIds, 'group', function () use ($group): void {
            $group->delete();
        });
    }

    /**
     * Makes a user a member of the ACL staff (no groups yet, so no access is granted).
     */
    public function addStaff(Authenticatable $user): Staff
    {
        $staff = Staff::query()->firstOrCreate(['user_id' => (int) $user->getAuthIdentifier()]);
        $this->resolver->forget((int) $user->getAuthIdentifier());

        return $staff;
    }

    /**
     * Admins (members of the configured admin group) manage every group.
     */
    public function isAdmin(Authenticatable $user): bool
    {
        return Group::query()
            ->where('name', config('acl.admin_group'))
            ->whereHas('staff', fn ($query) => $query->where('acl_staff.user_id', (int) $user->getAuthIdentifier()))
            ->exists();
    }

    /**
     * Groups $actor may assign or remove; null means all of them (admin).
     *
     * @return list<int>|null
     */
    public function assignableGroupIds(Authenticatable $actor): ?array
    {
        if ($this->isAdmin($actor)) {
            return null;
        }

        return DB::table('acl_staff_group as sg')
            ->join('acl_staff as s', 's.id', '=', 'sg.staff_id')
            ->where('s.user_id', (int) $actor->getAuthIdentifier())
            ->pluck('sg.group_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Replaces the staff member's group memberships. A non-admin actor only
     * controls the groups they belong to themselves: the target's memberships
     * in other groups stay as they are.
     *
     * @param  list<int>  $groupIds
     * @return int number of memberships added or removed
     *
     * @throws ValidationException when $actor picks foreign groups or the change would lock them out
     */
    public function syncStaffGroups(Staff $staff, array $groupIds, Authenticatable $actor): int
    {
        $changed = 0;
        $groupIds = array_values(array_unique($groupIds));
        $scope = $this->assignableGroupIds($actor);

        if ($scope !== null) {
            if (array_diff($groupIds, $scope) !== []) {
                throw ValidationException::withMessages(['groups' => 'Можно назначать только свои группы.']);
            }

            $outside = $staff->groups()->pluck('acl_groups.id')
                ->map(fn ($id): int => (int) $id)
                ->reject(fn (int $id): bool => in_array($id, $scope, true))
                ->all();

            $groupIds = array_values(array_unique([...$outside, ...$groupIds]));
        }

        $this->guarded($actor, [$staff->user_id, (int) $actor->getAuthIdentifier()], 'groups', function () use ($staff, $groupIds, &$changed): void {
            $result = $staff->groups()->sync($groupIds);
            $changed = count($result['attached']) + count($result['detached']);
        });

        return $changed;
    }

    /**
     * Creates or updates an acl_routes row ($route->exists decides).
     *
     * @param  array{pattern: string, description?: string|null, icon?: string|null, order_index?: int}  $data
     *
     * @throws ValidationException when the change would lock $actor out
     */
    public function saveRoute(AclRoute $route, array $data, Authenticatable $actor): AclRoute
    {
        $userIds = $route->exists ? $this->usersAffectedBy($route, $actor) : [(int) $actor->getAuthIdentifier()];

        // blank form values: no description / no icon (hidden in the menu) / order 0
        $data['description'] = filled($data['description'] ?? null) ? $data['description'] : null;
        $data['icon'] = filled($data['icon'] ?? null) ? $data['icon'] : null;
        $data['order_index'] = (int) ($data['order_index'] ?? 0);

        $this->guarded($actor, $userIds, 'pattern', function () use ($route, $data): void {
            $route->fill($data)->save();
        });

        return $route;
    }

    /**
     * Deletes the row together with every rule that points to it (FK cascade).
     *
     * @throws ValidationException when the change would lock $actor out
     */
    public function deleteRoute(AclRoute $route, Authenticatable $actor): void
    {
        $this->guarded($actor, $this->usersAffectedBy($route, $actor), 'route', function () use ($route): void {
            $route->delete();
        });
    }

    /**
     * Runs $mutation in a transaction, refreshes the affected users' cached
     * rules, and rolls back when $actor would lose a guarded route.
     *
     * @param  list<int>  $userIds
     */
    private function guarded(Authenticatable $actor, array $userIds, string $errorKey, Closure $mutation): void
    {
        $before = array_values(array_filter(
            self::GUARDED_ROUTES,
            fn (string $name): bool => $this->resolver->can($actor, $name),
        ));

        try {
            DB::transaction(function () use ($actor, $userIds, $errorKey, $mutation, $before): void {
                $mutation();

                $this->forget($userIds);

                foreach ($before as $name) {
                    if (! $this->resolver->can($actor, $name)) {
                        throw ValidationException::withMessages([
                            $errorKey => 'Изменения не сохранены: вы потеряете доступ к редактированию прав или маршрутов.',
                        ]);
                    }
                }
            });
        } finally {
            // never leave a cache entry computed from uncommitted or rolled-back data
            $this->forget($userIds);
        }
    }

    /**
     * Users whose effective rules depend on $route (via groups or direct rules), plus the actor.
     *
     * @return list<int>
     */
    private function usersAffectedBy(AclRoute $route, Authenticatable $actor): array
    {
        $viaGroups = DB::table('acl_group_permissions as gp')
            ->join('acl_staff_group as sg', 'sg.group_id', '=', 'gp.group_id')
            ->join('acl_staff as s', 's.id', '=', 'sg.staff_id')
            ->where('gp.route_id', $route->id)
            ->pluck('s.user_id');

        $direct = DB::table('acl_staff_permissions as sp')
            ->join('acl_staff as s', 's.id', '=', 'sp.staff_id')
            ->where('sp.route_id', $route->id)
            ->pluck('s.user_id');

        return $viaGroups->merge($direct)->push((int) $actor->getAuthIdentifier())->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    /**
     * @param  list<int>  $userIds
     */
    private function forget(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $this->resolver->forget($userId);
        }
    }
}
