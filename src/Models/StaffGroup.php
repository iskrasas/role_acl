<?php

namespace RoleAccess\Acl\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Staff <-> group membership (pivot with its own id).
 *
 * @property int $id
 * @property int $staff_id
 * @property int $group_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Staff $staff
 * @property-read Group $group
 */
#[Table('acl_staff_group', incrementing: true)]
class StaffGroup extends Pivot
{
    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }
}
