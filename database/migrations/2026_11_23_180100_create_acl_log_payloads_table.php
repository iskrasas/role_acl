<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acl_log_payloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acl_log_id')->unique()->constrained('acl_logs')->cascadeOnDelete();
            $table->json('payload');
            // no timestamps: the age of a payload is the age of its acl_logs row
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_log_payloads');
    }
};
