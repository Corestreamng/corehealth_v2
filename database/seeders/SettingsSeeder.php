<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        // Check if settings table exists
        if (DB::getSchemaBuilder()->hasTable('settings')) {
            DB::table('settings')->insert([
                'key' => 'company_logo',
                'value' => '/images/default-logo.png',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Check if companies table exists
        if (DB::getSchemaBuilder()->hasTable('companies')) {
            DB::table('companies')->insert([
                'name' => 'CoreHealth',
                'logo' => '/images/default-logo.png',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}
