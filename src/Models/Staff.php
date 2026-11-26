<?php

namespace RoleAccess\Acl\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A user who takes part in the ACL (manager, admin, ...).
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model $user (the host user model, config('acl.user_model'))
 * @property-read Collection<int, Group> $groups
 * @property-read Collection<int, StaffPermission> $permissions
 */
#[Table('acl_staff')]
#[Fillable(['user_id'])]
class Staff extends Model
{
    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('acl.user_model'));
    }

    /**
     * @return BelongsToMany<Group, $this, StaffGroup>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'acl_staff_group', 'staff_id', 'group_id')
            ->using(StaffGroup::class)
            ->withTimestamps();
    }

    /**
     * Per-user rules (exceptions on top of the group rules).
     *
     * @return HasMany<StaffPermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(StaffPermission::class, 'staff_id');
    }
}
