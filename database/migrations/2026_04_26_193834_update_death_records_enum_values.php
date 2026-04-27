<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateDeathRecordsEnumValues extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Using raw SQL to avoid doctrine/dbal version incompatibility issues in Laravel 8
        DB::statement("ALTER TABLE death_records MODIFY COLUMN death_type VARCHAR(255) NOT NULL DEFAULT 'RIP'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE death_records MODIFY COLUMN death_type ENUM('RIP', 'BID') NOT NULL DEFAULT 'RIP'");
    }
}
