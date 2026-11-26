<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isMysql = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);

        Schema::create('acl_groups', function (Blueprint $table) use ($isMysql) {
            $table->id();
            $column = $table->string('name');

            // ascii_bin exists on MariaDB/MySQL only (SQLite rejects it)
            if ($isMysql) {
                $column->charset('ascii')->collation('ascii_bin');
            }

            $table->unique('name');
            $table->string('description')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_groups');
    }
};
