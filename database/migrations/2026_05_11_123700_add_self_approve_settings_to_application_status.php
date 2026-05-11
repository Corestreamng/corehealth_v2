<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSelfApproveSettingsToApplicationStatus extends Migration
{
    /**
     * Run the migrations.
     * Adds 4 self-approve boolean columns for the "Perform Investigation" feature.
     * These allow requesting clinicians to bypass the approval queue for their own results.
     */
    public function up()
    {
        Schema::table('application_status', function (Blueprint $table) {
            if (!Schema::hasColumn('application_status', 'doctor_self_approve_lab_result')) {
                $table->boolean('doctor_self_approve_lab_result')->default(false)->after('imaging_results_require_approval');
            }
            if (!Schema::hasColumn('application_status', 'nurse_self_approve_lab_result')) {
                $table->boolean('nurse_self_approve_lab_result')->default(false)->after('doctor_self_approve_lab_result');
            }
            if (!Schema::hasColumn('application_status', 'doctor_self_approve_imaging_result')) {
                $table->boolean('doctor_self_approve_imaging_result')->default(false)->after('nurse_self_approve_lab_result');
            }
            if (!Schema::hasColumn('application_status', 'nurse_self_approve_imaging_result')) {
                $table->boolean('nurse_self_approve_imaging_result')->default(false)->after('doctor_self_approve_imaging_result');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('application_status', function (Blueprint $table) {
            $columns = [
                'doctor_self_approve_lab_result',
                'nurse_self_approve_lab_result',
                'doctor_self_approve_imaging_result',
                'nurse_self_approve_imaging_result',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('application_status', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
