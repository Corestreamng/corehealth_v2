<?php

namespace Database\Seeders;

use App\Models\Ward;
use Illuminate\Database\Seeder;

class WardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $wards = [
            [
                'name' => 'General Ward',
                'code' => 'GW',
                'type' => 'general',
                'capacity' => 20,
                'floor' => 'Ground Floor',
                'building' => 'Block A',
                'nurse_station' => 'NS-A1',
                'contact_extension' => '101',
                'is_active' => true,
            ],
            [
                'name' => 'Intensive Care Unit (ICU)',
                'code' => 'ICU',
                'type' => 'icu',
                'capacity' => 8,
                'floor' => 'First Floor',
                'building' => 'Block A',
                'nurse_station' => 'NS-ICU',
                'contact_extension' => '111',
                'nurse_patient_ratio' => 0.5, // 1:2 ratio for ICU
                'is_active' => true,
            ],
            [
                'name' => 'Pediatric Ward',
                'code' => 'PED',
                'type' => 'pediatric',
                'capacity' => 15,
                'floor' => 'Ground Floor',
                'building' => 'Block B',
                'nurse_station' => 'NS-B1',
                'contact_extension' => '201',
                'is_active' => true,
            ],
            [
                'name' => 'Maternity Ward',
                'code' => 'MAT',
                'type' => 'maternity',
                'capacity' => 12,
                'floor' => 'First Floor',
                'building' => 'Block B',
                'nurse_station' => 'NS-B2',
                'contact_extension' => '211',
                'is_active' => true,
            ],
            [
                'name' => 'Surgical Ward',
                'code' => 'SUR',
                'type' => 'recovery', // Post-operative recovery
                'capacity' => 16,
                'floor' => 'Ground Floor',
                'building' => 'Block C',
                'nurse_station' => 'NS-C1',
                'contact_extension' => '301',
                'is_active' => true,
            ],
            [
                'name' => 'Emergency Ward',
                'code' => 'ER',
                'type' => 'emergency',
                'capacity' => 10,
                'floor' => 'Ground Floor',
                'building' => 'Emergency Wing',
                'nurse_station' => 'NS-ER',
                'contact_extension' => '911',
                'is_active' => true,
            ],
            [
                'name' => 'Private Ward',
                'code' => 'VIP',
                'type' => 'private',
                'capacity' => 10,
                'floor' => 'All Floors',
                'building' => 'Block D',
                'nurse_station' => 'NS-D1',
                'contact_extension' => '401',
                'is_active' => true,
            ],
        ];

        foreach ($wards as $ward) {
            Ward::updateOrCreate(
                ['name' => $ward['name']],
                $ward
            );
        }

        $this->command->info('Wards seeded successfully!');
    }
}
