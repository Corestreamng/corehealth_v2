<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\ClinicNoteTemplate;
use Illuminate\Database\Seeder;

class ClinicNoteTemplateSeeder extends Seeder
{
    /**
     * Seed additional clinics and clinical note templates.
     */
    public function run(): void
    {
        $this->seedGlobalTemplates();
        $this->seedSpecialtyTemplates();
        $this->printSummary();
    }

    // ─── Global templates (clinic_id = null → all clinics) ─────────────

    private function seedGlobalTemplates(): void
    {
        $this->command->info('Seeding global templates...');
        $count = 0;

        $jsonPath = database_path('data/clinic_note_global_templates.json');
        if (!file_exists($jsonPath)) {
            $this->command->error("Global templates data file not found: {$jsonPath}");

            return;
        }

        $templates = json_decode(file_get_contents($jsonPath), true) ?: [];

        foreach ($templates as $t) {
            if (!ClinicNoteTemplate::whereNull('clinic_id')->where('name', $t['name'])->exists()) {
                ClinicNoteTemplate::create([
                    'clinic_id' => null,
                    'name' => $t['name'],
                    'description' => $t['description'],
                    'content' => $t['content'],
                    'category' => $t['category'],
                    'sort_order' => $t['sort_order'],
                    'is_active' => true,
                    'created_by' => 1,
                ]);
                $count++;
                $this->command->line("  + {$t['category']}: {$t['name']}");
            }
        }

        $this->command->info("Global templates seeded: {$count}");
    }

    // ─── Specialty templates ───────────────────────────────────────────

    private function seedSpecialtyTemplates(): void
    {
        $this->command->info('Seeding specialty templates...');
        $count = 0;

        $jsonPath = database_path('data/clinic_note_specialty_templates.json');
        if (!file_exists($jsonPath)) {
            $this->command->error("Specialty templates data file not found: {$jsonPath}");

            return;
        }

        $specialtyMap = json_decode(file_get_contents($jsonPath), true) ?: [];

        foreach ($specialtyMap as $clinicName => $templates) {
            $clinic = Clinic::where('name', $clinicName)->first();
            if (!$clinic) {
                $this->command->warn("  Clinic not found: {$clinicName} — skipping");

                continue;
            }

            foreach ($templates as $idx => $t) {
                if (!ClinicNoteTemplate::where('clinic_id', $clinic->id)->where('name', $t['name'])->exists()) {
                    ClinicNoteTemplate::create([
                        'clinic_id' => $clinic->id,
                        'name' => $t['name'],
                        'description' => $t['description'],
                        'content' => $t['content'],
                        'category' => $t['category'],
                        'sort_order' => $idx + 10,
                        'is_active' => true,
                        'created_by' => 1,
                    ]);
                    $count++;
                    $this->command->line("  + [{$clinicName}] {$t['category']}: {$t['name']}");
                }
            }
        }

        $this->command->info("Specialty templates seeded: {$count}");
    }

    // ─── Summary ──────────────────────────────────────────────────────────

    private function printSummary(): void
    {
        $total = ClinicNoteTemplate::count();
        $global = ClinicNoteTemplate::whereNull('clinic_id')->count();
        $specialty = ClinicNoteTemplate::whereNotNull('clinic_id')->count();
        $clinics = Clinic::count();

        $this->command->info('');
        $this->command->info('═══ CLINICAL NOTE TEMPLATES SUMMARY ═══');
        $this->command->info("  Total Templates in DB: {$total}");
        $this->command->info("  Global Templates:      {$global}");
        $this->command->info("  Specialty Templates:   {$specialty}");
        $this->command->info("  Total Clinics:         {$clinics}");
        $this->command->info('═════════════════════════════════════════');
    }
}
