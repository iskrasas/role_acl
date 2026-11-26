<?php

return [
    /*
     * The host application's user model (acl_staff.user_id holds its key; there is no database foreign key).
     */
    'user_model' => 'App\\Models\\User',

    /*
     * Members of this acl_groups.name may put any user into any group and manage groups.
     * Everyone else manages memberships only of the groups they belong to.
     */
    'admin_group' => env('ACL_ADMIN_GROUP', 'admin'),

    /*
     * Cache of the resolved rules / menu per user. null = the default cache store.
     */
    'cache_store' => null,
    'cache_ttl' => 3600,

    /*
     * Layout the package views extend. It must provide the sections "title", "breadcrumbs" and
     * "content" and load Bootstrap 5 and Font Awesome.
     */
    'layout' => 'layouts.app',

    /*
     * Routes are not registered by the package: publish editable copies
     * (php artisan vendor:publish --tag=acl-routes) and load them from your route files.
     * Route names are fixed ("acl.*" for the screens, "api-acl.*" for the JSON API): ACL rules refer to them.
     */

    /*
     * Sidebar: the listed views receive the items (allowed "*.index" routes that have an icon
     * in acl_routes) in the given variable.
     */
    'menu' => [
        'enabled' => true,
        'views' => ['layouts.app'],
        'variable' => 'asideItems',
    ],
];
