<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create Shift Actions Table
 * 
 * Logs individual actions performed during a shift for detailed tracking.
 * Complements the audit log with nursing-specific categorization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_actions', function (Blueprint $table) {
            $table->id();
            
            // Link to shift
            $table->foreignId('shift_id')->constrained('nursing_shifts')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Action classification
            $table->string('action_type', 50)->comment('vitals, medication, note, injection, immunization, bill, admission, discharge, other');
            $table->string('action_subtype', 50)->nullable()->comment('More specific classification');
            
            // Description
            $table->string('description');
            $table->text('details')->nullable();
            
            // Patient context
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('patient_name')->nullable()->comment('Denormalized for display');
            
            // Link to auditable record
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            
            // Additional metadata
            $table->json('metadata')->nullable();
            
            // Importance flag
            $table->boolean('is_critical')->default(false)->comment('Highlight in handover');
            
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes
            $table->index(['shift_id', 'action_type']);
            $table->index(['shift_id', 'is_critical']);
            $table->index(['patient_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_actions');
    }
};
