<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiagnosisFieldsToTreatmentPlans extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->text('diagnosis_data')->nullable()->after('description');
            $table->string('diagnosis_status', 50)->nullable()->after('diagnosis_data');
            $table->string('diagnosis_course', 50)->nullable()->after('diagnosis_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropColumn(['diagnosis_data', 'diagnosis_status', 'diagnosis_course']);
        });
    }
}
