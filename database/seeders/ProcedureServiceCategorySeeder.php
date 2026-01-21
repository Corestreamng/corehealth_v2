<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use App\Models\ApplicationStatu;
use Illuminate\Database\Seeder;

class ProcedureServiceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates "Procedures" and "Registration" service categories
     * and updates application_status with their IDs.
     *
     * @return void
     */
    public function run()
    {
        // Create "Registration" service category
        $registrationCategory = ServiceCategory::firstOrCreate(
            ['category_code' => 'REG'],
            [
                'category_name' => 'Registration',
                'category_description' => 'Registration and administrative fees',
                'status' => 1,
            ]
        );

        // Create "Procedures" service category
        $procedureCategory = ServiceCategory::firstOrCreate(
            ['category_code' => 'PROC'],
            [
                'category_name' => 'Procedures',
                'category_description' => 'Surgical and non-surgical medical procedures',
                'status' => 1,
            ]
        );

        // Update application_status with the category IDs
        $appStatus = ApplicationStatu::first();
        if ($appStatus) {
            $appStatus->update([
                'registration_category_id' => $registrationCategory->id,
                'procedure_category_id' => $procedureCategory->id,
            ]);

            $this->command->info("Application status updated with category IDs:");
            $this->command->info("  - Registration Category ID: {$registrationCategory->id}");
            $this->command->info("  - Procedure Category ID: {$procedureCategory->id}");
        } else {
            $this->command->warn("No application_status record found. Please set category IDs manually in Hospital Config.");
        }

        $this->command->info("Service categories seeded successfully!");
    }
}
