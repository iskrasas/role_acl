<?php

namespace RoleAccess\Acl\Tests\Feature;

use RoleAccess\Acl\Models\AclRoute;
use RoleAccess\Acl\Models\Group;
use RoleAccess\Acl\Models\GroupPermission;
use RoleAccess\Acl\Models\Staff;
use RoleAccess\Acl\Tests\Fixtures\User;
use RoleAccess\Acl\Services\AclMenu;
use RoleAccess\Acl\Services\AclResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RoleAccess\Acl\Tests\TestCase;

class AclMenuTest extends TestCase
{
    use RefreshDatabase;

    private AclMenu $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->menu = $this->app->make(AclMenu::class);
    }

    #[Test]
    public function only_allowed_index_routes_are_listed(): void
    {
        $user = $this->staffWith(['users.*' => true, 'staff.index' => true, 'banners.*' => false]);

        $routes = $this->menu->allowedRoutes($user);

        $this->assertContains('users.index', $routes);
        $this->assertContains('staff.index', $routes);
        $this->assertNotContains('banners.index', $routes);
        $this->assertNotContains('news.index', $routes);
    }

    #[Test]
    public function only_get_routes_without_url_parameters_qualify(): void
    {
        $routes = $this->menu->allowedRoutes($this->staffWith(['*' => true]));

        $this->assertContains('users.index', $routes);
        $this->assertContains('users.create', $routes);   // not only "*.index"
        $this->assertContains('welcome', $routes);
        $this->assertNotContains('survey_questions.index', $routes); // needs {survey}
        $this->assertNotContains('users.edit', $routes);             // needs {user}
    }

    #[Test]
    public function a_non_index_route_with_an_icon_is_shown_and_active_only_on_itself(): void
    {
        $user = $this->staffWith(['users.create' => true]);
        $this->menuItem('users.create', icon: 'fa-user-plus', description: 'Новый пользователь');

        $items = $this->menu->itemsFor($user);

        $this->assertSame(['users.create'], array_column($items, 'name'));
        $this->assertSame('Новый пользователь', $items[0]['label']);
        $this->assertFalse($items[0]['active']);
    }

    #[Test]
    public function user_without_staff_record_and_guest_get_nothing(): void
    {
        $this->assertSame([], $this->menu->allowedRoutes(User::factory()->create()));
        $this->assertSame([], $this->menu->itemsFor(null));
    }

    #[Test]
    public function list_is_cached_until_the_rules_are_forgotten(): void
    {
        $user = $this->staffWith(['users.index' => true]);

        $this->assertSame(['users.index'], $this->menu->allowedRoutes($user));

        GroupPermission::query()->update(['allowed' => false]);

        $this->assertSame(['users.index'], $this->menu->allowedRoutes($user), 'stale until forget()');

        $this->app->make(AclResolver::class)->forget($user->id);

        $this->assertSame([], $this->menu->allowedRoutes($user));
    }

    #[Test]
    public function only_routes_with_an_icon_are_shown(): void
    {
        $user = $this->staffWith(['users.index' => true, 'staff.index' => true, 'news.index' => true, 'banners.index' => true]);
        $this->menuItem('users.index', icon: 'fa-users');
        $this->menuItem('staff.index', icon: '');          // empty icon => hidden
        $this->menuItem('news.index', icon: null);         // no icon => hidden
        // banners.index: no attributes at all => hidden

        $this->assertSame(['users.index'], array_column($this->menu->itemsFor($user), 'name'));
    }

    #[Test]
    public function an_icon_does_not_grant_access(): void
    {
        $user = $this->staffWith(['users.index' => true]);
        $this->menuItem('users.index', icon: 'fa-users');
        $this->menuItem('banners.index', icon: 'fa-cog');   // not allowed for this user

        $this->assertSame(['users.index'], array_column($this->menu->itemsFor($user), 'name'));
    }

    #[Test]
    public function items_are_ordered_by_order_index_then_pattern(): void
    {
        $user = $this->staffWith(['*' => true]);
        $this->menuItem('staff.index', icon: 'fa-a', order: 5);
        $this->menuItem('users.index', icon: 'fa-b', order: 1);
        $this->menuItem('news.index', icon: 'fa-c', order: 5);
        $this->menuItem('banners.index', icon: 'fa-d', order: 5);

        $this->assertSame(
            ['users.index', 'banners.index', 'news.index', 'staff.index'],
            array_column($this->menu->itemsFor($user), 'name'),
        );
    }

    #[Test]
    public function item_carries_icon_url_and_label_from_the_route_row(): void
    {
        $user = $this->staffWith(['users.index' => true, 'acl.groups.index' => true]);
        $this->menuItem('users.index', icon: 'fa-users', order: 1, description: 'Пользователи сайта');
        $this->menuItem('acl.groups.index', icon: 'fa-layer-group', order: 2);   // no description => derived label

        $items = $this->menu->itemsFor($user);

        $this->assertSame('Пользователи сайта', $items[0]['label']);
        $this->assertSame('fa-users', $items[0]['icon']);
        $this->assertSame(route('users.index'), $items[0]['url']);
        $this->assertSame('Acl groups', $items[1]['label']);
    }

    #[Test]
    public function edits_to_icon_order_and_label_show_without_clearing_the_cache(): void
    {
        $user = $this->staffWith(['users.index' => true]);
        $route = $this->menuItem('users.index', icon: 'fa-users', description: 'Клиенты');

        $this->assertSame('Клиенты', $this->menu->itemsFor($user)[0]['label']);

        $route->update(['description' => 'Покупатели', 'icon' => 'fa-user']);
        $item = $this->menu->itemsFor($user)[0];
        $this->assertSame('Покупатели', $item['label']);
        $this->assertSame('fa-user', $item['icon']);

        $route->update(['icon' => '']);
        $this->assertSame([], $this->menu->itemsFor($user));
    }

    #[Test]
    public function sidebar_renders_only_what_the_user_may_open(): void
    {
        $user = $this->staffWith(['welcome' => true, 'users.index' => true]);
        $this->menuItem('users.index', icon: 'fa-users', description: 'Пользователи сайта');
        $this->menuItem('banners.index', icon: 'fa-cog', description: 'Баннеры');   // allowed? no

        $this->actingAs($user)->get(route('welcome'))
            ->assertOk()
            ->assertSee('Пользователи сайта')
            ->assertSee('fa-users')
            ->assertDontSee('Баннеры');
    }

    /**
     * Sets the sidebar attributes of an acl_routes row (created if missing).
     */
    private function menuItem(string $pattern, ?string $icon = null, int $order = 0, ?string $description = null): AclRoute
    {
        $route = AclRoute::firstOrCreate(['pattern' => $pattern]);
        $route->update(['icon' => $icon, 'order_index' => $order, 'description' => $description]);

        return $route;
    }

    /**
     * Staff user in a fresh group with the given pattern => allowed rules.
     *
     * @param  array<string, bool>  $rules
     */
    private function staffWith(array $rules): User
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'group-'.Group::query()->count()]);
        Staff::create(['user_id' => $user->id])->groups()->attach($group->id);

        foreach ($rules as $pattern => $allowed) {
            GroupPermission::create([
                'group_id' => $group->id,
                'route_id' => AclRoute::firstOrCreate(['pattern' => $pattern])->id,
                'allowed' => $allowed,
            ]);
        }

        return $user;
    }
}
