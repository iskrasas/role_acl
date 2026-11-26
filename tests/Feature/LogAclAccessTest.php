<?php

namespace RoleAccess\Acl\Tests\Feature;

use RoleAccess\Acl\Http\Middleware\LogAclAccess;
use RoleAccess\Acl\Models\AclLog;
use RoleAccess\Acl\Models\AclRoute;
use RoleAccess\Acl\Models\Group;
use RoleAccess\Acl\Models\GroupPermission;
use RoleAccess\Acl\Models\Staff;
use RoleAccess\Acl\Services\AclResolver;
use RoleAccess\Acl\Tests\Fixtures\User;
use RoleAccess\Acl\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\Test;

/**
 * The middleware is driven directly (handle + terminate) so the app's routes do not get in the way.
 */
class LogAclAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_logs_route_staff_uri_and_object_id(): void
    {
        $user = User::factory()->create();
        $staff = Staff::create(['user_id' => $user->id]);
        $route = AclRoute::create(['pattern' => 'actions.update']);

        $this->dispatchRequest($this->request($user, 'POST', '/actions/5/edit?x=1', ['name' => 'N'], 'actions/{action}/edit', 'actions.update'));

        $this->assertDatabaseCount('acl_logs', 1);
        $this->assertDatabaseHas('acl_logs', [
            'route_id' => $route->id,
            'staff_id' => $staff->id,
            'method' => 'POST',
            'uri' => '/actions/5/edit',   // no query string
            'object_id' => '5',
        ]);
    }

    #[Test]
    public function the_response_status_is_logged_including_denied_403(): void
    {
        $user = User::factory()->create();

        $this->dispatchRequest($this->request($user, 'GET', '/users', [], 'users', 'users.index'), 200);
        $this->dispatchRequest($this->request($user, 'POST', '/users', ['name' => 'N'], 'users', 'users.store'), 403);
        $this->dispatchRequest($this->request($user, 'GET', '/actions/9', [], 'actions/{action}', 'actions.show'), 404);

        $this->assertDatabaseHas('acl_logs', ['uri' => '/users', 'method' => 'GET', 'status_code' => 200]);
        $this->assertDatabaseHas('acl_logs', ['uri' => '/users', 'method' => 'POST', 'status_code' => 403]);
        $this->assertDatabaseHas('acl_logs', ['uri' => '/actions/9', 'status_code' => 404, 'object_id' => '9']);
    }

    #[Test]
    public function a_request_denied_by_role_access_is_logged_with_403_through_the_real_stack(): void
    {
        RouteFacade::middleware([LogAclAccess::class, 'roleAccess'])
            ->get('/_acl_log/actions/{action}', fn () => 'ok')->name('actions.show');

        $denied = User::factory()->create();
        $staff = Staff::create(['user_id' => $denied->id]);

        $this->actingAs($denied)->get('/_acl_log/actions/7')->assertForbidden();

        $this->assertDatabaseHas('acl_logs', [
            'staff_id' => $staff->id, 'method' => 'GET', 'uri' => '/_acl_log/actions/7', 'object_id' => '7', 'status_code' => 403,
        ]);

        $group = Group::create(['name' => 'viewers']);
        $staff->groups()->attach($group->id);
        GroupPermission::create(['group_id' => $group->id, 'route_id' => AclRoute::create(['pattern' => 'actions.show'])->id, 'allowed' => true]);

        $this->app->make(AclResolver::class)->forget($denied->id);   // the denial above is cached

        $this->actingAs($denied)->get('/_acl_log/actions/8')->assertOk();

        $this->assertDatabaseHas('acl_logs', ['uri' => '/_acl_log/actions/8', 'status_code' => 200]);
    }

    #[Test]
    public function the_row_is_written_before_the_request_runs_so_a_crash_still_leaves_a_log(): void
    {
        $user = User::factory()->create();
        $request = $this->request($user, 'POST', '/users', ['name' => 'N', 'password' => 'p'], 'users', 'users.store');

        try {
            (new LogAclAccess())->handle($request, function (): never {
                throw new \RuntimeException('boom');   // a 500 / fatal: terminate() never gets a response
            });
            $this->fail('the exception must propagate');
        } catch (\RuntimeException) {
        }

        $log = AclLog::query()->firstOrFail();
        $this->assertSame('/users', $log->uri);
        $this->assertNull($log->status_code);
        $this->assertSame('***', $log->payload->payload['password']);
    }

    #[Test]
    public function the_status_is_filled_in_after_the_response(): void
    {
        $user = User::factory()->create();
        $request = $this->request($user, 'GET', '/users', [], 'users', 'users.index');
        $middleware = new LogAclAccess();

        $response = $middleware->handle($request, function () {
            // while the request runs the row exists already, without a status
            $this->assertNull(AclLog::query()->firstOrFail()->status_code);

            return new Response('ok', 201);
        });
        $middleware->terminate($request, $response);

        $this->assertSame(201, AclLog::query()->firstOrFail()->status_code);
    }

    #[Test]
    public function object_id_is_the_last_route_parameter(): void
    {
        $user = User::factory()->create();

        $this->dispatchRequest($this->request($user, 'GET', '/erid/news/77', [], 'erid/{table}/{adv_id}', 'erid.edit'));

        $this->assertDatabaseHas('acl_logs', ['uri' => '/erid/news/77', 'object_id' => '77']);
    }

    #[Test]
    public function route_without_parameters_and_without_an_acl_row_gets_nulls(): void
    {
        $user = User::factory()->create();

        $this->dispatchRequest($this->request($user, 'GET', '/users', [], 'users', 'users.index'));
        $this->dispatchRequest($this->request($user, 'GET', '/unnamed', [], 'unnamed', null));

        $this->assertDatabaseHas('acl_logs', ['uri' => '/users', 'route_id' => null, 'staff_id' => null, 'object_id' => null]);
        $this->assertDatabaseHas('acl_logs', ['uri' => '/unnamed', 'route_id' => null]);
    }

    #[Test]
    public function payload_is_stored_as_json_with_secrets_masked_at_any_depth(): void
    {
        $user = User::factory()->create();

        $this->dispatchRequest($this->request($user, 'POST', '/users', [
            'name' => 'Анна',
            'Password' => 'secret',
            'nested' => ['token' => 'abc', 'keep' => 1],
        ], 'users', 'users.store'));

        $this->assertSame(
            ['name' => 'Анна', 'Password' => '***', 'nested' => ['token' => '***', 'keep' => 1]],
            AclLog::query()->firstOrFail()->payload->payload,
        );
    }

    #[Test]
    public function uploaded_files_are_stored_as_their_real_path(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('doc.txt', 1);

        $this->dispatchRequest($this->request($user, 'POST', '/images', ['title' => 'T'], 'images', 'images.store', ['doc' => $file]));

        $stored = AclLog::query()->firstOrFail()->payload->payload;
        $this->assertSame('T', $stored['title']);
        $this->assertSame($file->getRealPath(), $stored['doc']);
    }

    #[Test]
    public function no_payload_row_when_there_is_no_input(): void
    {
        $this->dispatchRequest($this->request(User::factory()->create(), 'GET', '/users', [], 'users', 'users.index'));

        $this->assertDatabaseCount('acl_logs', 1);
        $this->assertDatabaseCount('acl_log_payloads', 0);
    }

    #[Test]
    public function oversized_payload_is_replaced_by_a_marker(): void
    {
        config(['acl_log.max_payload_bytes' => 50]);

        $this->dispatchRequest($this->request(User::factory()->create(), 'POST', '/users', ['text' => str_repeat('x', 200)], 'users', 'users.store'));

        $payload = AclLog::query()->firstOrFail()->payload->payload;
        $this->assertTrue($payload['_truncated']);
        $this->assertGreaterThan(200, $payload['bytes']);
    }

    #[Test]
    public function nothing_is_written_when_disabled_or_for_guests(): void
    {
        config(['acl_log.enabled' => false]);
        $this->dispatchRequest($this->request(User::factory()->create(), 'GET', '/users', [], 'users', 'users.index'));

        config(['acl_log.enabled' => true]);
        $guest = $this->request(null, 'GET', '/users', [], 'users', 'users.index');
        $this->dispatchRequest($guest);

        $this->assertDatabaseCount('acl_logs', 0);
    }

    #[Test]
    public function a_failing_write_never_breaks_the_request(): void
    {
        \Illuminate\Support\Facades\Schema::drop('acl_log_payloads');
        \Illuminate\Support\Facades\Schema::drop('acl_logs');

        $this->dispatchRequest($this->request(User::factory()->create(), 'GET', '/users', [], 'users', 'users.index'));

        $this->assertTrue(true, 'terminate() swallowed the error (reported, not thrown)');
    }

    private function dispatchRequest(Request $request, int $status = 200): void
    {
        $middleware = new LogAclAccess();
        $response = $middleware->handle($request, fn () => new Response('ok', $status));
        $middleware->terminate($request, $response);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, UploadedFile>  $files
     */
    private function request(?User $user, string $method, string $uri, array $input, string $pattern, ?string $name, array $files = []): Request
    {
        $request = Request::create($uri, $method, $input, [], $files);

        $route = new Route([$method], $pattern, fn () => null);
        if ($name !== null) {
            $route->name($name);
        }
        $route->bind($request);

        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
