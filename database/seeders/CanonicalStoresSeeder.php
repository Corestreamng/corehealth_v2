<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;
use App\Models\Ward;
use App\Models\Department;
use Illuminate\Support\Str;

class CanonicalStoresSeeder extends Seeder
{
    public function run(): void
    {
        // 1. CANONICAL CENTRAL STORE — id=3
        $central = Store::find(3);
        if ($central) {
            Store::withoutEvents(function () use ($central) {
                $central->update([
                    'store_name' => 'Central Store', 'code' => 'CNT',
                    'store_type' => 'warehouse', 'distribution_role' => Store::ROLE_CENTRAL,
                    'is_default' => false, 'is_immutable' => true, 'status' => 1,
                ]);
            });
            $this->command->info('Central Store (id=3) set as immutable ROLE_CENTRAL.');
        } else {
            Store::withoutEvents(function () {
                Store::create([
                    'store_name' => 'Central Store', 'code' => 'CNT',
                    'description' => 'Main bulk stock. Canonical.',
                    'location' => 'Basement', 'store_type' => 'warehouse',
                    'distribution_role' => Store::ROLE_CENTRAL,
                    'is_default' => false, 'is_immutable' => true, 'status' => 1,
                ]);
            });
            $this->command->info('Central Store created as immutable ROLE_CENTRAL.');
        }

        // 2. CANONICAL PHARMACY HUB — id=2
        $pharmacy = Store::find(2);
        if ($pharmacy) {
            Store::withoutEvents(function () use ($pharmacy) {
                $pharmacy->update([
                    'store_name' => 'Pharmacy', 'code' => 'PHR',
                    'store_type' => 'pharmacy', 'distribution_role' => Store::ROLE_PHARMACY_HUB,
                    'allows_direct_patient_dispense' => true,
                    'is_default' => true, 'is_immutable' => true, 'status' => 1,
                ]);
            });
            $this->command->info('Pharmacy (id=2) set as immutable ROLE_PHARMACY_HUB.');
        } else {
            Store::withoutEvents(function () {
                Store::create([
                    'store_name' => 'Pharmacy', 'code' => 'PHR',
                    'description' => 'Primary pharmacy hub. Canonical.',
                    'location' => 'Ground Floor', 'store_type' => 'pharmacy',
                    'distribution_role' => Store::ROLE_PHARMACY_HUB,
                    'allows_direct_patient_dispense' => true,
                    'is_default' => true, 'is_immutable' => true, 'status' => 1,
                ]);
            });
            $this->command->info('Pharmacy created as immutable ROLE_PHARMACY_HUB.');
        }

        // 3. DEACTIVATE DUPLICATE — id=1 'Centeral'
        $duplicate = Store::find(1);
        if ($duplicate && $duplicate->status) {
            Store::withoutEvents(function () use ($duplicate) {
                $duplicate->update([
                    'status' => 0, 'distribution_role' => Store::ROLE_OTHER,
                    'description' => 'Deactivated — superseded by Central Store (id=3).',
                ]);
            });
            $this->command->warn('Store id=1 "Centeral" deactivated.');
        }

        // Emergency Store id=4 → ROLE_DEPARTMENT dept 5
        $emergency = Store::find(4);
        if ($emergency && !$emergency->department_id) {
            Store::withoutEvents(function () use ($emergency) {
                $emergency->update(['distribution_role' => Store::ROLE_DEPARTMENT, 'department_id' => 5, 'store_type' => 'other']);
            });
            $this->command->info('Emergency Store (id=4) → ROLE_DEPARTMENT dept 5.');
        }

        // Theatre Store id=5 → ROLE_DEPARTMENT dept 2
        $theatre = Store::find(5);
        if ($theatre && !$theatre->department_id) {
            Store::withoutEvents(function () use ($theatre) {
                $theatre->update(['distribution_role' => Store::ROLE_DEPARTMENT, 'department_id' => 2, 'store_type' => 'other']);
            });
            $this->command->info('Theatre Store (id=5) → ROLE_DEPARTMENT dept 2.');
        }

        // 4. WARD STORES
        foreach (Ward::where('is_active', 1)->get() as $ward) {
            if (Store::where('ward_id', $ward->id)->exists()) {
                $this->command->line("  – Ward store exists for [{$ward->name}], skip.");
                continue;
            }
            $code = $ward->code ? Str::upper($ward->code) . '_WS' : 'W' . $ward->id . '_WS';
            $base = $code; $n = 0;
            while (Store::where('code', $code)->exists()) { $code = $base . (++$n); }
            Store::withoutEvents(function () use ($ward, $code) {
                Store::create([
                    'store_name' => $ward->name . ' Store', 'code' => $code,
                    'description' => "Ward store for {$ward->name}",
                    'location' => $ward->floor ?? $ward->name,
                    'store_type' => 'ward', 'distribution_role' => Store::ROLE_WARD,
                    'ward_id' => $ward->id, 'requires_shift_context' => true,
                    'allows_direct_patient_dispense' => false,
                    'status' => 1, 'is_default' => false, 'is_immutable' => false,
                ]);
            });
            $this->command->info("Created ward store [{$ward->name} Store] ward_id={$ward->id}.");
        }

        // 5. DEPARTMENT STORES
        foreach (Department::where('is_active', 1)->get() as $dept) {
            if (Store::where('department_id', $dept->id)->where('distribution_role', Store::ROLE_DEPARTMENT)->exists()) {
                $this->command->line("  – Dept store exists for [{$dept->name}], skip.");
                continue;
            }
            $code = $dept->code ? Str::upper($dept->code) . '_DS' : 'D' . $dept->id . '_DS';
            $base = $code; $n = 0;
            while (Store::where('code', $code)->exists()) { $code = $base . (++$n); }
            Store::withoutEvents(function () use ($dept, $code) {
                Store::create([
                    'store_name' => $dept->name . ' Store', 'code' => $code,
                    'description' => "Dept store for {$dept->name}",
                    'location' => $dept->name,
                    'store_type' => 'other', 'distribution_role' => Store::ROLE_DEPARTMENT,
                    'department_id' => $dept->id, 'requires_shift_context' => false,
                    'allows_direct_patient_dispense' => false,
                    'status' => 1, 'is_default' => false, 'is_immutable' => false,
                ]);
            });
            $this->command->info("Created dept store [{$dept->name} Store] dept_id={$dept->id}.");
        }

        $this->command->info('CanonicalStoresSeeder complete.');
    }
}
