# RoleAccess/acl

Hierarchical, route-name based access control for Laravel: groups, rules (allow / deny per route-name pattern),
a route-pattern editor, staff-to-group membership, a JSON API and a sidebar menu driven by the same rules.

## How access is decided

* A route is identified by its **name**. Rules refer to patterns stored in `acl_routes`:
  `users.index`, `users.*`, `*`.
* The most specific matching pattern wins. At the same specificity a per-user rule beats group rules;
  among group rules deny beats allow. No matching rule means deny. Unnamed routes are denied.

## Install

```sh
composer config repositories.acl path pkg/acl      # or your VCS url
composer require role_access/acl:@dev
php artisan vendor:publish --tag=acl-config        # optional
php artisan migrate
```

Add the trait to the user model (optional, gives `aclStaff()` and `canAccessRoute()`):

```php
use RoleAccess\Acl\Concerns\HasAcl;

class User extends Authenticatable
{
    use HasAcl;
}
```

Protect your routes with the `roleAccess` middleware (registered by the package):

```php
Route::middleware(['auth', 'roleAccess'])->group(function () { ... });
```

## Configuration (`config/acl.php`)

| key | meaning |
| --- | --- |
| `user_model` | host user model (`acl_staff.user_id` holds its key, without a DB foreign key) |
| `admin_group` | name of the admin group (all groups, group CRUD) |
| `cache_store`, `cache_ttl` | cache of resolved rules / menu per user |
| `layout` | Blade layout the screens extend; needs sections `title`, `breadcrumbs`, `content`, Bootstrap 5 and Font Awesome |
| `menu` | views that receive the sidebar items and the variable name (default `asideItems`) |

Route names are fixed: `acl.*` (web) and `api-acl.*` (API), because rules refer to them.

Publish tags: `acl-config`, `acl-views`, `acl-migrations`.

## Routes

The package does not register routes by itself. Publish editable copies and load them from your route files:

```sh
php artisan vendor:publish --tag=acl-routes     # routes/acl-web.php, routes/acl-api.php
```

```php
// routes/web.php
require __DIR__.'/acl-web.php';

// routes/api.php
require __DIR__.'/acl-api.php';
```

The copies define their own prefix (`acl`, `api/v1/acl`) and middleware (`auth`, `roleAccess`): change them to
fit the host (e.g. your own API auth middleware), but keep the route names (`acl.*`, `api-acl.*`) and keep
`roleAccess`. Load only the web file, or only the API file, if you need just one of them.

## Sidebar

Allowed named routes (GET, no URL parameters; `*.index` or any other) that have an icon in `acl_routes` are passed to the
configured views, ordered by `order_index`. The icon is the only switch: no icon, no menu item.
`*.index` items are active on every `prefix.*` page, other items only on their own page.


```blade
@foreach ($asideItems ?? [] as $item)
    <a href="{{ $item['url'] }}" class="list-group-item {{ $item['active'] ? 'active' : '' }}">
        <i class="fas {{ $item['icon'] }}"></i> {{ $item['label'] }}
    </a>
@endforeach
```

## First admin

`acl_routes` must contain the patterns you refer to (use the route editor, or seed them yourself).
Bootstrap the first admin, e.g. in tinker:

```php
use RoleAccess\Acl\Models\{AclRoute, Group, GroupPermission, Staff};

$group = Group::create(['name' => 'admin']);
GroupPermission::create(['group_id' => $group->id, 'route_id' => AclRoute::firstOrCreate(['pattern' => '*'])->id, 'allowed' => true]);
Staff::create(['user_id' => 1])->groups()->attach($group->id);
```

## Screens and API

| | web | API |
| --- | --- | --- |
| groups | `acl.groups.*` (create / edit / delete: admins only) | `api-acl.groups.*` |
| group rules | `acl.groups.permissions.*` | `api-acl.groups.permissions*` |
| route patterns | `acl.routes.*` | `api-acl.routes.*` |
| staff membership | `acl.staff.*` | `api-acl.staff.*` |

API responses: `{"meta": {"result": "data|empty|error", "time": ...}, "data": {...}, "error": [...]}`;
validation errors are HTTP 422 with `meta.errors` per field.

## Access log

The `logAclAccess` middleware (put it after `auth` and BEFORE `roleAccess`, as the published route stubs do)
writes one `acl_logs` row per authenticated request, denied (403) ones included: route, staff member, method,
path, the last route parameter as `object_id` and the response status. The row is written before the request runs,
so a crash still leaves it (with a null `status_code`). The request input goes to `acl_log_payloads` as JSON
(hidden keys masked, uploaded files as their real path).

Settings are in `config/acl_log.php` (publish with `--tag=acl-config`): `enabled`, `hidden`, `max_payload_bytes`,
`payload_days`, `log_days`, `prune_at`. The package schedules `acl:prune-logs` every night at `prune_at`
(`null` = schedule it yourself). Payloads are deleted after 2+ days, log rows after 14+ days; those are floors that
the config cannot lower. Pruning goes by id (ids grow with time), so no index on `created_at` is needed.

## Tests

```sh
composer install
vendor/bin/phpunit
```
