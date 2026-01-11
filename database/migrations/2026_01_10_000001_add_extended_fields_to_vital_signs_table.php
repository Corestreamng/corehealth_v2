<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add Extended Vital Signs Fields
 *
 * Reference: Plan Phase 1 - Database Migration for Vitals
 *
 * Current vital_signs table has:
 * - blood_pressure, temp, heart_rate, resp_rate, weight, other_notes, time_taken
 *
 * Adding per world-class nursing standards:
 * - height: For BMI calculation (WHO standard vital)
 * - spo2: Oxygen saturation (critical for respiratory assessment)
 * - blood_sugar: Blood glucose monitoring (diabetic care, post-op)
 * - bmi: Calculated and stored for trending
 * - pain_score: 0-10 pain scale (Joint Commission requirement)
 *
 * @see App\Models\VitalSign
 * @see NursingWorkbenchController::storeVitals()
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            // Height in centimeters for BMI calculation
            // Normal adult range: 100-250 cm
            $table->decimal('height', 5, 2)->nullable()->after('weight')
                ->comment('Height in centimeters');

            // Oxygen saturation percentage
            // Normal: 95-100%, Critical: <90%
            $table->decimal('spo2', 5, 2)->nullable()->after('height')
                ->comment('Oxygen saturation percentage');

            // Blood glucose in mg/dL
            // Fasting normal: 70-100, Random: <140
            $table->decimal('blood_sugar', 6, 2)->nullable()->after('spo2')
                ->comment('Blood glucose in mg/dL');

            // Body Mass Index (calculated from weight/height²)
            // Underweight: <18.5, Normal: 18.5-24.9, Overweight: 25-29.9, Obese: ≥30
            $table->decimal('bmi', 5, 2)->nullable()->after('blood_sugar')
                ->comment('Body Mass Index');

            // Pain score on 0-10 numeric rating scale (NRS)
            // 0: No pain, 1-3: Mild, 4-6: Moderate, 7-10: Severe
            $table->tinyInteger('pain_score')->nullable()->after('bmi')
                ->comment('Pain score 0-10');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->dropColumn(['height', 'spo2', 'blood_sugar', 'bmi', 'pain_score']);
        });
    }
};
