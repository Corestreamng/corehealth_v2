<?php

namespace Database\Seeders;

use App\Models\VaccineScheduleTemplate;
use App\Models\VaccineScheduleItem;
use Illuminate\Database\Seeder;

class MaternalVaccineScheduleSeeder extends Seeder
{
    /**
     * Seed the Nigeria ANC Maternal Immunization Schedule.
     *
     * This covers vaccines and prophylaxis recommended during pregnancy
     * per Nigeria Federal Ministry of Health ANC guidelines.
     *
     * NOTE: age_days here represents "days from LMP" (gestational age),
     * NOT age from birth. The controller must use enrollment LMP
     * to calculate due dates instead of patient DOB.
     *
     * @return void
     */
    public function run()
    {
        $template = VaccineScheduleTemplate::firstOrCreate(
            ['name' => 'Nigeria ANC Maternal Schedule'],
            [
                'description' => 'Standard Nigeria ANC maternal immunization and prophylaxis schedule (Td, IPTp-SP, Iron/Folic Acid, Deworming)',
                'is_default' => false,
                'is_active' => true,
                'country' => 'Nigeria',
            ]
        );

        // Maternal ANC schedule items
        // age_days = gestational age in days when the vaccine/prophylaxis is due
        $scheduleItems = [
            // ── Tetanus Diphtheria (Td) ──────────────────────────
            [
                'vaccine_name' => 'Tetanus Diphtheria (Td)',
                'vaccine_code' => 'TD-1',
                'dose_number' => 1,
                'dose_label' => 'Td-1',
                'age_days' => 140,  // ~20 weeks GA (first ANC visit or ≥20 weeks)
                'age_display' => '20 Weeks GA',
                'route' => 'IM',
                'site' => 'Left Deltoid',
                'notes' => 'Tetanus Diphtheria vaccine – First dose. Give at first ANC contact if unvaccinated or status unknown.',
                'sort_order' => 1,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'Tetanus Diphtheria (Td)',
                'vaccine_code' => 'TD-2',
                'dose_number' => 2,
                'dose_label' => 'Td-2',
                'age_days' => 168,  // ~24 weeks GA (4 weeks after TD-1)
                'age_display' => '24 Weeks GA',
                'route' => 'IM',
                'site' => 'Left Deltoid',
                'notes' => 'Tetanus Diphtheria vaccine – Second dose. At least 4 weeks after Td-1.',
                'sort_order' => 2,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'Tetanus Diphtheria (Td)',
                'vaccine_code' => 'TD-3',
                'dose_number' => 3,
                'dose_label' => 'Td-3 (booster)',
                'age_days' => 350,   // ~50 weeks (6 months after Td-2, often postpartum)
                'age_display' => '6 Months Post Td-2',
                'route' => 'IM',
                'site' => 'Left Deltoid',
                'notes' => 'Tetanus Diphtheria vaccine – Third dose (booster). 6 months after Td-2. May be given postpartum.',
                'sort_order' => 3,
                'is_required' => false,
            ],

            // ── IPTp-SP (Intermittent Preventive Treatment for Malaria in Pregnancy) ──
            [
                'vaccine_name' => 'IPTp-SP (Sulphadoxine-Pyrimethamine)',
                'vaccine_code' => 'IPTP-1',
                'dose_number' => 1,
                'dose_label' => 'IPTp-SP 1',
                'age_days' => 91,   // 13 weeks GA – start of 2nd trimester
                'age_display' => '13 Weeks GA',
                'route' => 'Oral',
                'site' => 'Mouth',
                'notes' => 'Intermittent Preventive Treatment – First dose. Give as DOT (Directly Observed Therapy). NOT in 1st trimester.',
                'sort_order' => 4,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'IPTp-SP (Sulphadoxine-Pyrimethamine)',
                'vaccine_code' => 'IPTP-2',
                'dose_number' => 2,
                'dose_label' => 'IPTp-SP 2',
                'age_days' => 140,  // 20 weeks GA
                'age_display' => '20 Weeks GA',
                'route' => 'Oral',
                'site' => 'Mouth',
                'notes' => 'Intermittent Preventive Treatment – Second dose. At least 1 month after IPTp-1.',
                'sort_order' => 5,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'IPTp-SP (Sulphadoxine-Pyrimethamine)',
                'vaccine_code' => 'IPTP-3',
                'dose_number' => 3,
                'dose_label' => 'IPTp-SP 3',
                'age_days' => 196,  // 28 weeks GA
                'age_display' => '28 Weeks GA',
                'route' => 'Oral',
                'site' => 'Mouth',
                'notes' => 'Intermittent Preventive Treatment – Third dose. At least 1 month after IPTp-2.',
                'sort_order' => 6,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'IPTp-SP (Sulphadoxine-Pyrimethamine)',
                'vaccine_code' => 'IPTP-4',
                'dose_number' => 4,
                'dose_label' => 'IPTp-SP 4',
                'age_days' => 224,  // 32 weeks GA
                'age_display' => '32 Weeks GA',
                'route' => 'Oral',
                'site' => 'Mouth',
                'notes' => 'Intermittent Preventive Treatment – Fourth dose (optional). At least 1 month after IPTp-3.',
                'sort_order' => 7,
                'is_required' => false,
            ],

            // ── Iron/Folic Acid Supplementation ──
            [
                'vaccine_name' => 'Iron/Folic Acid',
                'vaccine_code' => 'IFA-START',
                'dose_number' => 1,
                'dose_label' => 'Iron/Folic Acid (Start)',
                'age_days' => 0,    // From first ANC visit
                'age_display' => 'First ANC Visit',
                'route' => 'Oral',
                'site' => 'Mouth',
                'notes' => 'Daily Iron (60mg) + Folic Acid (400μg) supplementation. Start at first ANC visit and continue throughout pregnancy.',
                'sort_order' => 8,
                'is_required' => true,
            ],

            // ── Deworming (Albendazole) ──
            [
                'vaccine_name' => 'Deworming (Albendazole)',
                'vaccine_code' => 'DEWORM',
                'dose_number' => 1,
                'dose_label' => 'Albendazole',
                'age_days' => 91,   // 13 weeks GA – 2nd trimester
                'age_display' => '2nd Trimester',
                'route' => 'Oral',
                'site' => 'Mouth',
                'notes' => 'Albendazole 400mg single dose. Give in 2nd trimester. Contraindicated in 1st trimester.',
                'sort_order' => 9,
                'is_required' => true,
            ],

            // ── LLIN (Long Lasting Insecticidal Net) ──
            [
                'vaccine_name' => 'LLIN Distribution',
                'vaccine_code' => 'LLIN',
                'dose_number' => 1,
                'dose_label' => 'LLIN (Bed Net)',
                'age_days' => 0,    // At first ANC visit
                'age_display' => 'First ANC Visit',
                'route' => 'Oral',  // Not applicable but required field
                'site' => 'N/A',
                'notes' => 'Long Lasting Insecticidal Net distribution. Document receipt at first ANC visit.',
                'sort_order' => 10,
                'is_required' => false,
            ],
        ];

        foreach ($scheduleItems as $itemData) {
            VaccineScheduleItem::firstOrCreate(
                [
                    'template_id' => $template->id,
                    'vaccine_code' => $itemData['vaccine_code'],
                ],
                array_merge($itemData, ['template_id' => $template->id])
            );
        }

        $this->command->info('Nigeria ANC Maternal immunization schedule seeded successfully!');
        $this->command->info('Total items in schedule: ' . count($scheduleItems));
    }
}
