<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdmissionRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admission_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->nullable()->constrained('product_or_service_requests');
            $table->foreignId('billed_by')->nullable()->constrained('users');
            $table->timestamp('billed_date')->nullable();
            $table->foreignId('service_id')->nullable()->constrained('services');
            $table->foreignId('encounter_id')->nullable()->constrained('encounters');
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('bed_id')->nullable()->constrained('beds');
            $table->timestamp('bed_assign_date')->nullable();
            $table->foreignId('bed_assigned_by')->nullable()->constrained('users');
            $table->boolean('discharged')->default(0);
            $table->timestamp('discharge_date')->nullable();
            $table->foreignId('discharged_by')->nullable()->constrained('users');
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
        Schema::dropIfExists('admission_requests');
    }
}
