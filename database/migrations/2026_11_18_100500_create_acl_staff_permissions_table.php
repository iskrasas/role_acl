<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acl_staff_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('acl_staff')->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('acl_routes')->cascadeOnDelete();
            $table->boolean('allowed');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['staff_id', 'route_id']);
            $table->index('route_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_staff_permissions');
    }
};
