<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('child_growth_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baby_id')->constrained('maternity_babies')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');
            $table->date('record_date');
            $table->decimal('age_months', 5, 1)->nullable();

            // Anthropometrics
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->decimal('length_height_cm', 5, 1)->nullable();
            $table->decimal('head_circumference_cm', 5, 1)->nullable();
            $table->decimal('muac_cm', 5, 1)->nullable();

            // WHO z-scores
            $table->decimal('weight_for_age_z', 5, 2)->nullable();
            $table->decimal('length_for_age_z', 5, 2)->nullable();
            $table->decimal('weight_for_length_z', 5, 2)->nullable();
            $table->decimal('bmi_for_age_z', 5, 2)->nullable();

            $table->enum('nutritional_status', ['normal','mild_underweight','moderate_underweight','severe_underweight','overweight','obese'])->default('normal');
            $table->json('milestones')->nullable();

            $table->enum('feeding_method', ['exclusive_breastfeeding','complementary','formula','mixed','family_food'])->nullable();
            $table->text('dietary_notes')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->foreign('recorded_by')->references('id')->on('users');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['baby_id', 'record_date']);
            $table->index('patient_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('child_growth_records');
    }
};
