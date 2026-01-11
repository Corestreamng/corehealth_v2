<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create Nursing Shifts Table
 * 
 * Tracks nurse shifts for activity monitoring and handover management.
 * Shifts persist across login/logout and auto-end after 12 hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursing_shifts', function (Blueprint $table) {
            $table->id();
            
            // Nurse who owns this shift
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Optional ward assignment
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            
            // Shift classification
            $table->enum('shift_type', ['morning', 'afternoon', 'night'])->default('morning');
            
            // Timing
            $table->datetime('started_at');
            $table->datetime('ended_at')->nullable();
            $table->datetime('scheduled_end_at')->comment('Auto-calculated: started_at + 12 hours');
            
            // Status tracking
            $table->enum('status', ['active', 'completed', 'auto_ended', 'cancelled'])->default('active');
            
            // Handover flags
            $table->boolean('handover_created')->default(false);
            $table->text('concluding_notes')->nullable();
            $table->text('critical_notes')->nullable()->comment('Urgent items for incoming nurse');
            
            // Handover recipient
            $table->foreignId('incoming_nurse_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Action counts (denormalized for quick display)
            $table->unsignedInteger('vitals_count')->default(0);
            $table->unsignedInteger('medications_count')->default(0);
            $table->unsignedInteger('notes_count')->default(0);
            $table->unsignedInteger('injections_count')->default(0);
            $table->unsignedInteger('immunizations_count')->default(0);
            $table->unsignedInteger('bills_count')->default(0);
            $table->unsignedInteger('patients_seen')->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['status', 'scheduled_end_at']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursing_shifts');
    }
};
