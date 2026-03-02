<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('postnatal_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('maternity_enrollments')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();

            $table->enum('visit_type', ['within_24h','day_3','week_1_2','week_6','other'])->default('within_24h');
            $table->date('visit_date');
            $table->integer('days_postpartum')->nullable();

            // Mother assessment
            $table->string('general_condition')->nullable();
            $table->string('blood_pressure')->nullable();
            $table->decimal('temperature_c', 4, 1)->nullable();
            $table->string('uterus_assessment')->nullable();
            $table->enum('lochia', ['normal','offensive','heavy','absent'])->nullable();
            $table->string('wound_assessment')->nullable();
            $table->string('breast_assessment')->nullable();
            $table->text('breastfeeding_support')->nullable();
            $table->enum('emotional_wellbeing', ['good','mild_concern','moderate_concern','severe_concern'])->nullable();
            $table->text('emotional_notes')->nullable();

            // Baby assessment
            $table->decimal('baby_weight_kg', 4, 3)->nullable();
            $table->enum('baby_feeding', ['exclusive_breastfeeding','formula','mixed'])->nullable();
            $table->enum('cord_status', ['clean','infected','separated'])->nullable();
            $table->boolean('jaundice')->default(false);
            $table->string('baby_general_condition')->nullable();
            $table->text('baby_notes')->nullable();

            // Family planning
            $table->boolean('family_planning_counselled')->default(false);
            $table->string('family_planning_method')->nullable();

            $table->text('clinical_notes')->nullable();
            $table->date('next_appointment')->nullable();

            $table->unsignedBigInteger('seen_by')->nullable();
            $table->foreign('seen_by')->references('id')->on('users');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['enrollment_id', 'visit_type']);
            $table->index('patient_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('postnatal_visits');
    }
};
