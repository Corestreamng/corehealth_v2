<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Treatment Plan System Upgrade — Phase 1-3 combined migration.
 *
 * 1. Extend treatment_plans with patient scope, problem/goal/progress fields.
 * 2. Extend treatment_plan_items enum to include non_pharm, referral, admission, encounter_note.
 * 3. Add treatment_plan_id + treatment_plan_name to 8 order tables.
 */
class ExtendTreatmentPlanSystem extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // ═══ Phase 1: Extend treatment_plans table ═══
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('patient_id')->nullable()->after('created_by');
            $table->unsignedBigInteger('encounter_id')->nullable()->after('patient_id');
            $table->unsignedBigInteger('clinic_id')->nullable()->after('encounter_id');
            $table->string('problem_text', 500)->nullable()->after('description');
            $table->string('icd_code', 20)->nullable()->after('problem_text');
            $table->text('goal')->nullable()->after('icd_code');
            $table->tinyInteger('progress_percent')->default(0)->after('goal');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->after('progress_percent');

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('set null');
            $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('set null');
            $table->foreign('clinic_id')->references('id')->on('clinics')->onDelete('set null');
            $table->index('patient_id');
        });

        // ═══ Phase 2: Extend treatment_plan_items item_type enum ═══
        DB::statement("ALTER TABLE treatment_plan_items MODIFY COLUMN item_type ENUM('lab','imaging','medication','procedure','non_pharm','referral','admission','encounter_note') NOT NULL");

        // ═══ Phase 3: Add treatment_plan_id to 8 order tables ═══
        $orderTables = [
            'lab_service_requests',
            'imaging_service_requests',
            'product_requests',
            'procedures',
            'non_pharm_orders',
            'specialist_referrals',
            'admission_requests',
            'encounters',
        ];

        foreach ($orderTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->unsignedBigInteger('treatment_plan_id')->nullable()->after('id');
                $table->string('treatment_plan_name', 255)->nullable()->after('treatment_plan_id');

                $table->foreign('treatment_plan_id', "fk_{$tableName}_tp_id")
                      ->references('id')
                      ->on('treatment_plans')
                      ->onDelete('set null');

                $table->index('treatment_plan_id', "idx_{$tableName}_tp_id");
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop treatment_plan_id from 8 order tables
        $orderTables = [
            'lab_service_requests',
            'imaging_service_requests',
            'product_requests',
            'procedures',
            'non_pharm_orders',
            'specialist_referrals',
            'admission_requests',
            'encounters',
        ];

        foreach ($orderTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropForeign("fk_{$tableName}_tp_id");
                $table->dropIndex("idx_{$tableName}_tp_id");
                $table->dropColumn(['treatment_plan_id', 'treatment_plan_name']);
            });
        }

        // Revert treatment_plan_items enum
        DB::statement("ALTER TABLE treatment_plan_items MODIFY COLUMN item_type ENUM('lab','imaging','medication','procedure') NOT NULL");

        // Drop added columns from treatment_plans
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->dropForeign(['encounter_id']);
            $table->dropForeign(['clinic_id']);
            $table->dropIndex(['patient_id']);
            $table->dropColumn([
                'patient_id', 'encounter_id', 'clinic_id',
                'problem_text', 'icd_code', 'goal',
                'progress_percent', 'priority',
            ]);
        });
    }
}
