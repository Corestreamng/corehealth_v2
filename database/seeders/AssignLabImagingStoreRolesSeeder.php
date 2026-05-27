<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Assigns the new 'lab' and 'imaging' distribution_role values to existing stores
 * based on keyword-matching in their store_name or code.
 *
 * Lab keywords:   Lab, Laboratory, Microbiology, Haematology, Pathology, Biochemistry
 * Imaging keywords: Radiology, X-ray, Xray, Scan, Imaging, Ultrasound, MRI, CT
 *
 * Run via:
 *   php artisan db:seed --class=AssignLabImagingStoreRolesSeeder
 *
 * Safe to re-run — uses updateOrCreate semantics on targeted stores only.
 */
class AssignLabImagingStoreRolesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('');
        $this->command?->info('═══════════════════════════════════════════════');
        $this->command?->info('  Store Governance — Lab & Imaging Role Assignment');
        $this->command?->info('═══════════════════════════════════════════════');

        // ── 1. Show ALL stores before changes ──
        $allStores = Store::orderBy('store_name')->get();
        $this->command?->info('');
        $this->command?->info('Current Store Roles (' . $allStores->count() . ' stores):');
        $this->command?->table(
            ['ID', 'Store Name', 'Code', 'Type', 'Current Role'],
            $allStores->map(fn($s) => [
                $s->id,
                $s->store_name,
                $s->code ?? '—',
                $s->store_type ?? '—',
                $s->distribution_role ?? 'null',
            ])->toArray()
        );

        // ── 2. Find lab stores ──
        $labKeywords = ['lab', 'laboratory', 'microbiology', 'haematology', 'pathology', 'biochemistry'];
        $labStores = Store::where(function ($q) use ($labKeywords) {
            foreach ($labKeywords as $kw) {
                $q->orWhere('store_name', 'LIKE', "%{$kw}%");
                $q->orWhere('code', 'LIKE', "%{$kw}%");
            }
        })->get();

        $labUpdated = 0;
        foreach ($labStores as $store) {
            if ($store->distribution_role !== Store::ROLE_LAB) {
                $oldRole = $store->distribution_role;
                $store->update(['distribution_role' => Store::ROLE_LAB]);
                $labUpdated++;
                $this->command?->line("  ✓ {$store->store_name} (ID:{$store->id}): {$oldRole} → lab");
            }
        }

        // ── 3. Find imaging stores ──
        $imagingKeywords = ['radiology', 'x-ray', 'xray', 'scan', 'imaging', 'ultrasound', 'mri', 'ct scan'];
        $imagingStores = Store::where(function ($q) use ($imagingKeywords) {
            foreach ($imagingKeywords as $kw) {
                $q->orWhere('store_name', 'LIKE', "%{$kw}%");
                $q->orWhere('code', 'LIKE', "%{$kw}%");
            }
        })->get();

        $imagingUpdated = 0;
        foreach ($imagingStores as $store) {
            // Don't override if already set to lab (in case a store matches both)
            if ($store->distribution_role !== Store::ROLE_IMAGING && $store->distribution_role !== Store::ROLE_LAB) {
                $oldRole = $store->distribution_role;
                $store->update(['distribution_role' => Store::ROLE_IMAGING]);
                $imagingUpdated++;
                $this->command?->line("  ✓ {$store->store_name} (ID:{$store->id}): {$oldRole} → imaging");
            }
        }

        // ── 4. Summary ──
        $this->command?->info('');
        $this->command?->info("Lab stores updated:     {$labUpdated}");
        $this->command?->info("Imaging stores updated: {$imagingUpdated}");

        // ── 5. After table ──
        $afterStores = Store::orderBy('store_name')->get();
        $this->command?->info('');
        $this->command?->info('Updated Store Roles:');
        $this->command?->table(
            ['ID', 'Store Name', 'Code', 'Type', 'New Role'],
            $afterStores->map(fn($s) => [
                $s->id,
                $s->store_name,
                $s->code ?? '—',
                $s->store_type ?? '—',
                $s->distribution_role ?? 'null',
            ])->toArray()
        );

        $this->command?->info('');
        $this->command?->info('✅ Store governance role assignment complete.');
    }
}
