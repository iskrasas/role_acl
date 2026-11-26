<?php

namespace RoleAccess\Acl\Tests;

use RoleAccess\Acl\AclServiceProvider;
use RoleAccess\Acl\Tests\Fixtures\User;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        View::addNamespace('acl-test', __DIR__.'/views');
    }

    protected function getPackageProviders($app): array
    {
        return [AclServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('acl.user_model', User::class);
        $app['config']->set('acl.layout', 'acl-test::layout');
        $app['config']->set('acl.menu.views', ['acl-test::layout']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * Routes the host application would have (names the ACL tests refer to).
     */
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        // the published route files, as a host would load them (the stubs bring their own prefix)
        $router->middleware('web')->group(function (): void {
            require __DIR__.'/../stubs/acl-web.php';
            require __DIR__.'/../stubs/acl-api.php';
        });

        $router->middleware('web')->group(function () use ($router): void {
            $router->get('/', fn () => view('acl-test::page'))->name('welcome');
            $router->get('/users', fn () => 'users')->name('users.index');
            $router->get('/users/create', fn () => 'create')->name('users.create');
            $router->get('/users/{user}/edit', fn () => 'edit')->name('users.edit');
            $router->get('/banners', fn () => 'banners')->name('banners.index');
            $router->get('/news', fn () => 'news')->name('news.index');
            $router->get('/staff', fn () => 'staff')->name('staff.index');
            $router->get('/surveys/{survey}/questions', fn () => 'questions')->name('survey_questions.index');
        });
    }
}
