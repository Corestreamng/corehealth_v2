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
            $columnInfo = DB::select("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'dob'");
            $type = $columnInfo ? $columnInfo[0]->DATA_TYPE : null;
            if ($type !== 'date') {
                // Clean up missing/empty first
                DB::statement("UPDATE patients SET dob = NULL WHERE dob = '' OR dob = '0000-00-00' OR dob = '0000-00-00 00:00:00'");

                // Fix DD/MM/YYYY format first (if day > 12)
                DB::statement("UPDATE patients SET dob = DATE_FORMAT(STR_TO_DATE(dob, '%d/%m/%Y'), '%Y-%m-%d') WHERE dob REGEXP '^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$' AND CAST(SUBSTRING_INDEX(dob, '/', 1) AS UNSIGNED) > 12");
                
                // Fix MM/DD/YYYY format using STR_TO_DATE (if it matches ##/##/####)
                DB::statement("UPDATE patients SET dob = DATE_FORMAT(STR_TO_DATE(dob, '%m/%d/%Y'), '%Y-%m-%d') WHERE dob REGEXP '^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$'");

                // Nullify anything that is still not in YYYY-MM-DD format
                DB::statement("UPDATE patients SET dob = NULL WHERE dob NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                DB::statement("ALTER TABLE patients MODIFY dob DATE NULL");
            }
        }

        // staff.date_of_birth: timestamp → date
        if (Schema::hasColumn('staff', 'date_of_birth')) {
            $columnInfo = DB::select("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff' AND COLUMN_NAME = 'date_of_birth'");
            $type = $columnInfo ? $columnInfo[0]->DATA_TYPE : null;
            if ($type !== 'date') {
                DB::statement("UPDATE staff SET date_of_birth = NULL WHERE date_of_birth = '0000-00-00 00:00:00' OR date_of_birth = ''");

                // Fix DD/MM/YYYY or MM/DD/YYYY format using STR_TO_DATE (if it matches ##/##/####)
                DB::statement("UPDATE staff SET date_of_birth = DATE_FORMAT(STR_TO_DATE(date_of_birth, '%d/%m/%Y'), '%Y-%m-%d') WHERE date_of_birth REGEXP '^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$' AND CAST(SUBSTRING_INDEX(date_of_birth, '/', 1) AS UNSIGNED) > 12");
                DB::statement("UPDATE staff SET date_of_birth = DATE_FORMAT(STR_TO_DATE(date_of_birth, '%m/%d/%Y'), '%Y-%m-%d') WHERE date_of_birth REGEXP '^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$'");

                // Nullify anything that is still not in YYYY-MM-DD format
                DB::statement("UPDATE staff SET date_of_birth = NULL WHERE date_of_birth NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}'");

                DB::statement("ALTER TABLE staff MODIFY date_of_birth DATE NULL");
            }
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
