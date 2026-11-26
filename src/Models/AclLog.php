<?php

namespace RoleAccess\Acl\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One request that passed the ACL.
 *
 * @property int $id
 * @property int|null $route_id
 * @property int|null $staff_id
 * @property string $method
 * @property string $uri request path without the query string
 * @property string|null $object_id value of the last route parameter
 * @property int|null $status_code HTTP status of the response (403 = denied by the ACL); null: no response was recorded
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read AclRoute|null $route
 * @property-read Staff|null $staff
 * @property-read AclLogPayload|null $payload
 */
#[Table('acl_logs')]
#[Fillable(['route_id', 'staff_id', 'method', 'uri', 'object_id', 'status_code'])]
class AclLog extends Model
{
    /**
     * @return BelongsTo<AclRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(AclRoute::class, 'route_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * @return HasOne<AclLogPayload, $this>
     */
    public function payload(): HasOne
    {
        return $this->hasOne(AclLogPayload::class, 'acl_log_id');
    }
}
