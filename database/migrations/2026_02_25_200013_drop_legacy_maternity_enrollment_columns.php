<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No old records exist — drop legacy columns that were duplicated by
     * the alignment migration (200012). Keep only the workbench column names.
     */
    public function up()
    {
        Schema::table('maternity_enrollments', function (Blueprint $table) {
            $drops = [];

            // Legacy ➜ Replacement
            // enrollment_date ➜ booking_date  (keep enrollment_date too, set from controller)
            // para             ➜ parity
            // abortions        ➜ abortion_miscarriage
            // living_children  ➜ alive

            if (Schema::hasColumn('maternity_enrollments', 'para')) {
                $drops[] = 'para';
            }
            if (Schema::hasColumn('maternity_enrollments', 'abortions')) {
                $drops[] = 'abortions';
            }
            if (Schema::hasColumn('maternity_enrollments', 'living_children')) {
                $drops[] = 'living_children';
            }

            if (!empty($drops)) {
                $table->dropColumn($drops);
            }
        });

        // Make alive, abortion_miscarriage, parity nullable with default 0
        // Using raw SQL to avoid Doctrine DBAL version conflict
        DB::statement("ALTER TABLE maternity_enrollments MODIFY alive SMALLINT NULL DEFAULT 0");
        DB::statement("ALTER TABLE maternity_enrollments MODIFY abortion_miscarriage SMALLINT NULL DEFAULT 0");
        DB::statement("ALTER TABLE maternity_enrollments MODIFY parity SMALLINT NULL DEFAULT 0");
    }

    public function down()
    {
        Schema::table('maternity_enrollments', function (Blueprint $table) {
            $table->smallInteger('para')->nullable()->after('gravida');
            $table->smallInteger('abortions')->default(0)->after('para');
            $table->smallInteger('living_children')->default(0)->after('abortions');
        });
    }
};
