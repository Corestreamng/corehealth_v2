<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StoreLanePolicy;

class RelaxStoreGovernanceSeeder extends Seeder
{
    /**
     * Run the database seeds to relax store lane policies.
     * Can be run on production via: php artisan db:seed --class=RelaxStoreGovernanceSeeder
     *
     * @return void
     */
    public function run(): void
    {
        StoreLanePolicy::updateOrCreate(
            [
                'source_role'      => 'central',
                'destination_role' => 'ward',
            ],
            [
                'allowed'                 => true,
                'requires_approval_level' => 'none',
                'notes'                   => 'Central → Ward Store (standard replenishment, relaxed to no approval required)',
            ]
        );

        $this->command?->info('Store governance lane policy for Central -> Ward has been successfully relaxed to "none"!');
    }
}
