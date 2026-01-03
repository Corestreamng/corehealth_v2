<?php

namespace Database\Seeders;

use App\Models\HmoScheme;
use Illuminate\Database\Seeder;

class HmoSchemeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $schemes = [
            [
                'name' => 'Self/Private',
                'code' => 'SELF',
                'description' => 'Self-paying patients without insurance coverage',
                'status' => 1
            ],
            [
                'name' => 'Private Health Insurance Scheme',
                'code' => 'PHIS',
                'description' => 'Private health insurance providers',
                'status' => 1
            ],
            [
                'name' => 'Corporate',
                'code' => 'CORPORATE',
                'description' => 'Corporate/Company health insurance schemes',
                'status' => 1
            ],
            [
                'name' => 'National Health Insurance Scheme',
                'code' => 'NHIS',
                'description' => 'National health insurance scheme',
                'status' => 1
            ],
            [
                'name' => 'State Health Insurance Scheme',
                'code' => 'SHIS',
                'description' => 'State-level health insurance schemes',
                'status' => 1
            ],
            [
                'name' => 'Others',
                'code' => 'OTHERS',
                'description' => 'Other health insurance schemes',
                'status' => 1
            ]
        ];

        foreach ($schemes as $scheme) {
            HmoScheme::updateOrCreate(
                ['code' => $scheme['code']],
                $scheme
            );
        }

        $this->command->info('HMO Schemes seeded successfully');
    }
}
