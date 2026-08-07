<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTreatmentPlanSettingsToApplicationStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->boolean('enable_treatment_plans_in_consult')->default(true)->after('active');
            $table->boolean('require_treatment_plan_in_consult')->default(false)->after('enable_treatment_plans_in_consult');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->dropColumn(['enable_treatment_plans_in_consult', 'require_treatment_plan_in_consult']);
        });
    }
}
