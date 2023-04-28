<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLabServiceRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lab_service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained('product_or_service_requests');
            $table->foreignId('service_id')->constrained('services');
            $table->unsignedBigInteger('encounter_id')->nullable();
            $table->foreignId('patient_id')->constrained('patients');
            $table->longText('result')->nullable();
            $table->timestamp('result_date')->nullable();
            $table->foreignId('result_by')->constrained('users');
            $table->boolean('sample_taken')->default(0);
            $table->timestamp('sample_date')->nullable();
            $table->foreignId('sample_taken_by')->constrained('users');
            $table->boolean('status')->default(1);
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
        Schema::dropIfExists('lab_service_requests');
    }
}
