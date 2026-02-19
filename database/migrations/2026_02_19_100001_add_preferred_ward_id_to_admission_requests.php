<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add preferred_ward_id column to admission_requests table.
     * Allows doctors to optionally indicate a ward preference when requesting admission.
     */
    public function up()
    {
        DB::statement('ALTER TABLE admission_requests ADD COLUMN preferred_ward_id BIGINT UNSIGNED NULL AFTER bed_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::statement('ALTER TABLE admission_requests DROP COLUMN preferred_ward_id');
    }
};
