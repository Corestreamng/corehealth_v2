<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create Shift Handovers Table
 * 
 * Stores handover documents created at end of shift.
 * Contains summary, critical notes, and acknowledgment tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_handovers', function (Blueprint $table) {
            $table->id();
            
            // Link to the shift
            $table->foreignId('shift_id')->constrained('nursing_shifts')->onDelete('cascade');
            
            // Creator and recipient
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Ward context
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            
            // Shift timing info (denormalized for easy display)
            $table->enum('shift_type', ['morning', 'afternoon', 'night']);
            $table->datetime('shift_started_at');
            $table->datetime('shift_ended_at');
            
            // Content
            $table->text('summary')->nullable()->comment('Auto-generated from shift actions');
            $table->text('critical_notes')->nullable()->comment('Urgent/important items');
            $table->text('concluding_notes')->nullable()->comment('General shift notes');
            $table->json('pending_tasks')->nullable()->comment('Tasks not completed');
            $table->json('patient_highlights')->nullable()->comment('Key patient updates');
            
            // Statistics snapshot
            $table->json('action_summary')->nullable()->comment('Count of each action type');
            
            // Acknowledgment tracking
            $table->datetime('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('acknowledgment_notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['ward_id', 'created_at']);
            $table->index(['created_by', 'created_at']);
            $table->index('shift_ended_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_handovers');
    }
};
