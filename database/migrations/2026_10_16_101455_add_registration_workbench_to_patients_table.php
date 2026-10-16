<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRegistrationWorkbenchToPatientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'registration_workbench')) {
                $table->string('registration_workbench', 50)->nullable()->index()->after('misc');
            }
        });

        // Smart retroactive backfill:
        // 1. Prefix-based workbench resolution (including ANC/PANC for antenatal and postnatal maternity)
        try {
            DB::statement("
                UPDATE patients 
                SET registration_workbench = 'maternity' 
                WHERE (file_no LIKE 'ANC%' OR file_no LIKE 'PANC%') 
                  AND (registration_workbench IS NULL OR registration_workbench = '')
            ");

            DB::statement("
                UPDATE patients 
                SET registration_workbench = 'pharmacy' 
                WHERE (file_no LIKE 'PHARM%' OR file_no LIKE 'PHM%') 
                  AND (registration_workbench IS NULL OR registration_workbench = '')
            ");

            DB::statement("
                UPDATE patients 
                SET registration_workbench = 'lab' 
                WHERE file_no LIKE 'LAB%' 
                  AND (registration_workbench IS NULL OR registration_workbench = '')
            ");

            DB::statement("
                UPDATE patients 
                SET registration_workbench = 'imaging' 
                WHERE (file_no LIKE 'RAD%' OR file_no LIKE 'IMG%') 
                  AND (registration_workbench IS NULL OR registration_workbench = '')
            ");

            DB::statement("
                UPDATE patients 
                SET registration_workbench = 'emergency' 
                WHERE (file_no LIKE 'EMG%' OR file_no LIKE 'EX-%' OR file_no LIKE 'EX%') 
                  AND (registration_workbench IS NULL OR registration_workbench = '')
            ");

            DB::statement("
                UPDATE patients 
                SET registration_workbench = 'nursing' 
                WHERE (file_no LIKE 'NUR%' OR file_no LIKE 'WRD%') 
                  AND (registration_workbench IS NULL OR registration_workbench = '')
            ");

            DB::statement("
                UPDATE patients 
                SET registration_workbench = 'billing' 
                WHERE file_no LIKE 'BIL%' 
                  AND (registration_workbench IS NULL OR registration_workbench = '')
            ");

            DB::statement("
                UPDATE patients 
                SET registration_workbench = 'hmo' 
                WHERE file_no LIKE 'HMO%' 
                  AND (registration_workbench IS NULL OR registration_workbench = '')
            ");

            DB::statement("
                UPDATE patients 
                SET registration_workbench = 'surgery' 
                WHERE (file_no LIKE 'SURG%' OR file_no LIKE 'TH-%' OR file_no LIKE 'TH%') 
                  AND (registration_workbench IS NULL OR registration_workbench = '')
            ");

            DB::statement("
                UPDATE patients 
                SET registration_workbench = 'morgue' 
                WHERE (file_no LIKE 'MOR%' OR file_no LIKE 'MORGUE%') 
                  AND (registration_workbench IS NULL OR registration_workbench = '')
            ");

            DB::statement("
                UPDATE patients 
                SET registration_workbench = 'doctor' 
                WHERE file_no LIKE 'DOC%' 
                  AND (registration_workbench IS NULL OR registration_workbench = '')
            ");
        } catch (\Throwable $e) {
            // Log or continue gracefully in environments with partial schemas
        }

        // 2. Departmental enrollment tables backfill (Maternity & Morgue direct admissions)
        // Strictly targets records where registration_workbench is NULL or empty, preserving existing values
        try {
            DB::statement("
                UPDATE patients p
                INNER JOIN maternity_enrollments me ON me.patient_id = p.id
                SET p.registration_workbench = 'maternity'
                WHERE p.registration_workbench IS NULL OR p.registration_workbench = ''
            ");

            DB::statement("
                UPDATE patients p
                INNER JOIN morgue_admissions ma ON ma.patient_id = p.id
                SET p.registration_workbench = 'morgue'
                WHERE p.registration_workbench IS NULL OR p.registration_workbench = ''
            ");
        } catch (\Throwable $e) {
            // Silently continue if clinical enrollment tables differ in test environments
        }

        // 3. Creator role-based backfill via OwenIt audit logs where available
        try {
            DB::statement("
                UPDATE patients p
                INNER JOIN audits a ON a.auditable_id = p.id
                    AND a.auditable_type LIKE '%Patient'
                    AND a.event = 'created'
                    AND a.user_id IS NOT NULL
                INNER JOIN model_has_roles mhr ON mhr.model_id = a.user_id
                INNER JOIN roles r ON r.id = mhr.role_id
                SET p.registration_workbench = CASE
                    WHEN LOWER(r.name) LIKE '%pharm%' THEN 'pharmacy'
                    WHEN LOWER(r.name) LIKE '%lab%' OR LOWER(r.name) LIKE '%scientist%' THEN 'lab'
                    WHEN LOWER(r.name) LIKE '%radiolog%' OR LOWER(r.name) LIKE '%scan%' OR LOWER(r.name) LIKE '%imaging%' THEN 'imaging'
                    WHEN LOWER(r.name) LIKE '%nurse%' OR LOWER(r.name) LIKE '%nursing%' THEN 'nursing'
                    WHEN LOWER(r.name) LIKE '%maternity%' OR LOWER(r.name) LIKE '%midwife%' THEN 'maternity'
                    WHEN LOWER(r.name) LIKE '%bill%' OR LOWER(r.name) LIKE '%account%' THEN 'billing'
                    WHEN LOWER(r.name) LIKE '%hmo%' THEN 'hmo'
                    WHEN LOWER(r.name) LIKE '%surgeon%' OR LOWER(r.name) LIKE '%theatre%' THEN 'surgery'
                    WHEN LOWER(r.name) LIKE '%morgue%' OR LOWER(r.name) LIKE '%mortuary%' THEN 'morgue'
                    WHEN LOWER(r.name) LIKE '%doctor%' OR LOWER(r.name) LIKE '%physician%' THEN 'doctor'
                    WHEN LOWER(r.name) LIKE '%reception%' THEN 'reception'
                    ELSE 'reception'
                END
                WHERE (p.registration_workbench IS NULL OR p.registration_workbench = '')
            ");
        } catch (\Throwable $e) {
            // Roles or audits table may not be present in specific testing contexts
        }

        // 3. Default all remaining records without a workbench to 'reception'
        try {
            DB::statement("
                UPDATE patients 
                SET registration_workbench = 'reception' 
                WHERE registration_workbench IS NULL OR registration_workbench = ''
            ");
        } catch (\Throwable $e) {
            // Continue gracefully
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'registration_workbench')) {
                $table->dropColumn('registration_workbench');
            }
        });
    }
}
