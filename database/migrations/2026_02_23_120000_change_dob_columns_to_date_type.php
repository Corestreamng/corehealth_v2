<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Change dob/date_of_birth columns to DATE type on patients and staff tables.
     */
    public function up()
    {
        // patients.dob: varchar(255) → date
        if (Schema::hasColumn('patients', 'dob')) {
            // First clean up any invalid values so the cast doesn't fail
            DB::statement("UPDATE patients SET dob = NULL WHERE dob = '' OR dob = '0000-00-00' OR dob = '0000-00-00 00:00:00'");
            DB::statement("ALTER TABLE patients MODIFY dob DATE NULL");
        }

        // staff.date_of_birth: timestamp → date
        if (Schema::hasColumn('staff', 'date_of_birth')) {
            DB::statement("UPDATE staff SET date_of_birth = NULL WHERE date_of_birth = '0000-00-00 00:00:00'");
            DB::statement("ALTER TABLE staff MODIFY date_of_birth DATE NULL");
        }
    }

    /**
     * Reverse the migration.
     */
    public function down()
    {
        if (Schema::hasColumn('patients', 'dob')) {
            DB::statement("ALTER TABLE patients MODIFY dob VARCHAR(255) NULL");
        }

        if (Schema::hasColumn('staff', 'date_of_birth')) {
            DB::statement("ALTER TABLE staff MODIFY date_of_birth TIMESTAMP NULL");
        }
    }
};
