<?php

namespace RoleAccess\Acl\Tests\Feature;

use RoleAccess\Acl\Tests\TestCase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;

class PublishedRoutesTest extends TestCase
{
    #[Test]
    public function the_package_does_not_register_routes_by_itself(): void
    {
        // the test case loads the stubs by hand; the provider must add nothing on top of them
        $names = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->getName() === 'acl.groups.index')
            ->count();

        $this->assertSame(1, $names);
    }

    #[Test]
    public function route_copies_are_published_for_editing(): void
    {
        $files = [base_path('routes/acl-web.php'), base_path('routes/acl-api.php')];

        try {
            $this->artisan('vendor:publish', ['--tag' => 'acl-routes', '--force' => true])->assertSuccessful();

            foreach ($files as $file) {
                $this->assertFileExists($file);
                $this->assertStringContainsString('roleAccess', File::get($file));
            }
        } finally {
            File::delete($files);
        }
    }
}
