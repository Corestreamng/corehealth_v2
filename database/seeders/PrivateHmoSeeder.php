<?php

namespace Database\Seeders;

use App\Models\Hmo;
use App\Models\HmoScheme;
use Illuminate\Database\Seeder;

class PrivateHmoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get SELF scheme
        $selfScheme = HmoScheme::where('code', 'SELF')->first();

        // Create or update Private HMO with id 1 (using updateOrCreate for idempotent seeding)
        Hmo::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Private',
                'desc' => 'Private/Self-paying patients',
                'status' => 1,
                'discount' => 0,
                'hmo_scheme_id' => $selfScheme ? $selfScheme->id : null
            ]
        );

        $this->command->info('Private HMO seeded successfully with ID 1');
    }
}
