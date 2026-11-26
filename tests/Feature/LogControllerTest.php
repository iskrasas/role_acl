<?php

namespace RoleAccess\Acl\Tests\Feature;

use RoleAccess\Acl\Models\AclLog;
use RoleAccess\Acl\Models\AclLogPayload;
use RoleAccess\Acl\Models\AclRoute;
use RoleAccess\Acl\Models\Group;
use RoleAccess\Acl\Models\GroupPermission;
use RoleAccess\Acl\Models\Staff;
use RoleAccess\Acl\Tests\Fixtures\User;
use RoleAccess\Acl\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class LogControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_without_access_gets_403(): void
    {
        $this->actingAs(User::factory()->create())->get(route('acl.logs.index'))->assertForbidden();
    }

    #[Test]
    public function index_lists_logs_newest_first(): void
    {
        $admin = $this->admin();
        $this->log(['uri' => '/first-request']);
        $this->log(['uri' => '/second-request']);

        $this->actingAs($admin)->get(route('acl.logs.index'))
            ->assertOk()
            ->assertSeeInOrder(['/second-request', '/first-request']);
    }

    #[Test]
    public function it_filters_by_staff_route_method_status_object_and_uri(): void
    {
        $admin = $this->admin();
        $olga = Staff::create(['user_id' => User::factory()->create(['name' => 'Olga Manager'])->id]);
        $route = AclRoute::create(['pattern' => 'orders.update']);

        $this->log(['uri' => '/other', 'method' => 'GET', 'status_code' => 200]);
        $this->log(['uri' => '/orders/15', 'method' => 'PUT', 'status_code' => 403, 'object_id' => '15', 'staff_id' => $olga->id, 'route_id' => $route->id]);

        $get = fn (array $query) => $this->actingAs($admin)->get(route('acl.logs.index', $query));

        foreach ([
            ['staff_id' => $olga->id],
            ['route' => 'orders'],
            ['method' => 'PUT'],
            ['status' => 403],
            ['object_id' => '15'],
            ['uri' => 'orders/1'],
        ] as $query) {
            $get($query)->assertOk()->assertSee('/orders/15')->assertDontSee('/other');
        }
    }

    #[Test]
    public function it_filters_by_date_range(): void
    {
        $admin = $this->admin();
        $this->log(['uri' => '/last-month'], createdAt: now()->subDays(30));
        $this->log(['uri' => '/today']);

        $this->actingAs($admin)->get(route('acl.logs.index', ['from' => now()->subDays(2)->toDateString()]))
            ->assertSee('/today')->assertDontSee('/last-month');

        $this->actingAs($admin)->get(route('acl.logs.index', ['to' => now()->subDays(20)->toDateString()]))
            ->assertSee('/last-month')->assertDontSee('/today');
    }

    #[Test]
    public function times_are_shown_in_gmt_plus_3(): void
    {
        $admin = $this->admin();
        $log = $this->log(['uri' => '/tz'], createdAt: '2026-03-10 22:30:00');   // stored in the app time zone (UTC)

        $this->actingAs($admin)->get(route('acl.logs.index'))->assertSee('11.03.2026 01:30:00');
        $this->actingAs($admin)->get(route('acl.logs.show', $log))->assertSee('11.03.2026 01:30:00');
    }

    #[Test]
    public function date_filters_are_days_in_gmt_plus_3(): void
    {
        $admin = $this->admin();
        // 22:30 UTC on the 10th is already the 11th in GMT+3
        $this->log(['uri' => '/late-evening'], createdAt: '2026-03-10 22:30:00');

        $this->actingAs($admin)->get(route('acl.logs.index', ['from' => '2026-03-11']))->assertSee('/late-evening');
        $this->actingAs($admin)->get(route('acl.logs.index', ['to' => '2026-03-10']))->assertDontSee('/late-evening');
    }

    #[Test]
    public function it_searches_in_the_payload(): void
    {
        $admin = $this->admin();
        $this->log(['uri' => '/with-needle'], payload: ['title' => 'a needle here']);
        $this->log(['uri' => '/with-hay'], payload: ['title' => 'just hay']);
        $this->log(['uri' => '/no-payload']);

        $this->actingAs($admin)->get(route('acl.logs.index', ['q' => 'needle']))
            ->assertOk()->assertSee('/with-needle')->assertDontSee('/with-hay')->assertDontSee('/no-payload');
    }

    #[Test]
    public function wildcards_in_the_search_are_taken_literally(): void
    {
        $admin = $this->admin();
        $this->log(['uri' => '/100%_done']);
        $this->log(['uri' => '/1000-done']);

        $this->actingAs($admin)->get(route('acl.logs.index', ['uri' => '100%_']))
            ->assertSee('/100%_done', false)->assertDontSee('/1000-done');
    }

    #[Test]
    public function invalid_filters_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('acl.logs.index', ['method' => 'HACK', 'from' => 'not a date', 'status' => 999]))
            ->assertSessionHasErrors(['method', 'from', 'status']);

        $this->actingAs($admin)->get(route('acl.logs.index', ['from' => '2026-02-10', 'to' => '2026-02-01']))
            ->assertSessionHasErrors(['to']);
    }

    #[Test]
    public function logs_are_paginated(): void
    {
        $admin = $this->admin();

        for ($i = 1; $i <= 51; $i++) {
            $this->log(['uri' => "/page-test/{$i}"]);
        }

        $this->actingAs($admin)->get(route('acl.logs.index'))
            ->assertSee('/page-test/51')->assertDontSee('/page-test/1<', false);

        $this->actingAs($admin)->get(route('acl.logs.index', ['page' => 2]))
            ->assertSee('/page-test/1<', false)->assertDontSee('/page-test/51');
    }

    #[Test]
    public function show_displays_the_entry_with_its_payload(): void
    {
        $admin = $this->admin();
        $log = $this->log(['uri' => '/orders/7', 'method' => 'POST', 'status_code' => 500], payload: ['comment' => 'Привет', 'password' => '***']);

        $this->actingAs($admin)->get(route('acl.logs.show', $log))
            ->assertOk()
            ->assertSee('/orders/7')
            ->assertSee('500')
            ->assertSee('Привет')
            ->assertSee('***');
    }

    #[Test]
    public function show_without_a_payload_and_without_a_response_still_renders(): void
    {
        $admin = $this->admin();
        $log = $this->log(['uri' => '/crashed']);

        $this->actingAs($admin)->get(route('acl.logs.show', $log))
            ->assertOk()->assertSee('/crashed')->assertSee('нет ответа');
    }

    #[Test]
    public function payload_is_escaped(): void
    {
        $admin = $this->admin();
        $log = $this->log(['uri' => '/x'], payload: ['name' => '<script>alert(1)</script>']);

        $this->actingAs($admin)->get(route('acl.logs.show', $log))
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $payload
     */
    private function log(array $attributes = [], ?array $payload = null, $createdAt = null): AclLog
    {
        $log = new AclLog($attributes + ['method' => 'GET', 'uri' => '/x']);

        if ($createdAt !== null) {
            $log->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt]);
        }

        $log->save();

        if ($payload !== null) {
            AclLogPayload::create(['acl_log_id' => $log->id, 'payload' => $payload]);
        }

        return $log;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'admin']);
        Staff::create(['user_id' => $user->id])->groups()->attach($group->id);
        GroupPermission::create(['group_id' => $group->id, 'route_id' => AclRoute::firstOrCreate(['pattern' => '*'])->id, 'allowed' => true]);

        return $user;
    }
}
