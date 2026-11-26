<?php

use RoleAccess\Acl\Http\Controllers\Api\AclRouteController;
use RoleAccess\Acl\Http\Controllers\Api\GroupController;
use RoleAccess\Acl\Http\Controllers\Api\GroupPermissionController;
use RoleAccess\Acl\Http\Controllers\Api\StaffGroupController;
use Illuminate\Support\Facades\Route;

// Published by `php artisan vendor:publish --tag=acl-routes`; this copy is yours to edit.
// Load it from routes/api.php:  require __DIR__.'/acl-api.php';
//
// Change the prefix / middleware as you like (e.g. your own API auth middleware instead of 'auth'),
// but keep the route names ("api-acl.*") and keep "roleAccess" (access by route name).
// "logAclAccess" writes the access log; keep it BEFORE "roleAccess" so denied requests are logged too.

Route::prefix('api/v1/acl')->middleware(['auth', 'logAclAccess', 'roleAccess'])->group(function () {
    Route::name('api-acl.')->group(function () {
        Route::prefix('groups')->group(function () {
            Route::get('/', [GroupController::class, 'index'])->name('groups.index');
            Route::put('/', [GroupController::class, 'store'])->middleware('aclAdmin')->name('groups.store');
            Route::get('/{group}', [GroupController::class, 'show'])->name('groups.show');
            Route::put('/{group}', [GroupController::class, 'update'])->middleware('aclAdmin')->name('groups.update');
            Route::delete('/{group}', [GroupController::class, 'delete'])->middleware('aclAdmin')->name('groups.delete');
            Route::get('/{group}/permissions', [GroupPermissionController::class, 'show'])->name('groups.permissions');
            Route::put('/{group}/permissions', [GroupPermissionController::class, 'update'])->name('groups.permissions.update');
        });

        Route::prefix('routes')->group(function () {
            Route::get('/', [AclRouteController::class, 'index'])->name('routes.index');
            Route::put('/', [AclRouteController::class, 'store'])->name('routes.store');
            Route::get('/{aclRoute}', [AclRouteController::class, 'show'])->name('routes.show');
            Route::put('/{aclRoute}', [AclRouteController::class, 'update'])->name('routes.update');
            Route::delete('/{aclRoute}', [AclRouteController::class, 'delete'])->name('routes.delete');
        });

        Route::prefix('staff')->group(function () {
            Route::get('/', [StaffGroupController::class, 'index'])->name('staff.index');
            Route::put('/', [StaffGroupController::class, 'store'])->name('staff.store');
            Route::get('/{staff}/groups', [StaffGroupController::class, 'groups'])->name('staff.groups');
            Route::put('/{staff}/groups', [StaffGroupController::class, 'updateGroups'])->name('staff.groups.update');
        });
    });
});
