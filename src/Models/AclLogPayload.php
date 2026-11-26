<?php

namespace RoleAccess\Acl\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Request input of a log entry (query + body; uploaded files as their real path).
 * Kept shorter than the log itself (pruned by the parent log's created_at), see acl:prune-logs.
 *
 * @property int $id
 * @property int $acl_log_id
 * @property array<string, mixed> $payload
 * @property-read AclLog $log
 */
#[Table('acl_log_payloads')]
#[Fillable(['acl_log_id', 'payload'])]
class AclLogPayload extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<AclLog, $this>
     */
    public function log(): BelongsTo
    {
        return $this->belongsTo(AclLog::class, 'acl_log_id');
    }
}
