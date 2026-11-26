<?php

namespace RoleAccess\Acl;

use RoleAccess\Acl\Console\PruneAclLogs;
use RoleAccess\Acl\Http\Middleware\AdminOnly;
use RoleAccess\Acl\Http\Middleware\LogAclAccess;
use RoleAccess\Acl\Http\Middleware\RoleAccess;
use RoleAccess\Acl\Services\AclMenu;
use RoleAccess\Acl\Services\AclResolver;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AclServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/acl.php', 'acl');
        $this->mergeConfigFrom(__DIR__.'/../config/acl_log.php', 'acl_log');

        $this->app->singleton(AclResolver::class, fn ($app): AclResolver => new AclResolver($this->cache()));
        $this->app->singleton(AclMenu::class, fn ($app): AclMenu => new AclMenu($app->make(AclResolver::class), $this->cache()));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'acl');

        $this->publishes([
            __DIR__.'/../config/acl.php' => config_path('acl.php'),
            __DIR__.'/../config/acl_log.php' => config_path('acl_log.php'),
        ], 'acl-config');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/acl')], 'acl-views');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'acl-migrations');
        $this->publishes([
            __DIR__.'/../stubs/acl-web.php' => base_path('routes/acl-web.php'),
            __DIR__.'/../stubs/acl-api.php' => base_path('routes/acl-api.php'),
        ], 'acl-routes');

        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('roleAccess', RoleAccess::class);
        $router->aliasMiddleware('aclAdmin', AdminOnly::class);
        $router->aliasMiddleware('logAclAccess', LogAclAccess::class);

        $this->registerLogPruning();

        $this->registerMenu();
    }

    private function registerLogPruning(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PruneAclLogs::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $time = config('acl_log.prune_at');

            if ($time) {
                $schedule->command('acl:prune-logs')->dailyAt($time)->withoutOverlapping();
            }
        });
    }

    private function cache(): Cache
    {
        return $this->app['cache']->store(config('acl.cache_store'));
    }

    private function registerMenu(): void
    {
        if (! config('acl.menu.enabled')) {
            return;
        }

        View::composer((array) config('acl.menu.views'), function (ViewContract $view): void {
            $view->with(config('acl.menu.variable'), $this->app->make(AclMenu::class)->itemsFor(auth()->user()));
        });
    }
}
