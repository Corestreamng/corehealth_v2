<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MorgueServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 1. Create Morgue category with ID 9
        $morgueCategory = \App\Models\ServiceCategory::updateOrCreate(
            ['id' => 9],
            ['category_name' => 'Morgue', 'status' => 1]
        );

        // 2. Update application_status with morgue_category_id
        $config = \App\Models\ApplicationStatu::first();
        if ($config) {
            $config->update(['morgue_category_id' => 9]);
        }

        // 3. Seed initial services
        $services = [
            ['name' => 'Daily Morgue Rate', 'code' => 'MORG-001', 'price' => 5000],
            ['name' => 'Embalming', 'code' => 'MORG-002', 'price' => 50000],
            ['name' => 'Autopsy', 'code' => 'MORG-003', 'price' => 100000],
            ['name' => 'Body Washing', 'code' => 'MORG-004', 'price' => 10000],
        ];

        $admin = \App\Models\User::whereHas('roles', function($q) {
            $q->where('name', 'ADMIN');
        })->first() ?? \App\Models\User::first();

        foreach ($services as $s) {
            $service = \App\Models\Service::updateOrCreate(
                ['service_code' => $s['code']],
                [
                    'service_name' => $s['name'],
                    'category_id' => 9,
                    'user_id' => $admin ? $admin->id : 1,
                    'status' => 1
                ]
            );

            \App\Models\ServicePrice::updateOrCreate(
                ['service_id' => $service->id],
                ['sale_price' => $s['price']]
            );
        }
    }
}
