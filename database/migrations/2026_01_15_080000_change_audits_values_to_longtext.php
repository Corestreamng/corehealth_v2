<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ChangeAuditsValuesToLongtext extends Migration
{
    /**
     * Run the migrations.
     *
     * Changes old_values and new_values columns from TEXT to LONGTEXT
     * to accommodate large audit data like shift handover summaries.
     *
     * @return void
     */
    public function up()
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $table = config('audit.drivers.database.table', 'audits');

        // Use raw SQL for MySQL to change column type
        // TEXT has ~65KB limit, LONGTEXT has ~4GB limit
        DB::connection($connection)->statement("ALTER TABLE `{$table}` MODIFY `old_values` LONGTEXT NULL");
        DB::connection($connection)->statement("ALTER TABLE `{$table}` MODIFY `new_values` LONGTEXT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $table = config('audit.drivers.database.table', 'audits');

        // Revert back to TEXT (note: this may truncate data if any records exceed TEXT limit)
        DB::connection($connection)->statement("ALTER TABLE `{$table}` MODIFY `old_values` TEXT NULL");
        DB::connection($connection)->statement("ALTER TABLE `{$table}` MODIFY `new_values` TEXT NULL");
    }
}
