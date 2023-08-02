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
            $table->foreignId('service_request_id')->nullable()->constrained('product_or_service_requests');
            $table->foreignId('billed_by')->nullable()->constrained('users');
            $table->timestamp('billed_date')->nullable();
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('encounter_id')->nullable()->constrained('encounters');
            $table->foreignId('patient_id')->constrained('patients');
            $table->longText('result')->nullable();
            $table->timestamp('result_date')->nullable();
            $table->foreignId('result_by')->nullable()->constrained('users');
            $table->boolean('sample_taken')->default(0);
            $table->timestamp('sample_date')->nullable();
            $table->foreignId('sample_taken_by')->nullable()->constrained('users');
            $table->foreignId('doctor_id')->nullable()->constrained('users');
            $table->text('note')->nullable();
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
