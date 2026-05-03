<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFormDataToVitalsAndClinics extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->json('form_data')->nullable()->after('other_notes');
        });

        Schema::table('clinics', function (Blueprint $table) {
            $table->json('vitals_template')->nullable();
        });
    }

    public function down()
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->dropColumn('form_data');
        });

        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn('vitals_template');
        });
    }
}
