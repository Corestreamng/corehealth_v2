<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialist_referrals', function (Blueprint $table) {
            $table->id();

            // Who is being referred
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('encounter_id')->constrained('encounters')
                  ->comment('The encounter that generated this referral');

            // Referring doctor
            $table->foreignId('referring_doctor_id')->constrained('staff');
            $table->foreignId('referring_clinic_id')->constrained('clinics');

            // Referral target
            $table->enum('referral_type', ['internal', 'external'])->default('internal')
                  ->comment('internal = in-hospital specialist, external = outside hospital');
            $table->foreignId('target_clinic_id')->nullable()->constrained('clinics')
                  ->comment('For internal: the specialist clinic');
            $table->foreignId('target_doctor_id')->nullable()->constrained('staff')
                  ->comment('For internal: specific specialist (null = any in target clinic)');
            $table->string('external_facility_name')->nullable()
                  ->comment('For external referrals');
            $table->string('external_doctor_name')->nullable();
            $table->string('external_facility_address')->nullable();
            $table->string('external_facility_phone')->nullable();

            // Specialization
            $table->foreignId('target_specialization_id')->nullable()
                  ->constrained('specializations')
                  ->comment('The specialization being referred to');

            // Clinical details
            $table->text('reason')->comment('Why the referral is being made');
            $table->text('clinical_summary')->nullable()
                  ->comment('Summary of findings for the specialist');
            $table->text('provisional_diagnosis')->nullable();
            $table->enum('urgency', ['routine', 'urgent', 'emergency'])->default('routine');

            // Status lifecycle
            $table->enum('status', [
                'pending',       // Doctor created, waiting for reception action
                'booked',        // Reception created an appointment for internal referral
                'referred_out',  // Reception marked as referred externally
                'completed',     // Specialist consultation happened
                'declined',      // Specialist/facility declined
                'cancelled',     // Cancelled by reception or doctor
            ])->default('pending');

            // Reception action tracking
            $table->foreignId('actioned_by')->nullable()->constrained('staff')
                  ->comment('Receptionist who processed the referral');
            $table->timestamp('actioned_at')->nullable();
            $table->text('action_notes')->nullable();

            // Links
            $table->foreignId('appointment_id')->nullable()
                  ->comment('DoctorAppointment created for internal referral');
            $table->foreignId('referral_letter_attachment_id')->nullable()
                  ->comment('Uploaded referral letter for external');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
            $table->index(['referring_doctor_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialist_referrals');
    }
};
