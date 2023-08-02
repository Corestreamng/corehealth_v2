<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVitalSignsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vital_signs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->nullable()->constrained('users');
            $table->foreignId('taken_by')->nullable()->constrained('users');
            $table->foreignId('patient_id')->nullable()->constrained('patients');
            $table->string('blood_pressure')->nullable();
            $table->string('temp')->nullable();
            $table->string('heart_rate')->nullable();
            $table->string('resp_rate')->nullable();
            $table->string('other_notes')->nullable();
            $table->dateTime('time_taken')->nullable();
            $table->integer('status')->default(1);
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
        Schema::dropIfExists('vital_signs');
    }
}
