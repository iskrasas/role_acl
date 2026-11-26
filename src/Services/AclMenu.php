<?php

namespace RoleAccess\Acl\Services;

use RoleAccess\Acl\Models\AclRoute;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Sidebar menu: the named routes (GET, no URL parameters) the user may open and that have an icon in
 * acl_routes (label = description, order = order_index). The icon is the only switch: no icon, no menu item.
 *
 * The per-user list of allowed route names is cached. It is derived from the ACL
 * rules, so AclResolver::forget() drops it together with the rules; it is also
 * rebuilt when the set of routes in the app changes.
 */
class AclMenu
{
    public function __construct(
        private readonly AclResolver $resolver,
        private readonly Cache $cache,
    ) {
    }

    public static function cacheKey(int $userId): string
    {
        return "acl:menu:{$userId}";
    }

    /**
     * Names of the routes (GET, no URL parameters) the user is allowed to open.
     *
     * @return list<string>
     */
    public function allowedRoutes(Authenticatable|int $user): array
    {
        $userId = $user instanceof Authenticatable ? (int) $user->getAuthIdentifier() : $user;
        $names = $this->routeNames();
        $fingerprint = md5(implode('|', $names));

        $cached = $this->cache->get(self::cacheKey($userId));

        if (is_array($cached) && ($cached['fingerprint'] ?? null) === $fingerprint) {
            return $cached['names'];
        }

        $allowed = array_values(array_filter(
            $names,
            fn (string $name): bool => $this->resolver->can($userId, $name),
        ));

        $this->cache->put(self::cacheKey($userId), ['fingerprint' => $fingerprint, 'names' => $allowed], (int) config('acl.cache_ttl', 3600));

        return $allowed;
    }

    /**
     * Sidebar items: allowed routes that have an icon, ordered by order_index.
     * Icon, order and label are read from acl_routes on every render, so edits show at once.
     *
     * @return list<array{name: string, url: string, label: string, icon: string, active: bool}>
     */
    public function itemsFor(?Authenticatable $user): array
    {
        if ($user === null) {
            return [];
        }

        $allowed = $this->allowedRoutes($user);

        if ($allowed === []) {
            return [];
        }

        return AclRoute::query()
            ->whereIn('pattern', $allowed)
            ->whereNotNull('icon')
            ->where('icon', '!=', '')
            ->orderBy('order_index')
            ->orderBy('pattern')
            ->get(['pattern', 'description', 'icon'])
            ->map(fn (AclRoute $route): array => $this->item($route))
            ->all();
    }

    /**
     * @return list<string>
     */
    private function routeNames(): array
    {
        $names = [];

        foreach (Route::getRoutes()->getRoutesByName() as $name => $route) {
            if (in_array('GET', $route->methods(), true)
                && ! str_contains($route->uri(), '{')) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * @return array{name: string, url: string, label: string, icon: string, active: bool}
     */
    private function item(AclRoute $route): array
    {
        $name = $route->pattern;

        return [
            'name' => $name,
            'url' => route($name),
            'label' => $this->label($name, $route->description),
            'icon' => (string) $route->icon,
            // "users.index" is active on every "users.*" page (create, edit, ...); other routes only on themselves
            'active' => str_ends_with($name, '.index')
                ? request()->routeIs(Str::beforeLast($name, '.index').'.*')
                : request()->routeIs($name),
        ];
    }

    private function label(string $name, ?string $description): string
    {
        $description = trim((string) $description);

        if ($description !== '') {
            return $description;
        }

        // no description: derive a label from the route name ("user_surveys.index" => "User surveys", "reports.sales" => "Reports sales")
        return (string) Str::of($name)->beforeLast('.index')->replace(['_', '.'], ' ')->ucfirst();
    }
}
