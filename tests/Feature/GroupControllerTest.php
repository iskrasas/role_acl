<?php

namespace RoleAccess\Acl\Tests\Feature;

use RoleAccess\Acl\Models\AclRoute;
use RoleAccess\Acl\Models\Group;
use RoleAccess\Acl\Models\GroupPermission;
use RoleAccess\Acl\Models\Staff;
use RoleAccess\Acl\Tests\Fixtures\User;
use RoleAccess\Acl\Services\AclResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RoleAccess\Acl\Tests\TestCase;

class GroupControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function non_admin_is_refused_even_with_access_to_the_routes(): void
    {
        $manager = $this->staffIn('managers', ['acl.groups.*' => true]);
        $group = Group::create(['name' => 'editors']);

        $this->actingAs($manager)->get(route('acl.groups.create'))->assertForbidden();
        $this->actingAs($manager)->post(route('acl.groups.store'), ['name' => 'support'])->assertForbidden();
        $this->actingAs($manager)->put(route('acl.groups.update', $group), ['name' => 'renamed'])->assertForbidden();
        $this->actingAs($manager)->delete(route('acl.groups.destroy', $group))->assertForbidden();

        $this->assertDatabaseHas('acl_groups', ['name' => 'editors']);
        $this->assertDatabaseMissing('acl_groups', ['name' => 'support']);
    }

    #[Test]
    public function admin_without_route_access_is_refused_too(): void
    {
        $this->actingAs($this->staffIn('admin', []))->get(route('acl.groups.create'))->assertForbidden();
    }

    #[Test]
    public function index_shows_management_buttons_to_admins_only(): void
    {
        $group = Group::create(['name' => 'editors']);

        $this->actingAs($this->staffIn('admin', ['*' => true]))->get(route('acl.groups.index'))
            ->assertOk()->assertSee(route('acl.groups.create'), false)->assertSee(route('acl.groups.edit', $group), false);

        $this->actingAs($this->staffIn('managers', ['acl.groups.*' => true]))->get(route('acl.groups.index'))
            ->assertOk()->assertDontSee(route('acl.groups.create'), false)->assertDontSee(route('acl.groups.edit', $group), false);
    }

    #[Test]
    public function admin_creates_a_group(): void
    {
        $this->actingAs($this->admin())->get(route('acl.groups.create'))->assertOk();

        $this->actingAs($this->admin())->post(route('acl.groups.store'), ['name' => 'support', 'description' => ''])
            ->assertRedirect(route('acl.groups.index'));

        $this->assertDatabaseHas('acl_groups', ['name' => 'support', 'description' => null]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'space' => ['my group'],
            'cyrillic' => ['группа'],
            'too long' => [str_repeat('a', 65)],
            'markup' => ['<b>'],
        ];
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function invalid_name_is_rejected(string $name): void
    {
        $this->actingAs($this->admin())->post(route('acl.groups.store'), ['name' => $name])
            ->assertSessionHasErrors('name');
    }

    #[Test]
    public function duplicate_name_is_rejected_but_own_name_can_be_resaved(): void
    {
        $admin = $this->admin();
        $editors = Group::create(['name' => 'editors']);
        Group::create(['name' => 'support']);

        $this->actingAs($admin)->post(route('acl.groups.store'), ['name' => 'editors'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->put(route('acl.groups.update', $editors), ['name' => 'support'])->assertSessionHasErrors('name');

        $this->actingAs($admin)->put(route('acl.groups.update', $editors), ['name' => 'editors', 'description' => 'Same'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('acl_groups', ['name' => 'editors', 'description' => 'Same']);
    }

    #[Test]
    public function the_admin_group_name_is_reserved(): void
    {
        $admin = $this->admin();
        $editors = Group::create(['name' => 'editors']);

        $this->actingAs($admin)->post(route('acl.groups.store'), ['name' => 'admin'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->put(route('acl.groups.update', $editors), ['name' => 'admin'])->assertSessionHasErrors('name');

        $this->assertSame(1, Group::query()->where('name', 'admin')->count());
    }

    #[Test]
    public function the_admin_group_cannot_be_renamed_or_deleted_but_its_description_can_change(): void
    {
        $admin = $this->admin();
        $adminGroup = Group::query()->where('name', 'admin')->firstOrFail();

        $this->actingAs($admin)->put(route('acl.groups.update', $adminGroup), ['name' => 'root'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->delete(route('acl.groups.destroy', $adminGroup))->assertSessionHasErrors('group');
        $this->assertDatabaseHas('acl_groups', ['name' => 'admin']);

        $this->actingAs($admin)->put(route('acl.groups.update', $adminGroup), ['name' => 'admin', 'description' => 'Everything'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('acl_groups', ['name' => 'admin', 'description' => 'Everything']);
    }

    #[Test]
    public function destroy_removes_the_group_with_memberships_and_rules_and_refreshes_members(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        $group = Group::create(['name' => 'editors']);
        Staff::create(['user_id' => $member->id])->groups()->attach($group->id);
        GroupPermission::create(['group_id' => $group->id, 'route_id' => $this->route('users.index')->id, 'allowed' => true]);

        $resolver = $this->app->make(AclResolver::class);
        $this->assertTrue($resolver->can($member, 'users.index'), 'primes the cache');

        $this->actingAs($admin)->delete(route('acl.groups.destroy', $group))->assertRedirect(route('acl.groups.index'));

        $this->assertDatabaseMissing('acl_groups', ['name' => 'editors']);
        $this->assertDatabaseMissing('acl_staff_group', ['group_id' => $group->id]);
        $this->assertDatabaseMissing('acl_group_permissions', ['group_id' => $group->id]);
        $this->assertFalse($resolver->can($member, 'users.index'));
    }

    #[Test]
    public function deleting_a_group_that_gives_the_actor_their_access_is_rolled_back(): void
    {
        $user = User::factory()->create();
        $adminGroup = Group::create(['name' => 'admin']);   // admin, but access comes from another group
        $powers = Group::create(['name' => 'powers']);
        $staff = Staff::create(['user_id' => $user->id]);
        $staff->groups()->attach([$adminGroup->id, $powers->id]);
        GroupPermission::create(['group_id' => $powers->id, 'route_id' => $this->route('*')->id, 'allowed' => true]);

        $this->actingAs($user)->delete(route('acl.groups.destroy', $powers))->assertSessionHasErrors('group');

        $this->assertDatabaseHas('acl_groups', ['name' => 'powers']);
        $this->assertTrue($this->app->make(AclResolver::class)->can($user, 'acl.groups.update'));
    }

    private function admin(): User
    {
        return $this->staffIn('admin', ['*' => true]);
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
                ['group_id' => $group->id, 'route_id' => $this->route($pattern)->id],
                ['allowed' => $allowed],
            );
        }

        return $user;
    }

    private function route(string $pattern): AclRoute
    {
        return AclRoute::firstOrCreate(['pattern' => $pattern]);
    }
}
