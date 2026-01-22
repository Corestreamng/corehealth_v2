<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;

/**
 * Seeder: StoreSeeder
 *
 * Plan Reference: Phase 1 - Seeders
 * Purpose: Create default stores required by the inventory system
 *
 * Default Stores:
 * - Pharmacy: Primary dispensing location, linked to pharmacy workbench
 * - Central Store: Main stock holding location for bulk items
 *
 * Related Models: Store
 * Related Files:
 * - app/Models/Store.php
 * - app/Http/Controllers/StoreWorkbenchController.php
 */
class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $stores = [
            [
                'store_name' => 'Pharmacy',
                'code' => 'PHR',
                'description' => 'Primary pharmacy dispensing location',
                'status' => 1,
                'is_default' => true,
                'location' => 'Ground Floor',
                'store_type' => 'pharmacy',
            ],
            [
                'store_name' => 'Central Store',
                'code' => 'CNT',
                'description' => 'Main stock holding location for bulk purchases',
                'status' => 1,
                'is_default' => false,
                'location' => 'Basement',
                'store_type' => 'warehouse',
            ],
            [
                'store_name' => 'Emergency Store',
                'code' => 'EMG',
                'description' => 'Emergency department stock',
                'status' => 1,
                'is_default' => false,
                'location' => 'Emergency Wing',
                'store_type' => 'pharmacy',
            ],
            [
                'store_name' => 'Theatre Store',
                'code' => 'THT',
                'description' => 'Operating theatre consumables and supplies',
                'status' => 1,
                'is_default' => false,
                'location' => '2nd Floor',
                'store_type' => 'theatre',
            ],
        ];

        foreach ($stores as $storeData) {
            Store::updateOrCreate(
                ['store_name' => $storeData['store_name']],
                $storeData
            );
        }

        $this->command->info('Default stores seeded successfully!');
    }
}
