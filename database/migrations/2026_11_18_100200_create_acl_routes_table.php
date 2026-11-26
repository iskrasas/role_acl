<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A row is a route name or a prefix pattern, e.g. "admin.users.index",
     * "admin.users.*" or "*". Edit `name` directly to follow a route rename;
     * permissions reference the row by id, so they stay attached.
     */
    public function up(): void
    {
        $isMysql = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);

        Schema::create('acl_routes', function (Blueprint $table) use ($isMysql) {
            $table->id();
            $column = $table->string('pattern');

            // ascii_bin exists on MariaDB/MySQL only (SQLite rejects it)
            if ($isMysql) {
                $column->charset('ascii')->collation('ascii_bin');
            }

            $table->unique('pattern');
            $table->string('description')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_routes');
    }
};
