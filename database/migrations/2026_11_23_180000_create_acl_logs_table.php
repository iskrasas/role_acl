<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acl_logs', function (Blueprint $table) {
            $table->id();
            // acl_routes row whose pattern equals the route name (null: unnamed route or no such row)
            $table->foreignId('route_id')->nullable()->constrained('acl_routes')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('acl_staff')->nullOnDelete();
            $table->string('method', 8);
            // request path as it was called, without the query string: /actions/1/edit
            $table->string('uri', 1024);
            // value of the last route parameter ({action} in actions/{action}/edit)
            $table->string('object_id', 191)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['route_id', 'object_id']);    // history of one object
            $table->index(['staff_id', 'created_at']);   // what did this person do
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_logs');
    }
};
