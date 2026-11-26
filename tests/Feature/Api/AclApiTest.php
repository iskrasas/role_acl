<?php

namespace RoleAccess\Acl\Tests\Feature\Api;

use RoleAccess\Acl\Models\AclRoute;
use RoleAccess\Acl\Models\Group;
use RoleAccess\Acl\Models\GroupPermission;
use RoleAccess\Acl\Models\Staff;
use RoleAccess\Acl\Tests\Fixtures\User;
use RoleAccess\Acl\Services\AclResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RoleAccess\Acl\Tests\TestCase;

class AclApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_gets_401_and_user_without_rules_gets_403(): void
    {
        $this->getJson(route('api-acl.groups.index'))->assertUnauthorized();

        $this->actingAs(User::factory()->create())->getJson(route('api-acl.groups.index'))->assertForbidden();
    }

    #[Test]
    public function groups_list_in_the_api_envelope(): void
    {
        $admin = $this->staffIn('admin', ['*' => true]);
        Group::create(['name' => 'editors', 'description' => 'Editors']);

        $this->actingAs($admin)->getJson(route('api-acl.groups.index'))
            ->assertOk()
            ->assertJsonPath('meta.result', 'data')
            ->assertJsonPath('data.is_admin', true)
            ->assertJsonPath('data.groups.0.name', 'admin')
            ->assertJsonPath('data.groups.1.name', 'editors')
            ->assertJsonPath('data.groups.1.staff_count', 0);
    }

    #[Test]
    public function group_writes_are_admin_only(): void
    {
        $manager = $this->staffIn('managers', ['api-acl.*' => true]);
        $group = Group::create(['name' => 'editors']);

        $this->actingAs($manager)->putJson(route('api-acl.groups.store'), ['name' => 'support'])->assertForbidden();
        $this->actingAs($manager)->putJson(route('api-acl.groups.update', $group), ['name' => 'x'])->assertForbidden();
        $this->actingAs($manager)->deleteJson(route('api-acl.groups.delete', $group))->assertForbidden();
        $this->actingAs($manager)->getJson(route('api-acl.groups.show', $group))->assertOk();

        $this->assertDatabaseHas('acl_groups', ['name' => 'editors']);
        $this->assertDatabaseMissing('acl_groups', ['name' => 'support']);
    }

    #[Test]
    public function admin_creates_updates_and_deletes_a_group(): void
    {
        $admin = $this->staffIn('admin', ['*' => true]);

        $id = $this->actingAs($admin)->putJson(route('api-acl.groups.store'), ['name' => 'support'])
            ->assertOk()->assertJsonPath('data.group.name', 'support')->json('data.group.id');

        $this->actingAs($admin)->putJson(route('api-acl.groups.update', $id), ['name' => 'support2', 'description' => 'D'])
            ->assertOk()->assertJsonPath('data.group.description', 'D');

        $this->actingAs($admin)->deleteJson(route('api-acl.groups.delete', $id))
            ->assertOk()->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('acl_groups', ['id' => $id]);
    }

    #[Test]
    public function validation_errors_use_the_envelope_with_422(): void
    {
        $admin = $this->staffIn('admin', ['*' => true]);

        $this->actingAs($admin)->putJson(route('api-acl.groups.store'), ['name' => 'bad name'])
            ->assertStatus(422)
            ->assertJsonPath('meta.result', 'error')
            ->assertJsonStructure(['meta' => ['errors' => ['name']], 'error']);

        // service-level rule: the admin group name is reserved
        $this->actingAs($admin)->putJson(route('api-acl.groups.store'), ['name' => 'admin'])
            ->assertStatus(422)->assertJsonStructure(['meta' => ['errors' => ['name']]]);
    }

    #[Test]
    public function group_permissions_are_listed_and_saved(): void
    {
        $admin = $this->staffIn('admin', ['*' => true]);
        $group = Group::create(['name' => 'editors']);
        $users = AclRoute::create(['pattern' => 'users.index']);

        $this->actingAs($admin)->getJson(route('api-acl.groups.permissions', $group))
            ->assertOk()
            ->assertJsonFragment(['pattern' => 'users.index', 'state' => 'inherit']);

        $this->actingAs($admin)->putJson(route('api-acl.groups.permissions.update', $group), ['rules' => [$users->id => 'allow']])
            ->assertOk()->assertJsonPath('data.changed', 1);

        $this->assertDatabaseHas('acl_group_permissions', ['group_id' => $group->id, 'route_id' => $users->id, 'allowed' => true]);

        $this->actingAs($admin)->putJson(route('api-acl.groups.permissions.update', $group), ['rules' => [$users->id => 'bogus']])
            ->assertStatus(422);
    }

    #[Test]
    public function the_actor_cannot_remove_their_own_access_through_the_api(): void
    {
        $admin = $this->staffIn('admin', ['*' => true]);
        $adminGroup = Group::query()->where('name', 'admin')->firstOrFail();
        $star = AclRoute::query()->where('pattern', '*')->firstOrFail();

        $this->actingAs($admin)->putJson(route('api-acl.groups.permissions.update', $adminGroup), ['rules' => [$star->id => 'inherit']])
            ->assertStatus(422)->assertJsonStructure(['meta' => ['errors' => ['rules']]]);

        $this->assertTrue($this->app->make(AclResolver::class)->can($admin, 'api-acl.groups.index'));
    }

    #[Test]
    public function routes_crud(): void
    {
        $admin = $this->staffIn('admin', ['*' => true]);

        $id = $this->actingAs($admin)->putJson(route('api-acl.routes.store'), ['pattern' => 'posts.index', 'icon' => 'fa-newspaper', 'order_index' => 7])
            ->assertOk()->assertJsonPath('data.route.icon', 'fa-newspaper')->json('data.route.id');

        $this->actingAs($admin)->getJson(route('api-acl.routes.index'))
            ->assertOk()->assertJsonFragment(['pattern' => 'posts.index', 'order_index' => 7]);

        $this->actingAs($admin)->putJson(route('api-acl.routes.update', $id), ['pattern' => 'posts.index', 'icon' => ''])
            ->assertOk()->assertJsonPath('data.route.icon', null);

        $this->actingAs($admin)->putJson(route('api-acl.routes.store'), ['pattern' => 'posts.index'])->assertStatus(422);
        $this->actingAs($admin)->putJson(route('api-acl.routes.store'), ['pattern' => 'x.index', 'icon' => 'users'])->assertStatus(422);

        $this->actingAs($admin)->deleteJson(route('api-acl.routes.delete', $id))->assertOk();
        $this->assertDatabaseMissing('acl_routes', ['id' => $id]);
    }

    #[Test]
    public function staff_can_be_registered_and_assigned_by_an_admin(): void
    {
        $admin = $this->staffIn('admin', ['*' => true]);
        $editors = Group::create(['name' => 'editors']);
        $user = User::factory()->create(['email' => 'new@example.com']);

        $staffId = $this->actingAs($admin)->putJson(route('api-acl.staff.store'), ['email' => 'new@example.com'])
            ->assertOk()->assertJsonPath('data.staff.email', 'new@example.com')->json('data.staff.id');
        $this->assertDatabaseHas('acl_staff', ['id' => $staffId, 'user_id' => $user->id]);

        $this->actingAs($admin)->putJson(route('api-acl.staff.store'), ['email' => 'nobody@example.com'])->assertStatus(422);

        $this->actingAs($admin)->putJson(route('api-acl.staff.groups.update', $staffId), ['groups' => [$editors->id]])
            ->assertOk()->assertJsonPath('data.changed', 1);

        $this->actingAs($admin)->getJson(route('api-acl.staff.groups', $staffId))
            ->assertOk()->assertJsonFragment(['name' => 'editors', 'member' => true]);
    }

    #[Test]
    public function non_admin_staff_endpoints_are_limited_to_their_own_groups(): void
    {
        $manager = $this->staffIn('managers', ['api-acl.staff.*' => true]);
        $managers = Group::query()->where('name', 'managers')->firstOrFail();
        $editors = Group::create(['name' => 'editors']);
        $target = Staff::create(['user_id' => User::factory()->create(['name' => 'Olga Target'])->id]);
        $target->groups()->attach([$managers->id, $editors->id]);

        $this->actingAs($manager)->getJson(route('api-acl.staff.index'))
            ->assertOk()
            ->assertJsonPath('data.is_admin', false)
            ->assertJsonFragment(['name' => 'managers'])
            ->assertJsonMissing(['name' => 'editors']);

        $this->actingAs($manager)->getJson(route('api-acl.staff.groups', $target))
            ->assertOk()->assertJsonMissing(['name' => 'editors']);

        $this->actingAs($manager)->putJson(route('api-acl.staff.groups.update', $target), ['groups' => [$editors->id]])
            ->assertStatus(422)->assertJsonStructure(['meta' => ['errors' => ['groups']]]);

        $this->actingAs($manager)->putJson(route('api-acl.staff.groups.update', $target), ['groups' => []])->assertOk();
        $this->assertSame([$editors->id], $target->groups()->pluck('acl_groups.id')->all());
    }

    /**
     * @param  array<string, bool>  $rules
     */
    private function staffIn(string $groupName, array $rules): User
    {
        $user = User::factory()->create();
        $group = Group::query()->firstOrCreate(['name' => $groupName]);
        Staff::create(['user_id' => $user->id])->groups()->attach($group->id);

        foreach ($rules as $pattern => $allowed) {
            GroupPermission::query()->firstOrCreate(
                ['group_id' => $group->id, 'route_id' => AclRoute::firstOrCreate(['pattern' => $pattern])->id],
                ['allowed' => $allowed],
            );
        }

        return $user;
    }
}
