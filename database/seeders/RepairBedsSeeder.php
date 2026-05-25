<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bed;
use App\Models\AdmissionRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RepairBedsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info("Starting hospital beds cleanup and repair script...");
        DB::beginTransaction();

        try {
            $repairedCount = 0;

            // 1. Repair DB Column Overwrite (Bug 1):
            // Find beds where status is 0 but they are actively occupied or had status set to 'occupied'
            // status = 0 means disabled, which makes them invisible.
            $corruptedBeds = Bed::where('status', 0)->get();
            foreach ($corruptedBeds as $bed) {
                // If the bed is valid and was mistakenly disabled by string-casting 'occupied' to 0
                $bed->status = 1;
                $bed->save();
                $this->command->warn("Repaired Bug 1: Enabled bed '{$bed->name}' in ward '{$bed->ward}' (restored status = 1).");
                $repairedCount++;
            }

            // 2. Repair Leaked Occupancy (Bugs 3 & 4):
            // Find beds where occupant_id is null but bed_status is 'occupied' or not 'available'
            $leakedBeds = Bed::whereNull('occupant_id')
                ->where('bed_status', 'occupied')
                ->get();
            foreach ($leakedBeds as $bed) {
                $bed->bed_status = 'available';
                $bed->save();
                $this->command->warn("Repaired Bug 3/4: Released vacant bed '{$bed->name}' (cleared bed_status = 'available').");
                $repairedCount++;
            }

            // 3. Repair Mismatched Active Admissions:
            // Find beds where occupant_id is set, but there is no active (undischarged) admission request for this occupant
            $occupiedBeds = Bed::whereNotNull('occupant_id')->get();
            foreach ($occupiedBeds as $bed) {
                $activeAdmission = AdmissionRequest::where('bed_id', $bed->id)
                    ->where('patient_id', $bed->occupant_id)
                    ->where('discharged', 0)
                    ->first();

                if (!$activeAdmission) {
                    // No active admission found for this bed/patient combination!
                    // Let's release the bed.
                    $bed->release();
                    $this->command->warn("Repaired Ghost Occupancy: Released bed '{$bed->name}' because occupant ID {$bed->occupant_id} has no active admission.");
                    $repairedCount++;
                }
            }

            // 4. Repair Partial Assignments (Bug 2):
            // Find beds where an active admission exists but occupant_id or bed_status is mismatched
            $activeAdmissions = AdmissionRequest::where('discharged', 0)
                ->whereNotNull('bed_id')
                ->get();
            foreach ($activeAdmissions as $admission) {
                $bed = Bed::find($admission->bed_id);
                if ($bed) {
                    if ($bed->occupant_id !== $admission->patient_id || $bed->bed_status !== 'occupied') {
                        $bed->assignPatient($admission->patient_id);
                        $this->command->warn("Repaired Bug 2: Synced active assignment for bed '{$bed->name}' and patient ID {$admission->patient_id}.");
                        $repairedCount++;
                    }
                }
            }

            DB::commit();
            $this->command->info("Cleanup completed successfully! Total records repaired: {$repairedCount}");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Cleanup failed: " . $e->getMessage());
            Log::error("Beds Repair Seeder failed", ['exception' => $e]);
        }
    }
}
