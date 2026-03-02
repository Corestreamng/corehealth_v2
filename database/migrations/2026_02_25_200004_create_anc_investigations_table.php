<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('anc_investigations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('maternity_enrollments')->cascadeOnDelete();
            $table->unsignedBigInteger('anc_visit_id')->nullable();
            $table->enum('investigation_type', ['lab','imaging','procedure']);
            $table->unsignedBigInteger('lab_service_request_id')->nullable();
            $table->unsignedBigInteger('imaging_service_request_id')->nullable();
            $table->string('investigation_name');
            $table->text('result_summary')->nullable();
            $table->smallInteger('gestational_age_weeks')->nullable();
            $table->boolean('is_routine')->default(false);
            $table->timestamps();

            $table->foreign('anc_visit_id')->references('id')->on('anc_visits')->nullOnDelete();
            $table->foreign('lab_service_request_id')->references('id')->on('lab_service_requests')->nullOnDelete();
            $table->foreign('imaging_service_request_id')->references('id')->on('imaging_service_requests')->nullOnDelete();
            $table->index('enrollment_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('anc_investigations');
    }
};
