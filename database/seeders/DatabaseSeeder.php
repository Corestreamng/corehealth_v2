<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            // Core Setup
            DepartmentSeeder::class,
            WardSeeder::class,
            StoreSeeder::class,

            // Permissions & Roles
            HrPermissionsSeeder::class,
            AccountingPermissionSeeder::class,
            InventoryPermissionsSeeder::class,
            HmoExecutiveRoleSeeder::class,

            // Accounting
            ChartOfAccountsSeeder::class,
            SalariesPayableAccountSeeder::class,
            FixedAssetCategorySeeder::class,

            // HMO
            HmoSchemeSeeder::class,
            PrivateHmoSeeder::class,

            // Services & Products
            ServiceCategorySeeder::class,
            ProcedureCategorySeeder::class,
            ProcedureServiceCategorySeeder::class,
            ProductCategorySeeder::class,
            ProductSeeder::class,
            ServiceSeeder::class,

            // Other
            VaccineScheduleSeeder::class,
        ]);
    }
}
