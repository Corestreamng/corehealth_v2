<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDoctorFullAdmissionSettingsToApplicationStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->boolean('doctor_full_admission')->default(false)->after('thermal_printer_width');
            $table->boolean('doctor_full_discharge')->default(false)->after('doctor_full_admission');
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
            $table->dropColumn('doctor_full_admission');
            $table->dropColumn('doctor_full_discharge');
        });
    }
}
