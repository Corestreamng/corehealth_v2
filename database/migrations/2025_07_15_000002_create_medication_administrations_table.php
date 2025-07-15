<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('product_or_service_request_id');
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->datetime('administered_at');
            $table->string('dose');
            $table->string('route');
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('administered_by');
            $table->unsignedBigInteger('edited_by')->nullable();
            $table->datetime('edited_at')->nullable();
            $table->text('edit_reason')->nullable();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('delete_reason')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients');
            $table->foreign('product_or_service_request_id')->references('id')->on('product_or_service_requests');
            $table->foreign('schedule_id')->references('id')->on('medication_schedules');
            $table->foreign('administered_by')->references('id')->on('users');
            $table->foreign('edited_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
        });
    }

    public function down() {
        Schema::dropIfExists('medication_administrations');
    }
};
