<?php

namespace RoleAccess\Acl\Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RoleAccess\Acl\Tests\TestCase;

class PruneAclLogsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function old_payloads_and_logs_are_deleted_and_recent_ones_kept(): void
    {
        // created oldest first: ids grow with time, like in real life
        $oldLog = $this->log(15, withPayload: true);    // both too old
        $edgeLog = $this->log(13, withPayload: false);  // log kept (under 2 weeks), no payload
        $midLog = $this->log(10, withPayload: true);    // log kept, payload too old
        $freshLog = $this->log(1, withPayload: true);   // both kept

        $this->artisan('acl:prune-logs')->expectsOutputToContain('Deleted payloads: 2, logs: 1')->assertSuccessful();

        $this->assertDatabaseMissing('acl_logs', ['id' => $oldLog]);
        $this->assertDatabaseHas('acl_logs', ['id' => $midLog]);
        $this->assertDatabaseHas('acl_logs', ['id' => $freshLog]);
        $this->assertDatabaseHas('acl_logs', ['id' => $edgeLog]);

        $this->assertDatabaseMissing('acl_log_payloads', ['acl_log_id' => $oldLog]);
        $this->assertDatabaseMissing('acl_log_payloads', ['acl_log_id' => $midLog]);
        $this->assertDatabaseHas('acl_log_payloads', ['acl_log_id' => $freshLog]);
    }

    #[Test]
    public function retention_cannot_be_configured_below_the_minimum(): void
    {
        config(['acl_log.payload_days' => 0, 'acl_log.log_days' => 0]);

        $older = $this->log(13, withPayload: true);
        $fresh = $this->log(1, withPayload: true);

        $this->artisan('acl:prune-logs')->assertSuccessful();

        $this->assertDatabaseHas('acl_logs', ['id' => $fresh]);
        $this->assertDatabaseHas('acl_log_payloads', ['acl_log_id' => $fresh]);
        $this->assertDatabaseHas('acl_logs', ['id' => $older]);
        $this->assertDatabaseMissing('acl_log_payloads', ['acl_log_id' => $older]);
    }

    #[Test]
    public function longer_retention_from_config_is_respected(): void
    {
        config(['acl_log.payload_days' => 10]);

        $dropped = $this->log(12, withPayload: true);
        $kept = $this->log(5, withPayload: true);

        $this->artisan('acl:prune-logs')->assertSuccessful();

        $this->assertDatabaseHas('acl_log_payloads', ['acl_log_id' => $kept]);
        $this->assertDatabaseMissing('acl_log_payloads', ['acl_log_id' => $dropped]);
        $this->assertDatabaseHas('acl_logs', ['id' => $dropped]);
    }

    #[Test]
    public function it_runs_every_night(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains($event->command, 'acl:prune-logs'));

        $this->assertNotNull($event);
        $this->assertSame('30 3 * * *', $event->expression);
    }

    private function log(int $daysAgo, bool $withPayload): int
    {
        $at = now()->subDays($daysAgo)->subHour();

        $id = DB::table('acl_logs')->insertGetId([
            'method' => 'GET', 'uri' => '/x', 'created_at' => $at, 'updated_at' => $at,
        ]);

        if ($withPayload) {
            DB::table('acl_log_payloads')->insert(['acl_log_id' => $id, 'payload' => '{"a":1}']);
        }

        return $id;
    }
}
