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

class StaffGroupControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_without_access_gets_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('acl.staff.index'))
            ->assertForbidden();
    }

    #[Test]
    public function index_lists_staff_with_their_groups(): void
    {
        [$admin, $adminGroup] = $this->admin();
        $other = Staff::create(['user_id' => User::factory()->create(['name' => 'Olga Manager'])->id]);
        $other->groups()->attach(Group::create(['name' => 'editors'])->id);

        $this->actingAs($admin)->get(route('acl.staff.index'))
            ->assertOk()
            ->assertSee('Olga Manager')
            ->assertSee('editors')
            ->assertSee($adminGroup->name);
    }

    #[Test]
    public function many_groups_wrap_in_a_spaced_container(): void
    {
        [$admin] = $this->admin();
        $member = Staff::create(['user_id' => User::factory()->create()->id]);

        foreach (range(1, 12) as $i) {
            $member->groups()->attach(Group::create(['name' => "group-{$i}"])->id);
        }

        $this->actingAs($admin)->get(route('acl.staff.index'))
            ->assertOk()
            ->assertSee('d-flex flex-wrap gap-1', false)
            ->assertSee('group-12');
    }

    #[Test]
    public function store_registers_a_user_as_staff_and_redirects_to_the_group_selection(): void
    {
        [$admin] = $this->admin();
        $user = User::factory()->create(['email' => 'new@example.com']);

        $response = $this->actingAs($admin)->post(route('acl.staff.store'), ['email' => 'new@example.com']);

        $staff = Staff::query()->where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('acl.staff.edit', $staff));
        $this->assertFalse($this->app->make(AclResolver::class)->can($user, 'users.index'), 'no groups => no access');
    }

    #[Test]
    public function store_is_idempotent_and_rejects_unknown_emails(): void
    {
        [$admin] = $this->admin();
        $user = User::factory()->create(['email' => 'new@example.com']);

        $this->actingAs($admin)->post(route('acl.staff.store'), ['email' => 'new@example.com']);
        $this->actingAs($admin)->post(route('acl.staff.store'), ['email' => 'new@example.com']);
        $this->assertSame(1, Staff::query()->where('user_id', $user->id)->count());

        $this->actingAs($admin)->post(route('acl.staff.store'), ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors('email');
    }

    #[Test]
    public function edit_shows_groups_with_current_memberships_checked(): void
    {
        [$admin] = $this->admin();
        $staff = Staff::create(['user_id' => User::factory()->create()->id]);
        $member = Group::create(['name' => 'editors']);
        Group::create(['name' => 'support']);
        $staff->groups()->attach($member->id);

        $this->actingAs($admin)->get(route('acl.staff.edit', $staff))
            ->assertOk()
            ->assertSee('editors')
            ->assertSee('support')
            ->assertSee('value="'.$member->id.'" checked', false);
    }

    #[Test]
    public function update_replaces_memberships_and_refreshes_the_cached_rules(): void
    {
        [$admin] = $this->admin();
        $user = User::factory()->create();
        $staff = Staff::create(['user_id' => $user->id]);
        $editors = Group::create(['name' => 'editors']);
        $support = Group::create(['name' => 'support']);
        GroupPermission::create(['group_id' => $editors->id, 'route_id' => $this->route('users.index')->id, 'allowed' => true]);
        $staff->groups()->attach($support->id);

        $resolver = $this->app->make(AclResolver::class);
        $this->assertFalse($resolver->can($user, 'users.index'), 'primes the cache');

        $this->actingAs($admin)->put(route('acl.staff.update', $staff), ['groups' => [$editors->id]])
            ->assertRedirect(route('acl.staff.edit', $staff));

        $this->assertSame([$editors->id], $staff->groups()->pluck('acl_groups.id')->all());
        $this->assertTrue($resolver->can($user, 'users.index'));
    }

    #[Test]
    public function update_without_groups_removes_all_memberships(): void
    {
        [$admin] = $this->admin();
        $staff = Staff::create(['user_id' => User::factory()->create()->id]);
        $staff->groups()->attach(Group::create(['name' => 'editors'])->id);

        $this->actingAs($admin)->put(route('acl.staff.update', $staff), [])->assertRedirect();

        $this->assertSame(0, $staff->groups()->count());
    }

    #[Test]
    public function unknown_group_id_is_rejected(): void
    {
        [$admin] = $this->admin();
        $staff = Staff::create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($admin)->put(route('acl.staff.update', $staff), ['groups' => [999999]])
            ->assertSessionHasErrors('groups.0');
    }

    #[Test]
    public function removing_the_actors_own_access_is_rolled_back(): void
    {
        [$admin, $adminGroup] = $this->admin();
        $adminStaff = Staff::query()->where('user_id', $admin->id)->firstOrFail();

        $this->actingAs($admin)->put(route('acl.staff.update', $adminStaff), ['groups' => []])
            ->assertSessionHasErrors('groups');

        $this->assertSame([$adminGroup->id], $adminStaff->groups()->pluck('acl_groups.id')->all());
        $this->assertTrue($this->app->make(AclResolver::class)->can($admin, 'acl.staff.update'));
    }

    #[Test]
    public function non_admin_sees_only_their_own_groups(): void
    {
        [$manager, $managers] = $this->manager();
        $editors = Group::create(['name' => 'editors']);
        $target = Staff::create(['user_id' => User::factory()->create(['name' => 'Olga Target'])->id]);
        $target->groups()->attach([$managers->id, $editors->id]);

        $this->actingAs($manager)->get(route('acl.staff.index'))
            ->assertOk()
            ->assertSee('Olga Target')
            ->assertSee('managers')
            ->assertDontSee('editors');

        $this->actingAs($manager)->get(route('acl.staff.edit', $target))
            ->assertOk()
            ->assertSee('managers')
            ->assertDontSee('editors');
    }

    #[Test]
    public function non_admin_changes_only_their_own_groups_and_leaves_the_rest(): void
    {
        [$manager, $managers] = $this->manager();
        $editors = Group::create(['name' => 'editors']);
        $target = Staff::create(['user_id' => User::factory()->create()->id]);
        $target->groups()->attach($editors->id);

        $this->actingAs($manager)->put(route('acl.staff.update', $target), ['groups' => [$managers->id]])->assertRedirect();
        $this->assertEqualsCanonicalizing([$managers->id, $editors->id], $target->groups()->pluck('acl_groups.id')->all());

        $this->actingAs($manager)->put(route('acl.staff.update', $target), [])->assertRedirect();
        $this->assertSame([$editors->id], $target->groups()->pluck('acl_groups.id')->all());
    }

    #[Test]
    public function non_admin_cannot_assign_a_foreign_group(): void
    {
        [$manager] = $this->manager();
        $editors = Group::create(['name' => 'editors']);
        $target = Staff::create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($manager)->put(route('acl.staff.update', $target), ['groups' => [$editors->id]])
            ->assertSessionHasErrors('groups');

        $this->assertSame(0, $target->groups()->count());
    }

    #[Test]
    public function non_admin_can_register_new_staff_and_gets_no_groups_yet(): void
    {
        [$manager] = $this->manager();
        $user = User::factory()->create(['email' => 'new@example.com']);

        $response = $this->actingAs($manager)->post(route('acl.staff.store'), ['email' => 'new@example.com']);

        $staff = Staff::query()->where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('acl.staff.edit', $staff));
        $this->assertSame(0, $staff->groups()->count(), 'registering grants no groups');
    }

    #[Test]
    public function admin_can_put_a_user_into_any_group(): void
    {
        [$admin] = $this->admin();
        $groups = [Group::create(['name' => 'editors']), Group::create(['name' => 'support'])];
        $target = Staff::create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($admin)->put(route('acl.staff.update', $target), ['groups' => array_map(fn ($g) => $g->id, $groups)])
            ->assertRedirect();

        $this->assertSame(2, $target->groups()->count());
    }

    /**
     * Non-admin staff member who may use the staff screens.
     *
     * @return array{User, Group}
     */
    private function manager(): array
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'managers']);
        Staff::create(['user_id' => $user->id])->groups()->attach($group->id);
        GroupPermission::create(['group_id' => $group->id, 'route_id' => $this->route('acl.staff.*')->id, 'allowed' => true]);

        return [$user, $group];
    }

    /**
     * @return array{User, Group}
     */
    private function admin(): array
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'admin']);
        Staff::create(['user_id' => $user->id])->groups()->attach($group->id);
        GroupPermission::create(['group_id' => $group->id, 'route_id' => $this->route('*')->id, 'allowed' => true]);

        return [$user, $group];
    }

    private function route(string $pattern): AclRoute
    {
        return AclRoute::firstOrCreate(['pattern' => $pattern]);
    }
}
