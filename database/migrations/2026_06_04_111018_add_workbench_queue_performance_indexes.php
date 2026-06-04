<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWorkbenchQueuePerformanceIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->index('created_at', 'posr_created_at_idx');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->index('created_at', 'patients_created_at_idx');
        });

        Schema::table('morgue_admissions', function (Blueprint $table) {
            $table->index('arrival_time', 'morgue_arrival_time_idx');
            $table->index('release_time', 'morgue_release_time_idx');
        });

        Schema::table('child_growth_records', function (Blueprint $table) {
            $table->index('created_at', 'cgr_created_at_idx');
        });

        Schema::table('patient_immunization_schedules', function (Blueprint $table) {
            $table->index('due_date', 'pis_due_date_idx');
        });

        Schema::table('admission_requests', function (Blueprint $table) {
            $table->index('created_at', 'ar_created_at_idx');
        });

        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->index('created_at', 'lsr_created_at_idx');
        });

        Schema::table('imaging_service_requests', function (Blueprint $table) {
            $table->index('created_at', 'isr_created_at_idx');
        });

        Schema::table('doctor_queues', function (Blueprint $table) {
            $table->index('created_at', 'dq_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->dropIndex('posr_created_at_idx');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_created_at_idx');
        });

        Schema::table('morgue_admissions', function (Blueprint $table) {
            $table->dropIndex('morgue_arrival_time_idx');
            $table->dropIndex('morgue_release_time_idx');
        });

        Schema::table('child_growth_records', function (Blueprint $table) {
            $table->dropIndex('cgr_created_at_idx');
        });

        Schema::table('patient_immunization_schedules', function (Blueprint $table) {
            $table->dropIndex('pis_due_date_idx');
        });

        Schema::table('admission_requests', function (Blueprint $table) {
            $table->dropIndex('ar_created_at_idx');
        });

        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->dropIndex('lsr_created_at_idx');
        });

        Schema::table('imaging_service_requests', function (Blueprint $table) {
            $table->dropIndex('isr_created_at_idx');
        });

        Schema::table('doctor_queues', function (Blueprint $table) {
            $table->dropIndex('dq_created_at_idx');
        });
    }
}
