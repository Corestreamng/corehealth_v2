<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('maternity_babies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('maternity_enrollments')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');
            $table->smallInteger('birth_order')->default(1);
            $table->enum('sex', ['male','female','ambiguous']);

            // Birth measurements
            $table->decimal('birth_weight_kg', 4, 3)->nullable();
            $table->decimal('length_cm', 5, 1)->nullable();
            $table->decimal('head_circumference_cm', 5, 1)->nullable();
            $table->decimal('chest_circumference_cm', 5, 1)->nullable();

            // APGAR
            $table->smallInteger('apgar_1_min')->nullable();
            $table->smallInteger('apgar_5_min')->nullable();
            $table->smallInteger('apgar_10_min')->nullable();

            // Resuscitation
            $table->boolean('resuscitation')->default(false);
            $table->text('resuscitation_details')->nullable();
            $table->text('birth_defects')->nullable();

            // Feeding
            $table->enum('feeding_method', ['exclusive_breastfeeding','formula','mixed'])->default('exclusive_breastfeeding');

            // Immediate newborn care
            $table->boolean('bcg_given')->default(false);
            $table->boolean('opv0_given')->default(false);
            $table->boolean('hbv0_given')->default(false);
            $table->boolean('vitamin_k_given')->default(false);
            $table->boolean('eye_prophylaxis')->default(false);

            $table->date('date_first_seen')->nullable();
            $table->text('reasons_for_special_care')->nullable();
            $table->enum('status', ['alive','deceased','nicu','discharged'])->default('alive');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('enrollment_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('maternity_babies');
    }
};
