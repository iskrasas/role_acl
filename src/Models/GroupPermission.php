<?php

namespace RoleAccess\Acl\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Allow/deny rule of a group for a route pattern.
 *
 * @property int $id
 * @property int $group_id
 * @property int $route_id
 * @property bool $allowed
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Group $group
 * @property-read AclRoute $route
 */
#[Table('acl_group_permissions')]
#[Fillable(['group_id', 'route_id', 'allowed'])]
class GroupPermission extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    /**
     * @return BelongsTo<AclRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(AclRoute::class, 'route_id');
    }
}
