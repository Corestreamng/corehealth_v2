<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Route Metadata Seeder
 *
 * Populates and synchronizes all navigation route metadata for global search.
 */
class RouteMetadataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        Artisan::call('routes:scan');
    }
}
