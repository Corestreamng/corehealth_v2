<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->boolean('doctor_can_enter_lab_result')->default(false)->after('imaging_results_require_approval');
            $table->boolean('nurse_can_enter_lab_result')->default(false)->after('doctor_can_enter_lab_result');
            $table->boolean('doctor_can_enter_imaging_result')->default(false)->after('nurse_can_enter_lab_result');
            $table->boolean('nurse_can_enter_imaging_result')->default(false)->after('doctor_can_enter_imaging_result');
        });
    }

    public function down(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->dropColumn([
                'doctor_can_enter_lab_result',
                'nurse_can_enter_lab_result',
                'doctor_can_enter_imaging_result',
                'nurse_can_enter_imaging_result',
            ]);
        });
    }
};
