<?php

namespace RoleAccess\Acl\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\DB;

#[Signature('acl:prune-logs')]
#[Description('Delete ACL log payloads older than acl_log.payload_days (min 2) and logs older than acl_log.log_days (min 14)')]
class PruneAclLogs extends Command
{
    private const CHUNK = 5000;

    public function handle(): int
    {
        $payloadsBefore = now()->subDays(max(2, (int) config('acl_log.payload_days')));
        $logsBefore = now()->subDays(max(14, (int) config('acl_log.log_days')));

        // ids grow with time and payloads have no timestamps of their own,
        // so everything up to the newest expired log id is deleted by id
        $payloads = $this->deleteUpTo('acl_log_payloads', 'acl_log_id', $this->newestExpiredLogId($payloadsBefore));
        $logs = $this->deleteUpTo('acl_logs', 'id', $this->newestExpiredLogId($logsBefore));

        $this->info("Deleted payloads: {$payloads}, logs: {$logs}");

        return self::SUCCESS;
    }

    /**
     * Walks the primary key from the newest row backwards and stops at the first expired one,
     * so no index on created_at is needed.
     */
    private function newestExpiredLogId(Carbon $before): ?int
    {
        $id = DB::table('acl_logs')->where('created_at', '<', $before)->orderByDesc('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Deletes rows with $column <= $maxId in chunks to keep the locks short.
     */
    private function deleteUpTo(string $table, string $column, ?int $maxId): int
    {
        if ($maxId === null) {
            return 0;
        }

        $total = 0;

        do {
            $deleted = DB::table($table)->where($column, '<=', $maxId)->limit(self::CHUNK)->delete();
            $total += $deleted;
        } while ($deleted === self::CHUNK);

        return $total;
    }
}
