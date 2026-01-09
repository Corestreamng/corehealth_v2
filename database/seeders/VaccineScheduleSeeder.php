<?php

namespace Database\Seeders;

use App\Models\VaccineScheduleTemplate;
use App\Models\VaccineScheduleItem;
use Illuminate\Database\Seeder;

class VaccineScheduleSeeder extends Seeder
{
    /**
     * Seed the default Nigeria National Programme on Immunization (NPI) schedule.
     *
     * @return void
     */
    public function run()
    {
        // Create the default Nigeria NPI template
        $template = VaccineScheduleTemplate::firstOrCreate(
            ['name' => 'Nigeria NPI Schedule'],
            [
                'description' => 'Standard Nigeria National Programme on Immunization (NPI) vaccination schedule for children 0-9 months',
                'is_default' => true,
                'is_active' => true,
                'country' => 'Nigeria',
            ]
        );

        // Define the Nigeria NPI schedule
        $scheduleItems = [
            // At Birth (0 days)
            [
                'vaccine_name' => 'BCG',
                'vaccine_code' => 'BCG',
                'dose_number' => 1,
                'dose_label' => 'BCG',
                'age_days' => 0,
                'age_display' => 'At Birth',
                'route' => 'ID',
                'site' => 'Right Upper Arm',
                'notes' => 'Bacillus Calmette–Guérin vaccine for tuberculosis protection',
                'sort_order' => 1,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'OPV',
                'vaccine_code' => 'OPV-0',
                'dose_number' => 0,
                'dose_label' => 'OPV-0',
                'age_days' => 0,
                'age_display' => 'At Birth',
                'route' => 'Oral',
                'site' => 'Mouth',
                'notes' => 'Oral Polio Vaccine - Birth dose',
                'sort_order' => 2,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'HBV',
                'vaccine_code' => 'HBV-0',
                'dose_number' => 0,
                'dose_label' => 'HBV-0',
                'age_days' => 0,
                'age_display' => 'At Birth',
                'route' => 'IM',
                'site' => 'Left Thigh',
                'notes' => 'Hepatitis B Vaccine - Birth dose (within 24 hours)',
                'sort_order' => 3,
                'is_required' => true,
            ],

            // 6 Weeks (42 days)
            [
                'vaccine_name' => 'OPV',
                'vaccine_code' => 'OPV-1',
                'dose_number' => 1,
                'dose_label' => 'OPV-1',
                'age_days' => 42,
                'age_display' => '6 Weeks',
                'route' => 'Oral',
                'site' => 'Mouth',
                'notes' => 'Oral Polio Vaccine - First dose',
                'sort_order' => 4,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'Pentavalent',
                'vaccine_code' => 'PENTA-1',
                'dose_number' => 1,
                'dose_label' => 'Penta-1',
                'age_days' => 42,
                'age_display' => '6 Weeks',
                'route' => 'IM',
                'site' => 'Right Thigh',
                'notes' => 'Pentavalent (DPT-HepB-Hib) - First dose',
                'sort_order' => 5,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'PCV',
                'vaccine_code' => 'PCV-1',
                'dose_number' => 1,
                'dose_label' => 'PCV-1',
                'age_days' => 42,
                'age_display' => '6 Weeks',
                'route' => 'IM',
                'site' => 'Left Thigh',
                'notes' => 'Pneumococcal Conjugate Vaccine - First dose',
                'sort_order' => 6,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'Rotavirus',
                'vaccine_code' => 'ROTA-1',
                'dose_number' => 1,
                'dose_label' => 'Rota-1',
                'age_days' => 42,
                'age_display' => '6 Weeks',
                'route' => 'Oral',
                'site' => 'Mouth',
                'notes' => 'Rotavirus Vaccine - First dose',
                'sort_order' => 7,
                'is_required' => true,
            ],

            // 10 Weeks (70 days)
            [
                'vaccine_name' => 'OPV',
                'vaccine_code' => 'OPV-2',
                'dose_number' => 2,
                'dose_label' => 'OPV-2',
                'age_days' => 70,
                'age_display' => '10 Weeks',
                'route' => 'Oral',
                'site' => 'Mouth',
                'notes' => 'Oral Polio Vaccine - Second dose',
                'sort_order' => 8,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'Pentavalent',
                'vaccine_code' => 'PENTA-2',
                'dose_number' => 2,
                'dose_label' => 'Penta-2',
                'age_days' => 70,
                'age_display' => '10 Weeks',
                'route' => 'IM',
                'site' => 'Right Thigh',
                'notes' => 'Pentavalent (DPT-HepB-Hib) - Second dose',
                'sort_order' => 9,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'PCV',
                'vaccine_code' => 'PCV-2',
                'dose_number' => 2,
                'dose_label' => 'PCV-2',
                'age_days' => 70,
                'age_display' => '10 Weeks',
                'route' => 'IM',
                'site' => 'Left Thigh',
                'notes' => 'Pneumococcal Conjugate Vaccine - Second dose',
                'sort_order' => 10,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'Rotavirus',
                'vaccine_code' => 'ROTA-2',
                'dose_number' => 2,
                'dose_label' => 'Rota-2',
                'age_days' => 70,
                'age_display' => '10 Weeks',
                'route' => 'Oral',
                'site' => 'Mouth',
                'notes' => 'Rotavirus Vaccine - Second dose',
                'sort_order' => 11,
                'is_required' => true,
            ],

            // 14 Weeks (98 days)
            [
                'vaccine_name' => 'OPV',
                'vaccine_code' => 'OPV-3',
                'dose_number' => 3,
                'dose_label' => 'OPV-3',
                'age_days' => 98,
                'age_display' => '14 Weeks',
                'route' => 'Oral',
                'site' => 'Mouth',
                'notes' => 'Oral Polio Vaccine - Third dose',
                'sort_order' => 12,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'Pentavalent',
                'vaccine_code' => 'PENTA-3',
                'dose_number' => 3,
                'dose_label' => 'Penta-3',
                'age_days' => 98,
                'age_display' => '14 Weeks',
                'route' => 'IM',
                'site' => 'Right Thigh',
                'notes' => 'Pentavalent (DPT-HepB-Hib) - Third dose',
                'sort_order' => 13,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'PCV',
                'vaccine_code' => 'PCV-3',
                'dose_number' => 3,
                'dose_label' => 'PCV-3',
                'age_days' => 98,
                'age_display' => '14 Weeks',
                'route' => 'IM',
                'site' => 'Left Thigh',
                'notes' => 'Pneumococcal Conjugate Vaccine - Third dose',
                'sort_order' => 14,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'IPV',
                'vaccine_code' => 'IPV',
                'dose_number' => 1,
                'dose_label' => 'IPV',
                'age_days' => 98,
                'age_display' => '14 Weeks',
                'route' => 'IM',
                'site' => 'Left Thigh',
                'notes' => 'Inactivated Polio Vaccine',
                'sort_order' => 15,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'Rotavirus',
                'vaccine_code' => 'ROTA-3',
                'dose_number' => 3,
                'dose_label' => 'Rota-3',
                'age_days' => 98,
                'age_display' => '14 Weeks',
                'route' => 'Oral',
                'site' => 'Mouth',
                'notes' => 'Rotavirus Vaccine - Third dose (if using 3-dose schedule)',
                'sort_order' => 16,
                'is_required' => false, // Some rotavirus vaccines are 2-dose
            ],

            // 6 Months (180 days)
            [
                'vaccine_name' => 'Vitamin A',
                'vaccine_code' => 'VITA-1',
                'dose_number' => 1,
                'dose_label' => 'Vitamin A-1',
                'age_days' => 180,
                'age_display' => '6 Months',
                'route' => 'Oral',
                'site' => 'Mouth',
                'notes' => 'Vitamin A Supplementation - First dose (100,000 IU)',
                'sort_order' => 17,
                'is_required' => true,
            ],

            // 9 Months (270 days)
            [
                'vaccine_name' => 'Measles',
                'vaccine_code' => 'MCV-1',
                'dose_number' => 1,
                'dose_label' => 'Measles-1',
                'age_days' => 270,
                'age_display' => '9 Months',
                'route' => 'SC',
                'site' => 'Right Upper Arm',
                'notes' => 'Measles Vaccine - First dose',
                'sort_order' => 18,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'Yellow Fever',
                'vaccine_code' => 'YF',
                'dose_number' => 1,
                'dose_label' => 'Yellow Fever',
                'age_days' => 270,
                'age_display' => '9 Months',
                'route' => 'SC',
                'site' => 'Left Upper Arm',
                'notes' => 'Yellow Fever Vaccine',
                'sort_order' => 19,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'Meningitis',
                'vaccine_code' => 'MEN-A',
                'dose_number' => 1,
                'dose_label' => 'Men-A',
                'age_days' => 270,
                'age_display' => '9 Months',
                'route' => 'IM',
                'site' => 'Left Thigh',
                'notes' => 'Meningococcal A Conjugate Vaccine',
                'sort_order' => 20,
                'is_required' => true,
            ],
            [
                'vaccine_name' => 'Vitamin A',
                'vaccine_code' => 'VITA-2',
                'dose_number' => 2,
                'dose_label' => 'Vitamin A-2',
                'age_days' => 270,
                'age_display' => '9 Months',
                'route' => 'Oral',
                'site' => 'Mouth',
                'notes' => 'Vitamin A Supplementation - Second dose (200,000 IU)',
                'sort_order' => 21,
                'is_required' => true,
            ],

            // 12 Months (365 days) - Booster/Second doses
            [
                'vaccine_name' => 'Measles',
                'vaccine_code' => 'MCV-2',
                'dose_number' => 2,
                'dose_label' => 'Measles-2',
                'age_days' => 456, // 15 months
                'age_display' => '15 Months',
                'route' => 'SC',
                'site' => 'Right Upper Arm',
                'notes' => 'Measles Vaccine - Second dose (can be given as MMR)',
                'sort_order' => 22,
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

        $this->command->info('Nigeria NPI vaccine schedule seeded successfully!');
        $this->command->info('Total vaccines in schedule: ' . count($scheduleItems));
    }
}
