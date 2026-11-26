<?php

namespace RoleAccess\Acl\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A route name or a prefix pattern that rules attach to, e.g.
 * "admin.users.index", "admin.users.*" or "*".
 *
 * Named AclRoute (not Route) to avoid clashing with the Route facade.
 *
 * @property int $id
 * @property string $pattern
 * @property string|null $description Human label; also the sidebar title
 * @property string|null $icon Font Awesome class ("fa-users"); empty = not shown in the sidebar
 * @property int $order_index Sidebar order (ascending)
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, GroupPermission> $groupPermissions
 * @property-read Collection<int, StaffPermission> $staffPermissions
 */
#[Table('acl_routes')]
#[Fillable(['pattern', 'description', 'icon', 'order_index'])]
class AclRoute extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_index' => 'integer',
        ];
    }

    /**
     * @return HasMany<GroupPermission, $this>
     */
    public function groupPermissions(): HasMany
    {
        return $this->hasMany(GroupPermission::class, 'route_id');
    }

    /**
     * @return HasMany<StaffPermission, $this>
     */
    public function staffPermissions(): HasMany
    {
        return $this->hasMany(StaffPermission::class, 'route_id');
    }
}
