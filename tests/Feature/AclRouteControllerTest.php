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

class AclRouteControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_without_access_gets_403(): void
    {
        $this->actingAs(User::factory()->create())->get(route('acl.routes.index'))->assertForbidden();
    }

    #[Test]
    public function index_lists_patterns(): void
    {
        [$admin] = $this->admin();
        $this->route('users.*');

        $this->actingAs($admin)->get(route('acl.routes.index'))->assertOk()->assertSee('users.*');
    }

    #[Test]
    public function store_creates_a_route(): void
    {
        [$admin] = $this->admin();

        $this->actingAs($admin)
            ->post(route('acl.routes.store'), ['pattern' => 'posts.*', 'description' => 'All posts'])
            ->assertRedirect(route('acl.routes.index'));

        $this->assertDatabaseHas('acl_routes', ['pattern' => 'posts.*', 'description' => 'All posts']);
    }

    #[Test]
    public function icon_and_order_index_are_saved_and_default_to_empty_and_zero(): void
    {
        [$admin] = $this->admin();

        $this->actingAs($admin)->post(route('acl.routes.store'), ['pattern' => 'posts.index', 'icon' => 'fa-newspaper', 'order_index' => 30]);
        $this->assertDatabaseHas('acl_routes', ['pattern' => 'posts.index', 'icon' => 'fa-newspaper', 'order_index' => 30]);

        $this->actingAs($admin)->post(route('acl.routes.store'), ['pattern' => 'pages.index', 'icon' => '', 'order_index' => '']);
        $this->assertDatabaseHas('acl_routes', ['pattern' => 'pages.index', 'icon' => null, 'order_index' => 0]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIcons(): array
    {
        return [
            'no fa- prefix' => ['users'],
            'space' => ['fa users'],
            'markup' => ['fa-"><script>'],
            'uppercase' => ['fa-Users'],
        ];
    }

    #[Test]
    #[DataProvider('invalidIcons')]
    public function invalid_icon_is_rejected(string $icon): void
    {
        [$admin] = $this->admin();

        $this->actingAs($admin)->post(route('acl.routes.store'), ['pattern' => 'posts.index', 'icon' => $icon])
            ->assertSessionHasErrors('icon');

        $this->assertDatabaseMissing('acl_routes', ['pattern' => 'posts.index']);
    }

    #[Test]
    public function order_index_must_be_a_non_negative_integer(): void
    {
        [$admin] = $this->admin();

        foreach (['-1', 'abc', '70000', '1.5'] as $value) {
            $this->actingAs($admin)->post(route('acl.routes.store'), ['pattern' => 'posts.index', 'order_index' => $value])
                ->assertSessionHasErrors('order_index');
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPatterns(): array
    {
        return [
            'empty' => [''],
            'double dot' => ['users..index'],
            'wildcard in the middle' => ['users.*.index'],
            'leading wildcard' => ['*users'],
            'space' => ['users index'],
            'trailing dot' => ['users.'],
        ];
    }

    #[Test]
    #[DataProvider('invalidPatterns')]
    public function invalid_pattern_is_rejected(string $pattern): void
    {
        [$admin] = $this->admin();

        $this->actingAs($admin)->post(route('acl.routes.store'), ['pattern' => $pattern])
            ->assertSessionHasErrors('pattern');

        $this->assertDatabaseMissing('acl_routes', ['pattern' => $pattern]);
    }

    #[Test]
    public function duplicate_pattern_is_rejected_but_own_pattern_can_be_resaved(): void
    {
        [$admin] = $this->admin();
        $first = $this->route('users.index');
        $this->route('users.edit');

        $this->actingAs($admin)->put(route('acl.routes.update', $first), ['pattern' => 'users.edit'])
            ->assertSessionHasErrors('pattern');

        $this->actingAs($admin)->put(route('acl.routes.update', $first), ['pattern' => 'users.index', 'description' => 'same'])
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function rename_refreshes_cached_rules_of_affected_users(): void
    {
        [$admin] = $this->admin();
        $group = Group::create(['name' => 'editors']);
        $member = User::factory()->create();
        Staff::create(['user_id' => $member->id])->groups()->attach($group->id);
        $route = $this->route('users.index');
        GroupPermission::create(['group_id' => $group->id, 'route_id' => $route->id, 'allowed' => true]);
        $resolver = $this->app->make(AclResolver::class);

        $this->assertTrue($resolver->can($member, 'users.index'));   // caches the rules

        $this->actingAs($admin)->put(route('acl.routes.update', $route), ['pattern' => 'people.index']);

        $this->assertFalse($resolver->can($member, 'users.index'));
        $this->assertTrue($resolver->can($member, 'people.index'));
    }

    #[Test]
    public function destroy_removes_the_route_and_its_rules(): void
    {
        [$admin] = $this->admin();
        $group = Group::create(['name' => 'editors']);
        $route = $this->route('users.index');
        GroupPermission::create(['group_id' => $group->id, 'route_id' => $route->id, 'allowed' => true]);

        $this->actingAs($admin)->delete(route('acl.routes.destroy', $route))
            ->assertRedirect(route('acl.routes.index'));

        $this->assertDatabaseMissing('acl_routes', ['id' => $route->id]);
        $this->assertDatabaseMissing('acl_group_permissions', ['route_id' => $route->id]);
    }

    #[Test]
    public function deleting_or_renaming_the_actors_own_access_is_rolled_back(): void
    {
        [$admin] = $this->admin();
        $star = AclRoute::where('pattern', '*')->firstOrFail();

        $this->actingAs($admin)->delete(route('acl.routes.destroy', $star))->assertSessionHasErrors('route');
        $this->assertDatabaseHas('acl_routes', ['id' => $star->id]);

        $this->actingAs($admin)->put(route('acl.routes.update', $star), ['pattern' => 'nothing.*'])
            ->assertSessionHasErrors('pattern');
        $this->assertDatabaseHas('acl_routes', ['id' => $star->id, 'pattern' => '*']);

        $this->assertTrue($this->app->make(AclResolver::class)->can($admin, 'acl.routes.update'));
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
