<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePatientProfilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('patient_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('form_id');
            $table->string('form_name')->nullable();
            $table->json('form_data')->nullable();
            $table->foreignId('filled_by')->nullable()->constrained('users');
            $table->foreignId('patient_id')->nullable()->constrained('patients');
            $table->foreignId('encounter_id')->nullable()->constrained('encounters');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('patient_profiles');
    }
}
