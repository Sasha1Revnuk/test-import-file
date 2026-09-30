<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Authentication tables are not used. Sessions use the file driver.
     */
    public function up(): void
    {
        //
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
