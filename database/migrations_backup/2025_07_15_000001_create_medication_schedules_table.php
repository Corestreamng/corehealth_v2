<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('medication_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('product_or_service_request_id');
            $table->dateTime('scheduled_time');
            $table->string('dose')->nullable();
            $table->string('route')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('patient_id')->references('id')->on('patients');
            $table->foreign('product_or_service_request_id')->references('id')->on('product_or_service_requests');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down() {
        Schema::dropIfExists('medication_schedules');
    }
};
