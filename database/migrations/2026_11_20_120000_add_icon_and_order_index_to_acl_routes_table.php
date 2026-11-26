<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existing sidebar items: pattern => [label, icon, order_index].
     * Copied onto rows that are already in acl_routes so the menu keeps working.
     */
    private const MENU = [
        'users.index' => ['Пользователи сайта', 'fa-users', 10],
        'user_logins.index' => ['Авторизации', 'fa-users', 20],
        'landing_regs.index' => ['Лэндинг', 'fa-user-plus', 110],
        'banners.index' => ['Баннеры', 'fa-cog', 120],
        'news.index' => ['Новости', 'fa-newspaper', 130],
        'actions.index' => ['Список акций', 'fa-chart-bar', 140],
        'aggregate_bonuses.index' => ['Бонусы акций', 'fa-chart-bar', 150],
        'erid.index' => ['ERID', 'fa-chart-bar', 160],
        'surveys.index' => ['Опросы', 'fa-chart-bar', 170],
        'staff.index' => ['Сотрудники', 'fa-chart-bar', 210],
        'acl.groups.index' => ['Группы и права', 'fa-user-shield', 220],
        'acl.routes.index' => ['Маршруты ACL', 'fa-route', 230],
    ];

    public function up(): void
    {
        Schema::table('acl_routes', function (Blueprint $table) {
            // Font Awesome class ("fa-users"); empty = the route is not shown in the sidebar
            $table->string('icon', 64)->nullable()->after('description');
            $table->unsignedInteger('order_index')->default(0)->after('icon');
        });

        foreach (self::MENU as $pattern => [$label, $icon, $order]) {
            DB::table('acl_routes')->where('pattern', $pattern)->update(['icon' => $icon, 'order_index' => $order]);

            DB::table('acl_routes')
                ->where('pattern', $pattern)
                ->where(fn ($query) => $query->whereNull('description')->orWhere('description', ''))
                ->update(['description' => $label]);
        }
    }

    public function down(): void
    {
        Schema::table('acl_routes', function (Blueprint $table) {
            $table->dropColumn(['icon', 'order_index']);
        });
    }
};
