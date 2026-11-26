<?php

namespace RoleAccess\Acl\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Staff> $staff
 * @property-read Collection<int, GroupPermission> $permissions
 */
#[Table('acl_groups')]
#[Fillable(['name', 'description'])]
class Group extends Model
{
    /**
     * @return BelongsToMany<Staff, $this, StaffGroup>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'acl_staff_group', 'group_id', 'staff_id')
            ->using(StaffGroup::class)
            ->withTimestamps();
    }

    /**
     * @return HasMany<GroupPermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(GroupPermission::class, 'group_id');
    }
}
