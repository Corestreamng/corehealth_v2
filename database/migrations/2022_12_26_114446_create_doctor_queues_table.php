<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDoctorQueuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('doctor_queues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('clinic_id');
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->unsignedBigInteger('receptionist_id');
            $table->unsignedBigInteger('request_entry_id');
            $table->integer('status')->default(1);//1 for new queue entry, 2 for conttinuing patients entry, 0 for inactive or deleted entries
            $table->foreign('patient_id')->references('id')->on('patients');
            $table->foreign('clinic_id')->references('id')->on('clinics');
            $table->foreign('staff_id')->references('id')->on('staff');
            $table->foreign('receptionist_id')->references('id')->on('staff');
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
        Schema::dropIfExists('doctor_queues');
    }
}
