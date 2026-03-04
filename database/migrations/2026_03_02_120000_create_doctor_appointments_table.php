<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_appointments', function (Blueprint $table) {
            $table->id();

            // Core relationships
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('clinic_id')->constrained('clinics');
            $table->foreignId('staff_id')->nullable()->constrained('staff')
                  ->comment('Assigned doctor (null = any available)');
            $table->foreignId('booked_by')->constrained('staff')
                  ->comment('Receptionist / self-service');

            // Schedule
            $table->date('appointment_date')->index();
            $table->time('start_time');
            $table->time('end_time')->nullable()->comment('Calculated from slot duration');
            $table->unsignedSmallInteger('duration_minutes')->default(15);

            // Status lifecycle
            $table->unsignedTinyInteger('status')->default(6)
                  ->comment('Uses QueueStatus enum: 6=Scheduled');
            $table->string('priority', 20)->default('routine')
                  ->comment('routine|urgent|emergency');
            $table->string('source', 30)->default('reception')
                  ->comment('reception|phone|online|referral|follow_up');
            $table->string('appointment_type', 30)->default('scheduled')
                  ->comment('scheduled|follow_up|referral|walk_in');

            // Notes
            $table->text('reason')->nullable()->comment('Reason for visit');
            $table->text('notes')->nullable()->comment('Internal notes (receptionist)');
            $table->text('cancellation_reason')->nullable();

            // Queue integration
            $table->foreignId('doctor_queue_id')->nullable()
                  ->comment('Set when appointment converts to live queue entry');
            $table->foreignId('service_request_id')->nullable()
                  ->comment('ProductOrServiceRequest created at check-in');

            // Follow-up linkage
            $table->foreignId('parent_appointment_id')->nullable()
                  ->comment('Self-ref: the original appointment this follow-up belongs to');
            $table->boolean('is_prepaid_followup')->default(false)
                  ->comment('True if billing is inherited from parent appointment');

            // Referral linkage
            $table->foreignId('referral_id')->nullable()
                  ->comment('Links to specialist_referrals if booked from a referral');

            // Rescheduling
            $table->foreignId('rescheduled_from_id')->nullable()
                  ->comment('Self-ref: original appointment if rescheduled');
            $table->unsignedSmallInteger('reschedule_count')->default(0)
                  ->comment('How many times this appointment chain has been rescheduled');

            // Doctor reassignment
            $table->foreignId('original_staff_id')->nullable()
                  ->comment('Original doctor before reassignment (null = never changed)');
            $table->text('reassignment_reason')->nullable()
                  ->comment('Why the doctor was changed');
            $table->timestamp('reassigned_at')->nullable();

            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('no_show_marked_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['appointment_date', 'clinic_id']);
            $table->index(['appointment_date', 'staff_id']);
            $table->index(['patient_id', 'appointment_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_appointments');
    }
};
