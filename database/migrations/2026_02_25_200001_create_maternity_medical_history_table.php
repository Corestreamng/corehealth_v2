<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('maternity_medical_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('maternity_enrollments')->cascadeOnDelete();
            $table->enum('category', ['medical','surgical','obstetric','family','social']);
            $table->text('description');
            $table->year('year')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('maternity_medical_history');
    }
};
