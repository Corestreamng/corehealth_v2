<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Changes hmo_no from unsignedBigInteger to varchar(100)
     * to support alphanumeric HMO membership numbers.
     */
    public function up()
    {
        DB::statement('ALTER TABLE patients MODIFY COLUMN hmo_no VARCHAR(100) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::statement('ALTER TABLE patients MODIFY COLUMN hmo_no BIGINT UNSIGNED NULL');
    }
};
