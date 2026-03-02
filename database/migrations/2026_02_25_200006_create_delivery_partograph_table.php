<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('delivery_partograph', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_record_id')->constrained('delivery_records')->cascadeOnDelete();
            $table->dateTime('recorded_at');
            $table->decimal('cervical_dilation_cm', 3, 1)->nullable();
            $table->string('descent_of_head')->nullable();
            $table->smallInteger('contractions_per_10_min')->nullable();
            $table->smallInteger('contraction_duration_sec')->nullable();
            $table->smallInteger('foetal_heart_rate')->nullable();
            $table->enum('amniotic_fluid', ['intact','clear','meconium_stained','bloody','absent'])->nullable();
            $table->enum('moulding', ['none','+','++','+++'])->nullable();
            $table->string('maternal_bp')->nullable();
            $table->smallInteger('maternal_pulse')->nullable();
            $table->decimal('maternal_temp', 4, 1)->nullable();
            $table->integer('urine_output_ml')->nullable();
            $table->enum('urine_protein', ['nil','trace','+','++','+++'])->nullable();
            $table->string('oxytocin_dose')->nullable();
            $table->string('iv_fluids')->nullable();
            $table->text('medications')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('delivery_partograph');
    }
};
