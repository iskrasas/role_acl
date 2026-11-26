<?php

use RoleAccess\Acl\Http\Controllers\Web\AclRouteController;
use RoleAccess\Acl\Http\Controllers\Web\GroupController;
use RoleAccess\Acl\Http\Controllers\Web\GroupPermissionController;
use RoleAccess\Acl\Http\Controllers\Web\LogController;
use RoleAccess\Acl\Http\Controllers\Web\StaffGroupController;
use Illuminate\Support\Facades\Route;

// Published by `php artisan vendor:publish --tag=acl-routes`; this copy is yours to edit.
// Load it from routes/web.php:  require __DIR__.'/acl-web.php';
//
// Change the prefix / middleware / domain as you like, but keep the route names ("acl.*") and keep
// "roleAccess" (access by route name). "logAclAccess" writes the access log; it must stay BEFORE
// "roleAccess" so that denied (403) requests are logged too. Add 'web' to the middleware if this file is loaded outside
// the application's web routes.

Route::prefix('acl')->middleware(['auth', 'logAclAccess', 'roleAccess'])->group(function () {
    Route::name('acl.')->group(function () {
        Route::get('groups', [GroupPermissionController::class, 'index'])->name('groups.index');
        Route::get('groups/{group}/permissions', [GroupPermissionController::class, 'edit'])->name('groups.permissions.edit');
        Route::put('groups/{group}/permissions', [GroupPermissionController::class, 'update'])->name('groups.permissions.update');
        Route::resource('routes', AclRouteController::class)->parameters(['routes' => 'aclRoute'])->except('show');
        Route::get('logs', [LogController::class, 'index'])->name('logs.index');
        Route::get('logs/{aclLog}', [LogController::class, 'show'])->name('logs.show');
    });

    // create / rename / delete access groups: admins only
    Route::prefix('groups')->name('acl.groups.')->middleware('aclAdmin')->group(function () {
        Route::get('create', [GroupController::class, 'create'])->name('create');
        Route::post('/', [GroupController::class, 'store'])->name('store');
        Route::get('{group}/edit', [GroupController::class, 'edit'])->name('edit');
        Route::put('{group}', [GroupController::class, 'update'])->name('update');
        Route::delete('{group}', [GroupController::class, 'destroy'])->name('destroy');
    });

    // membership of staff in access groups
    Route::prefix('staff')->name('acl.staff.')->group(function () {
        Route::get('/', [StaffGroupController::class, 'index'])->name('index');
        Route::post('/', [StaffGroupController::class, 'store'])->name('store');
        Route::get('{staff}/groups', [StaffGroupController::class, 'edit'])->name('edit');
        Route::put('{staff}/groups', [StaffGroupController::class, 'update'])->name('update');
    });
});
