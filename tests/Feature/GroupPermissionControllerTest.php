<?php

namespace RoleAccess\Acl\Tests\Feature;

use RoleAccess\Acl\Models\AclRoute;
use RoleAccess\Acl\Models\Group;
use RoleAccess\Acl\Models\GroupPermission;
use RoleAccess\Acl\Models\Staff;
use RoleAccess\Acl\Tests\Fixtures\User;
use RoleAccess\Acl\Services\AclResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RoleAccess\Acl\Tests\TestCase;

class GroupPermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_without_access_gets_403(): void
    {
        $group = Group::create(['name' => 'editors']);

        $this->actingAs(User::factory()->create())
            ->get(route('acl.groups.permissions.edit', $group))
            ->assertForbidden();
    }

    #[Test]
    public function admin_sees_groups_and_the_permission_matrix(): void
    {
        [$admin] = $this->admin();
        $group = Group::create(['name' => 'editors', 'description' => 'Editors']);
        $this->route('users.index');

        $this->actingAs($admin)->get(route('acl.groups.index'))
            ->assertOk()->assertSee('editors');

        $this->actingAs($admin)->get(route('acl.groups.permissions.edit', $group))
            ->assertOk()->assertSee('users.index');
    }

    #[Test]
    public function update_creates_changes_and_removes_rules(): void
    {
        [$admin] = $this->admin();
        $group = Group::create(['name' => 'editors']);
        $index = $this->route('users.index');
        $wildcard = $this->route('users.*');

        $this->actingAs($admin)
            ->put(route('acl.groups.permissions.update', $group), ['rules' => [$index->id => 'allow', $wildcard->id => 'deny']])
            ->assertRedirect(route('acl.groups.permissions.edit', $group));

        $this->assertDatabaseHas('acl_group_permissions', ['group_id' => $group->id, 'route_id' => $index->id, 'allowed' => true]);
        $this->assertDatabaseHas('acl_group_permissions', ['group_id' => $group->id, 'route_id' => $wildcard->id, 'allowed' => false]);

        $this->actingAs($admin)
            ->put(route('acl.groups.permissions.update', $group), ['rules' => [$index->id => 'deny', $wildcard->id => 'inherit']]);

        $this->assertDatabaseHas('acl_group_permissions', ['group_id' => $group->id, 'route_id' => $index->id, 'allowed' => false]);
        $this->assertDatabaseMissing('acl_group_permissions', ['group_id' => $group->id, 'route_id' => $wildcard->id]);
    }

    #[Test]
    public function invalid_state_is_rejected(): void
    {
        [$admin] = $this->admin();
        $group = Group::create(['name' => 'editors']);
        $route = $this->route('users.index');

        $this->actingAs($admin)
            ->put(route('acl.groups.permissions.update', $group), ['rules' => [$route->id => 'maybe']])
            ->assertSessionHasErrors('rules.'.$route->id);

        $this->assertDatabaseCount('acl_group_permissions', 1); // only the admin's own "*" rule
    }

    #[Test]
    public function members_cache_is_refreshed_after_update(): void
    {
        [$admin] = $this->admin();
        $group = Group::create(['name' => 'editors']);
        $member = User::factory()->create();
        Staff::create(['user_id' => $member->id])->groups()->attach($group->id);
        $route = $this->route('users.index');
        $resolver = $this->app->make(AclResolver::class);

        $this->assertFalse($resolver->can($member, 'users.index'));   // caches "no rules"

        $this->actingAs($admin)
            ->put(route('acl.groups.permissions.update', $group), ['rules' => [$route->id => 'allow']]);

        $this->assertTrue($resolver->can($member, 'users.index'));
    }

    #[Test]
    public function change_that_locks_the_actor_out_is_rolled_back(): void
    {
        [$admin, $adminGroup] = $this->admin();
        $star = AclRoute::where('pattern', '*')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('acl.groups.permissions.update', $adminGroup), ['rules' => [$star->id => 'deny']])
            ->assertSessionHasErrors('rules');

        $this->assertDatabaseHas('acl_group_permissions', ['group_id' => $adminGroup->id, 'route_id' => $star->id, 'allowed' => true]);
        $this->assertTrue($this->app->make(AclResolver::class)->can($admin, 'acl.groups.permissions.update'));
    }

    /**
     * A staff user in a group that allows everything ("*").
     *
     * @return array{User, Group}
     */
    private function admin(): array
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'admins']);
        Staff::create(['user_id' => $user->id])->groups()->attach($group->id);
        GroupPermission::create(['group_id' => $group->id, 'route_id' => $this->route('*')->id, 'allowed' => true]);

        return [$user, $group];
    }

    private function route(string $pattern): AclRoute
    {
        return AclRoute::firstOrCreate(['pattern' => $pattern]);
    }
}
