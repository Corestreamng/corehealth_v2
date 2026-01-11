<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create Wards Table
 *
 * Reference: Plan Phase 1 - Ward Dashboard Foundation
 *
 * Current state: Beds have 'ward' as a text string field
 * New state: Wards are first-class entities with relationships
 *
 * Ward types based on hospital standards:
 * - general: General medical/surgical ward
 * - icu: Intensive Care Unit
 * - pediatric: Children's ward
 * - maternity: Obstetrics/Gynecology ward
 * - emergency: Emergency/Accident ward
 * - psychiatric: Mental health ward
 * - isolation: Infectious disease isolation
 * - recovery: Post-operative recovery
 * - private: Private/VIP rooms
 *
 * @see App\Models\Ward (to be created)
 * @see App\Models\Bed (ward_id relationship)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table) {
            $table->id();

            // Basic identification
            $table->string('name')->comment('Ward display name (e.g., "Male Medical Ward")');
            $table->string('code', 20)->nullable()->unique()->comment('Short code (e.g., "MMW", "ICU")');

            // Ward classification
            $table->enum('type', [
                'general',
                'icu',
                'pediatric',
                'maternity',
                'emergency',
                'psychiatric',
                'isolation',
                'recovery',
                'private',
                'other'
            ])->default('general')->comment('Ward type for categorization');

            // Capacity planning
            $table->integer('capacity')->default(0)->comment('Maximum bed capacity');

            // Location details
            $table->string('floor', 50)->nullable()->comment('Floor/level location');
            $table->string('building', 100)->nullable()->comment('Building name if multi-building');

            // Operational settings
            $table->string('nurse_station')->nullable()->comment('Associated nurse station');
            $table->string('contact_extension', 20)->nullable()->comment('Phone extension');

            // Nursing ratios (for staffing)
            $table->decimal('nurse_patient_ratio', 3, 1)->nullable()
                ->comment('Recommended nurse:patient ratio (e.g., 1:4 = 0.25)');

            // Status
            $table->boolean('is_active')->default(true)->comment('Ward operational status');

            // Audit trail
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wards');
    }
};
