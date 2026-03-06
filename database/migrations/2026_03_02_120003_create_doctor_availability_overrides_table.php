<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_availability_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->onDelete('set null');
            $table->date('override_date');
            $table->time('start_time')->nullable()->comment('Null = unavailable entire day');
            $table->time('end_time')->nullable();
            $table->boolean('is_available')->default(false)->comment('false = blocked, true = extra slot');
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'override_date'], 'staff_override_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_availability_overrides');
    }
};
