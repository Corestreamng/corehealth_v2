<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->onDelete('cascade');
            $table->unsignedTinyInteger('day_of_week')->comment('0=Sun, 1=Mon … 6=Sat');
            $table->time('open_time');
            $table->time('close_time');
            $table->unsignedSmallInteger('slot_duration_minutes')->default(15);
            $table->unsignedSmallInteger('max_concurrent_slots')->default(1)
                  ->comment('How many appointments per time slot');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['clinic_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_schedules');
    }
};
