<?php

namespace RoleAccess\Acl\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Decides whether a user may access a route, based on acl_* rules.
 *
 * Precedence:
 *  1. the most specific matching pattern wins (users.index > users.* > *);
 *  2. at the same specificity a user rule beats group rules;
 *  3. among group rules at the same specificity, deny beats allow;
 *  4. no matching rule means deny.
 *
 * @phpstan-type RuleMap array<string, array{user: bool|null, groups: list<bool>}>
 */
final class AclResolver
{
    public function __construct(private readonly Cache $cache)
    {
    }

    public function can(Authenticatable|int $user, ?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        $userId = $user instanceof Authenticatable ? (int) $user->getAuthIdentifier() : $user;

        return self::decide($this->rulesFor($userId), $routeName);
    }

    /**
     * Pure decision over an already loaded rule map.
     *
     * @param  RuleMap  $rules
     */
    public static function decide(array $rules, string $routeName): bool
    {
        foreach (self::candidates($routeName) as $pattern) {
            $rule = $rules[$pattern] ?? null;

            if ($rule === null) {
                continue;
            }

            if ($rule['user'] !== null) {
                return $rule['user'];
            }

            if ($rule['groups'] !== []) {
                return ! in_array(false, $rule['groups'], true);
            }
        }

        return false;
    }

    /**
     * Patterns that can match a route name, most specific first.
     * "admin.users.index" => admin.users.index, admin.users.*, admin.*, *
     *
     * @return list<string>
     */
    public static function candidates(string $routeName): array
    {
        $parts = explode('.', $routeName);
        $candidates = [$routeName];

        for ($i = count($parts) - 1; $i > 0; $i--) {
            $candidates[] = implode('.', array_slice($parts, 0, $i)).'.*';
        }

        $candidates[] = '*';

        return $candidates;
    }

    /**
     * pattern => number of existing named routes it covers (same matching as can()).
     *
     * @return array<string, int>
     */
    public static function matchCounts(): array
    {
        $matches = [];

        foreach (array_keys(Route::getRoutes()->getRoutesByName()) as $name) {
            foreach (self::candidates($name) as $candidate) {
                $matches[$candidate] = ($matches[$candidate] ?? 0) + 1;
            }
        }

        return $matches;
    }

    /**
     * @return RuleMap
     */
    public function rulesFor(int $userId): array
    {
        return $this->cache->remember(
            self::cacheKey($userId),
            (int) config('acl.cache_ttl', 3600),
            fn (): array => $this->loadRules($userId),
        );
    }

    public function forget(int $userId): void
    {
        $this->cache->forget(self::cacheKey($userId));
        $this->cache->forget(AclMenu::cacheKey($userId)); // menu is derived from the same rules
    }

    private static function cacheKey(int $userId): string
    {
        return "acl:rules:{$userId}";
    }

    /**
     * @return RuleMap
     */
    private function loadRules(int $userId): array
    {
        $staffId = DB::table('acl_staff')->where('user_id', $userId)->value('id');

        if ($staffId === null) {
            return [];
        }

        $rules = [];

        $groupRows = DB::table('acl_group_permissions as gp')
            ->join('acl_staff_group as sg', 'sg.group_id', '=', 'gp.group_id')
            ->join('acl_routes as r', 'r.id', '=', 'gp.route_id')
            ->where('sg.staff_id', $staffId)
            ;
        foreach ($groupRows->get(['r.pattern', 'gp.allowed']) as $row) {
            $rules[$row->pattern] ??= ['user' => null, 'groups' => []];
            $rules[$row->pattern]['groups'][] = (bool) $row->allowed;
        }

        $userRows = DB::table('acl_staff_permissions as sp')
            ->join('acl_routes as r', 'r.id', '=', 'sp.route_id')
            ->where('sp.staff_id', $staffId)
            ->get(['r.pattern', 'sp.allowed']);

        foreach ($userRows as $row) {
            $rules[$row->pattern] ??= ['user' => null, 'groups' => []];
            $rules[$row->pattern]['user'] = (bool) $row->allowed;
        }

        return $rules;
    }
}
