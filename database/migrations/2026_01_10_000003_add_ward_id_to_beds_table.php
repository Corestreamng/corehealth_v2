<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration: Add ward_id to Beds Table
 *
 * Reference: Plan Phase 1 - Ward Dashboard Foundation
 *
 * This adds a foreign key relationship from beds to wards.
 * The existing 'ward' text column is kept for backward compatibility
 * during transition. A data migration populates ward_id from existing
 * ward text values.
 *
 * Also adds bed_status enum for better status tracking:
 * - available: Ready for new patient
 * - occupied: Currently has a patient
 * - reserved: Reserved for incoming patient
 * - maintenance: Under cleaning/repair
 * - out_of_service: Not available for use
 *
 * @see App\Models\Bed
 * @see App\Models\Ward
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('beds', function (Blueprint $table) {
            // Add ward_id foreign key
            $table->foreignId('ward_id')->nullable()->after('id')
                ->constrained('wards')->nullOnDelete()
                ->comment('FK to wards table');

            // Add bed status enum for better tracking
            $table->enum('bed_status', [
                'available',
                'occupied',
                'reserved',
                'maintenance',
                'out_of_service'
            ])->default('available')->after('status')
                ->comment('Detailed bed availability status');

            // Add index for faster ward filtering
            $table->index(['ward_id', 'bed_status']);
        });

        // Data migration: Create wards from existing bed.ward values
        // and link beds to their wards
        $this->migrateExistingWards();
    }

    /**
     * Migrate existing ward text values to wards table
     */
    private function migrateExistingWards(): void
    {
        // Get unique ward names from beds
        $existingWards = DB::table('beds')
            ->select('ward')
            ->whereNotNull('ward')
            ->where('ward', '!=', '')
            ->distinct()
            ->pluck('ward');

        foreach ($existingWards as $wardName) {
            // Create ward record
            $wardId = DB::table('wards')->insertGetId([
                'name' => $wardName,
                'code' => strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $wardName), 0, 5)),
                'type' => 'general',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update beds with this ward name
            DB::table('beds')
                ->where('ward', $wardName)
                ->update(['ward_id' => $wardId]);
        }

        // Update bed_status based on occupant_id
        DB::table('beds')
            ->whereNotNull('occupant_id')
            ->update(['bed_status' => 'occupied']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beds', function (Blueprint $table) {
            $table->dropForeign(['ward_id']);
            $table->dropIndex(['ward_id', 'bed_status']);
            $table->dropColumn(['ward_id', 'bed_status']);
        });
    }
};
