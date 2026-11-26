<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acl_group_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('acl_groups')->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('acl_routes')->cascadeOnDelete();
            $table->boolean('allowed');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['group_id', 'route_id']);
            $table->index('route_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_group_permissions');
    }
};
