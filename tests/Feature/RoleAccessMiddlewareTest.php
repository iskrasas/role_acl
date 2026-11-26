<?php

namespace RoleAccess\Acl\Tests\Feature;

use RoleAccess\Acl\Http\Middleware\RoleAccess;
use RoleAccess\Acl\Models\AclRoute;
use RoleAccess\Acl\Models\Group;
use RoleAccess\Acl\Models\GroupPermission;
use RoleAccess\Acl\Models\Staff;
use RoleAccess\Acl\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use RoleAccess\Acl\Tests\TestCase;

class RoleAccessMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(RoleAccess::class)->group(function () {
            Route::get('/_acl/users', fn () => 'users')->name('users.index');
            Route::get('/_acl/users/create', fn () => 'create')->name('users.create');
            Route::get('/_acl/unnamed', fn () => 'unnamed');
        });
    }

    #[Test]
    public function guest_is_denied(): void
    {
        $this->get('/_acl/users')->assertForbidden();
    }

    #[Test]
    public function user_without_staff_record_is_denied(): void
    {
        $this->actingAs(User::factory()->create())->get('/_acl/users')->assertForbidden();
    }

    #[Test]
    public function allowed_route_passes_and_others_are_denied(): void
    {
        $user = $this->staffWithGroupRule('users.index', true);

        $this->actingAs($user)->get('/_acl/users')->assertOk()->assertSee('users');
        $this->actingAs($user)->get('/_acl/users/create')->assertForbidden();
    }

    #[Test]
    public function specific_allow_overrides_wildcard_deny(): void
    {
        $user = $this->staffWithGroupRule('users.*', false);
        $group = Group::query()->firstOrFail();
        GroupPermission::create([
            'group_id' => $group->id,
            'route_id' => AclRoute::firstOrCreate(['pattern' => 'users.index'])->id,
            'allowed' => true,
        ]);

        $this->actingAs($user)->get('/_acl/users')->assertOk();
        $this->actingAs($user)->get('/_acl/users/create')->assertForbidden();
    }

    #[Test]
    public function unnamed_route_is_denied_even_with_global_allow(): void
    {
        $user = $this->staffWithGroupRule('*', true);

        $this->actingAs($user)->get('/_acl/users')->assertOk();
        $this->actingAs($user)->get('/_acl/unnamed')->assertForbidden();
    }

    private function staffWithGroupRule(string $pattern, bool $allowed): User
    {
        $user = User::factory()->create();
        $staff = Staff::create(['user_id' => $user->id]);
        $group = Group::create(['name' => 'test-group']);
        $staff->groups()->attach($group->id);

        GroupPermission::create([
            'group_id' => $group->id,
            'route_id' => AclRoute::firstOrCreate(['pattern' => $pattern])->id,
            'allowed' => $allowed,
        ]);

        return $user;
    }
}
