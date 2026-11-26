<?php

namespace RoleAccess\Acl\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Allow/deny rule of a single staff member for a route pattern.
 *
 * @property int $id
 * @property int $staff_id
 * @property int $route_id
 * @property bool $allowed
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Staff $staff
 * @property-read AclRoute $route
 */
#[Table('acl_staff_permissions')]
#[Fillable(['staff_id', 'route_id', 'allowed'])]
class StaffPermission extends Model
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
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * @return BelongsTo<AclRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(AclRoute::class, 'route_id');
    }
}
