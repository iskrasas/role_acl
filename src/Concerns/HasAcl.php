<?php

namespace RoleAccess\Acl\Concerns;

use RoleAccess\Acl\Models\Staff;
use RoleAccess\Acl\Services\AclResolver;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Add to the host user model.
 *
 * @property-read Staff|null $aclStaff
 */
trait HasAcl
{
    /**
     * @return HasOne<Staff, $this>
     */
    public function aclStaff(): HasOne
    {
        return $this->hasOne(Staff::class, 'user_id');
    }

    public function canAccessRoute(?string $routeName): bool
    {
        return app(AclResolver::class)->can($this, $routeName);
    }
}
