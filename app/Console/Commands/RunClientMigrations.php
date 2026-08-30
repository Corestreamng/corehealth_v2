<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class RunClientMigrations extends Command
{
    protected $signature = 'client:upgrade {database}';

    protected $description = 'Upgrade a specific client database';

    public function handle()
    {
        $database = $this->argument('database');

        $this->info("Starting DB Upgrade Script for {$database}...");

        // 1. Switch Database Connection
        DB::purge('mysql');
        Config::set('database.connections.mysql.database', $database);
        DB::reconnect('mysql');

        $dbName = DB::connection('mysql')->getDatabaseName();
        if ($dbName !== $database) {
            $this->error("Failed to switch database connection. Current DB: {$dbName}");

            return 1;
        }
        $this->info("Successfully connected to: {$dbName}");

        // 2. Run Migrations
        $this->info("Running migrations...");
        $exitCode = Artisan::call('migrate', [
            '--force' => true,
        ], $this->output);

        if ($exitCode !== 0) {
            $this->error("Migrations failed.");

            return $exitCode;
        }

        // 3. Run Main Seeders
        $mainSeeders = [
            // Permissions & Roles
            \Database\Seeders\HrPermissionsSeeder::class,
            \Database\Seeders\AccountingPermissionSeeder::class,
            \Database\Seeders\InventoryPermissionsSeeder::class,
            \Database\Seeders\HmoExecutiveRoleSeeder::class,
            \Database\Seeders\MaternityRoleSeeder::class,
            \Database\Seeders\SurgeryRoleSeeder::class,
            \Database\Seeders\AuditRoleSeeder::class,

            // Store Governance
            \Database\Seeders\StoreGovernanceSeeder::class,
            \Database\Seeders\StoreGovernancePermissionsSeeder::class,

            // HR Reference Data
            \Database\Seeders\HrReferenceDataSeeder::class,

            // Accounting
            \Database\Seeders\ChartOfAccountsSeeder::class,
            \Database\Seeders\SalariesPayableAccountSeeder::class,
            \Database\Seeders\FixedAssetCategorySeeder::class,

            // Services & Templates
            \Database\Seeders\ProcedureCategorySeeder::class,
            \Database\Seeders\ProcedureServiceCategorySeeder::class,
            \Database\Seeders\ProcedureConsentTemplateSeeder::class,
            \Database\Seeders\VaccineScheduleSeeder::class,
            \Database\Seeders\MaternalVaccineScheduleSeeder::class,
            \Database\Seeders\ClinicNoteTemplateSeeder::class,
        ];

        $this->info("Running Main Seeders...");
        foreach ($mainSeeders as $seederClass) {
            $this->info("Seeding: {$seederClass}");
            Artisan::call('db:seed', [
                '--class' => $seederClass,
                '--force' => true,
            ], $this->output);
        }

        // 4. Run Standalone Selected Seeders
        $standaloneSeeders = [
            \Database\Seeders\ComplementaryRequisitionsPermissionsSeeder::class,
            \Database\Seeders\GainLossDisposalAccountsSeeder::class,
            \Database\Seeders\MorgueServiceSeeder::class,
            \Database\Seeders\AssignLabImagingStoreRolesSeeder::class,
            \Database\Seeders\RepairBedsSeeder::class,
            \Database\Seeders\RelaxStoreGovernanceSeeder::class,
            \Database\Seeders\VitalRangeSeeder::class,
            \Database\Seeders\WhoGrowthStandardsSeeder::class,
        ];

        $this->info("Running Standalone Seeders...");
        foreach ($standaloneSeeders as $seederClass) {
            if (class_exists($seederClass)) {
                $this->info("Seeding: {$seederClass}");
                Artisan::call('db:seed', [
                    '--class' => $seederClass,
                    '--force' => true,
                ], $this->output);
            }
        }

        $this->info("Upgrade completed successfully!");

        return 0;
    }
}
