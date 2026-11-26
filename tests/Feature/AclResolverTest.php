<?php

namespace RoleAccess\Acl\Tests\Feature;

use RoleAccess\Acl\Models\AclRoute;
use RoleAccess\Acl\Models\Group;
use RoleAccess\Acl\Models\GroupPermission;
use RoleAccess\Acl\Models\Staff;
use RoleAccess\Acl\Models\StaffPermission;
use RoleAccess\Acl\Tests\Fixtures\User;
use RoleAccess\Acl\Services\AclResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RoleAccess\Acl\Tests\TestCase;

class AclResolverTest extends TestCase
{
    use RefreshDatabase;

    private AclResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = $this->app->make(AclResolver::class);
    }

    #[Test]
    public function user_without_staff_record_is_denied(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->resolver->can($user, 'users.index'));
    }

    #[Test]
    public function unnamed_route_is_denied(): void
    {
        [$user, $staff] = $this->staffUser();
        $this->grantToGroup($staff, '*', true);

        $this->assertFalse($this->resolver->can($user, null));
        $this->assertFalse($this->resolver->can($user, ''));
    }

    #[Test]
    public function group_rules_are_applied_through_membership(): void
    {
        [$user, $staff] = $this->staffUser();
        $group = $this->grantToGroup($staff, 'users.*', false);
        GroupPermission::create([
            'group_id' => $group->id,
            'route_id' => $this->route('users.index')->id,
            'allowed' => true,
        ]);

        $this->assertTrue($this->resolver->can($user, 'users.index'));
        $this->assertFalse($this->resolver->can($user, 'users.create'));
    }

    #[Test]
    public function staff_rule_overrides_group_rule_at_same_pattern(): void
    {
        [$user, $staff] = $this->staffUser();
        $this->grantToGroup($staff, 'users.*', false);
        StaffPermission::create([
            'staff_id' => $staff->id,
            'route_id' => $this->route('users.*')->id,
            'allowed' => true,
        ]);

        $this->assertTrue($this->resolver->can($user, 'users.index'));
    }

    #[Test]
    public function deny_wins_between_two_groups(): void
    {
        [$user, $staff] = $this->staffUser();
        $this->grantToGroup($staff, 'users.index', true);
        $this->grantToGroup($staff, 'users.index', false);

        $this->assertFalse($this->resolver->can($user, 'users.index'));
    }

    #[Test]
    public function rules_are_cached_until_forgotten(): void
    {
        [$user, $staff] = $this->staffUser();
        $this->grantToGroup($staff, 'users.*', true);

        $this->assertTrue($this->resolver->can($user, 'users.index'));

        GroupPermission::query()->update(['allowed' => false]);

        $this->assertTrue($this->resolver->can($user, 'users.index'), 'stale until forget()');

        $this->resolver->forget($user->id);

        $this->assertFalse($this->resolver->can($user, 'users.index'));
    }

    /**
     * @return array{User, Staff}
     */
    private function staffUser(): array
    {
        $user = User::factory()->create();
        $staff = Staff::create(['user_id' => $user->id]);

        return [$user, $staff];
    }

    private function route(string $pattern): AclRoute
    {
        return AclRoute::firstOrCreate(['pattern' => $pattern]);
    }

    /**
     * Creates a new group containing $staff with one rule on $pattern.
     */
    private function grantToGroup(Staff $staff, string $pattern, bool $allowed): Group
    {
        $group = Group::create(['name' => 'group-'.Group::query()->count()]);
        $staff->groups()->attach($group->id);

        GroupPermission::create([
            'group_id' => $group->id,
            'route_id' => $this->route($pattern)->id,
            'allowed' => $allowed,
        ]);

        return $group;
    }
}
