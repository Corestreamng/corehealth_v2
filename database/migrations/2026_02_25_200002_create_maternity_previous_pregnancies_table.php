<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('maternity_previous_pregnancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('maternity_enrollments')->cascadeOnDelete();
            $table->year('year')->nullable();
            $table->string('duration_of_pregnancy')->nullable();
            $table->text('ante_natal_complications')->nullable();
            $table->text('labour_notes')->nullable();
            $table->enum('baby_alive_or_dead', ['alive','dead','stillbirth'])->nullable();
            $table->enum('sex', ['male','female'])->nullable();
            $table->decimal('birth_weight_kg', 4, 2)->nullable();
            $table->string('age_at_death')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('maternity_previous_pregnancies');
    }
};
