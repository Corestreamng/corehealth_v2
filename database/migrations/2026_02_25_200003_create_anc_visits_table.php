<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('anc_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('maternity_enrollments')->cascadeOnDelete();
            $table->smallInteger('visit_number');
            $table->enum('visit_type', ['booking','routine','emergency','specialist_referral'])->default('routine');
            $table->date('visit_date');
            $table->smallInteger('gestational_age_weeks')->nullable();
            $table->smallInteger('gestational_age_days')->nullable();

            // Examination findings (matching ANC card columns)
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->string('blood_pressure')->nullable();
            $table->string('height_of_fundus')->nullable();
            $table->string('presentation_and_position')->nullable();
            $table->smallInteger('foetal_heart_rate')->nullable();
            $table->enum('foetal_movement', ['present','absent','reduced'])->nullable();
            $table->enum('oedema', ['none','mild','moderate','severe'])->nullable();
            $table->enum('urine_protein', ['nil','trace','+','++','+++'])->nullable();
            $table->enum('urine_glucose', ['nil','trace','+','++','+++'])->nullable();
            $table->decimal('haemoglobin', 4, 1)->nullable();

            // Clinical
            $table->text('complaints')->nullable();
            $table->text('examination_notes')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('treatment')->nullable();
            $table->text('plan')->nullable();
            $table->date('next_appointment')->nullable();

            // Staff
            $table->foreignId('seen_by')->constrained('users');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['enrollment_id', 'visit_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('anc_visits');
    }
};
