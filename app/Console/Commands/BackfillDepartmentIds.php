<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillDepartmentIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'corehealth:backfill-departments 
                            {--dry-run : Report what would be updated without writing changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill department_id on historical categories, services, products, product_or_service_requests and journal_entry_lines';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info('===========================================================');
        $this->info('  CoreHealth v2 - Department Revenue Attribution Backfill  ');
        $this->info('===========================================================');
        if ($isDryRun) {
            $this->warn('DRY RUN MODE: No database records will be modified.');
        }

        // 1. Service Categories Default Mappings
        $scatMap = [
            1 => 6,  // Consultation -> OPD
            2 => 9,  // Investigation -> Laboratory
            3 => 8,  // Bed -> Nursing
            4 => 8,  // Nursing -> Nursing
            5 => 17, // Miscellaneous -> Billing
            6 => 10, // Imaging -> Radiology
            7 => 13, // Registration -> Reception
            8 => 2,  // Procedures -> Surgical
            9 => 36, // Morgue -> Mortuary
        ];

        $pendingScats = DB::table('service_categories')->whereNull('department_id')->count();
        $this->line("Found {$pendingScats} service categories with unassigned department.");
        if (!$isDryRun) {
            foreach ($scatMap as $catId => $deptId) {
                DB::table('service_categories')->where('id', $catId)->whereNull('department_id')->update(['department_id' => $deptId]);
            }
        }

        // 2. Product Categories -> Pharmacy (11)
        $pendingPcats = DB::table('product_categories')->whereNull('department_id')->count();
        $this->line("Found {$pendingPcats} product categories with unassigned department.");
        if (!$isDryRun) {
            DB::table('product_categories')->whereNull('department_id')->update(['department_id' => 11]);
        }

        // 3. Services from category
        $pendingServices = DB::table('services as s')
            ->join('service_categories as sc', 's.category_id', '=', 'sc.id')
            ->whereNull('s.department_id')
            ->whereNotNull('sc.department_id')
            ->count();
        $this->line("Found {$pendingServices} services to backfill from categories.");
        if (!$isDryRun && $pendingServices > 0) {
            DB::update("UPDATE services s JOIN service_categories sc ON s.category_id = sc.id SET s.department_id = sc.department_id WHERE s.department_id IS NULL AND sc.department_id IS NOT NULL");
        }

        // 4. Products from category
        $pendingProducts = DB::table('products as p')
            ->join('product_categories as pc', 'p.category_id', '=', 'pc.id')
            ->whereNull('p.department_id')
            ->whereNotNull('pc.department_id')
            ->count();
        $this->line("Found {$pendingProducts} products to backfill from categories.");
        if (!$isDryRun && $pendingProducts > 0) {
            DB::update("UPDATE products p JOIN product_categories pc ON p.category_id = pc.id SET p.department_id = pc.department_id WHERE p.department_id IS NULL AND pc.department_id IS NOT NULL");
        }

        // 5. POSR from Services
        $pendingPosrServices = DB::table('product_or_service_requests as posr')
            ->join('services as s', 'posr.service_id', '=', 's.id')
            ->whereNull('posr.department_id')
            ->whereNotNull('s.department_id')
            ->count();
        $this->line("Found {$pendingPosrServices} service requests to backfill.");
        if (!$isDryRun && $pendingPosrServices > 0) {
            DB::update("UPDATE product_or_service_requests posr JOIN services s ON posr.service_id = s.id SET posr.department_id = s.department_id WHERE posr.department_id IS NULL AND s.department_id IS NOT NULL");
        }

        // 6. POSR from Products
        $pendingPosrProducts = DB::table('product_or_service_requests as posr')
            ->join('products as p', 'posr.product_id', '=', 'p.id')
            ->whereNull('posr.department_id')
            ->whereNotNull('p.department_id')
            ->count();
        $this->line("Found {$pendingPosrProducts} product requests to backfill.");
        if (!$isDryRun && $pendingPosrProducts > 0) {
            DB::update("UPDATE product_or_service_requests posr JOIN products p ON posr.product_id = p.id SET posr.department_id = p.department_id WHERE posr.department_id IS NULL AND p.department_id IS NOT NULL");
        }

        // 7. Journal Entry Lines for POSR
        $pendingJelPosr = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', function ($join) {
                $join->on('jel.journal_entry_id', '=', 'je.id')
                    ->where('je.reference_type', '=', 'App\\Models\\ProductOrServiceRequest');
            })
            ->join('product_or_service_requests as posr', 'posr.id', '=', 'je.reference_id')
            ->whereNull('jel.department_id')
            ->whereNotNull('posr.department_id')
            ->count();
        $this->line("Found {$pendingJelPosr} journal entry lines (from POSR) to backfill.");
        if (!$isDryRun && $pendingJelPosr > 0) {
            DB::update("UPDATE journal_entry_lines jel JOIN journal_entries je ON jel.journal_entry_id = je.id AND je.reference_type = 'App\\\\Models\\\\ProductOrServiceRequest' JOIN product_or_service_requests posr ON posr.id = je.reference_id SET jel.department_id = posr.department_id WHERE jel.department_id IS NULL AND posr.department_id IS NOT NULL");
        }

        // 8. Journal Entry Lines for Payments
        $pendingJelPayment = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', function ($join) {
                $join->on('jel.journal_entry_id', '=', 'je.id')
                    ->where('je.reference_type', '=', 'App\\Models\\Payment');
            })
            ->join('product_or_service_requests as posr', 'posr.payment_id', '=', 'je.reference_id')
            ->whereNull('jel.department_id')
            ->whereNotNull('posr.department_id')
            ->count();
        $this->line("Found {$pendingJelPayment} journal entry lines (from Payments) to backfill.");
        if (!$isDryRun && $pendingJelPayment > 0) {
            DB::update("UPDATE journal_entry_lines jel JOIN journal_entries je ON jel.journal_entry_id = je.id AND je.reference_type = 'App\\\\Models\\\\Payment' JOIN product_or_service_requests posr ON posr.payment_id = je.reference_id SET jel.department_id = posr.department_id WHERE jel.department_id IS NULL AND posr.department_id IS NOT NULL");
        }

        $this->newLine();
        $this->table(
            ['Entity', 'Pending Count', 'Action Taken'],
            [
                ['Service Categories', $pendingScats, $isDryRun ? 'Previewed' : 'Updated'],
                ['Product Categories', $pendingPcats, $isDryRun ? 'Previewed' : 'Updated'],
                ['Services', $pendingServices, $isDryRun ? 'Previewed' : 'Updated'],
                ['Products', $pendingProducts, $isDryRun ? 'Previewed' : 'Updated'],
                ['ProductOrServiceRequest (Services)', $pendingPosrServices, $isDryRun ? 'Previewed' : 'Updated'],
                ['ProductOrServiceRequest (Products)', $pendingPosrProducts, $isDryRun ? 'Previewed' : 'Updated'],
                ['JournalEntryLines (POSR)', $pendingJelPosr, $isDryRun ? 'Previewed' : 'Updated'],
                ['JournalEntryLines (Payments)', $pendingJelPayment, $isDryRun ? 'Previewed' : 'Updated'],
            ]
        );

        $this->info('Department attribution backfill complete!');

        return 0;
    }
}
