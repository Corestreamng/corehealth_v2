<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_queues', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable()->after('request_entry_id')
                  ->comment('Links back to doctor_appointments if this queue entry came from an appointment');
            $table->timestamp('consultation_started_at')->nullable()->after('triage_note');
            $table->timestamp('consultation_ended_at')->nullable()->after('consultation_started_at');
            $table->unsignedInteger('consultation_paused_seconds')->default(0)
                  ->after('consultation_ended_at')
                  ->comment('Accumulated pause time in seconds');
            $table->timestamp('last_paused_at')->nullable()->after('consultation_paused_seconds');
            $table->timestamp('last_resumed_at')->nullable()->after('last_paused_at');
            $table->boolean('is_paused')->default(false)->after('last_resumed_at');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_queues', function (Blueprint $table) {
            $table->dropColumn([
                'appointment_id',
                'consultation_started_at',
                'consultation_ended_at',
                'consultation_paused_seconds',
                'last_paused_at',
                'last_resumed_at',
                'is_paused',
            ]);
        });
    }
};
