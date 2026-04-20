<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migrate existing emergency_contact_* data into staff_next_of_kin table
 */
return new class extends Migration
{
    public function up(): void
    {
        $staff = DB::table('staff')
            ->whereNotNull('emergency_contact_name')
            ->where('emergency_contact_name', '!=', '')
            ->get(['id', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship']);

        foreach ($staff as $s) {
            // Check if already migrated
            $exists = DB::table('staff_next_of_kin')
                ->where('staff_id', $s->id)
                ->where('full_name', $s->emergency_contact_name)
                ->exists();

            if (!$exists) {
                DB::table('staff_next_of_kin')->insert([
                    'staff_id' => $s->id,
                    'full_name' => $s->emergency_contact_name,
                    'relationship' => $s->emergency_contact_relationship ?? 'Not specified',
                    'phone' => $s->emergency_contact_phone,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No rollback — data is supplementary
    }
};
