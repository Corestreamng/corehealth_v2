<?php

namespace App\Console\Commands;

use App\Models\BillingQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncBillingQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:sync-queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the materialized billing queues table from existing product or service requests';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting billing queue synchronization...');

        $this->info('Populating billing_queues via mass insert...');
        BillingQueue::truncate();

        $sql = "
            INSERT INTO billing_queues (user_id, patient_id, unpaid_items_count, hmo_items_count, is_emergency, latest_item_at, created_at, updated_at)
            SELECT 
                p.user_id,
                MAX(p.patient_id) as patient_id,
                COUNT(*) as unpaid_items_count,
                SUM(CASE WHEN p.claims_amount > 0 THEN 1 ELSE 0 END) as hmo_items_count,
                0 as is_emergency,
                MAX(p.created_at) as latest_item_at,
                NOW(),
                NOW()
            FROM product_or_service_requests p
            INNER JOIN users u ON u.id = p.user_id
            WHERE p.payment_id IS NULL 
              AND p.invoice_id IS NULL 
              AND NOT ((p.payable_amount IS NULL OR p.payable_amount = 0) AND (p.claims_amount > 0 AND p.validation_status = 'approved'))
            GROUP BY p.user_id
        ";

        DB::statement($sql);

        // Optional: Update emergency status for the new queues (can be slightly slower but manageable)
        $this->info('Updating emergency status flags...');

        // Find emergency patient IDs
        $emergencyPatientIds = DB::table('doctor_queues')
            ->where('priority', 'emergency')
            ->whereIn('status', [1, 2, 3])
            ->pluck('patient_id')
            ->merge(
                DB::table('admission_requests')
                ->where('priority', 'emergency')
                ->where('discharged', 0)
                ->pluck('patient_id')
            )
            ->unique()
            ->filter()
            ->toArray();

        if (!empty($emergencyPatientIds)) {
            BillingQueue::whereIn('patient_id', $emergencyPatientIds)->update(['is_emergency' => true]);
        }

        $this->info("\nBilling queue synchronized successfully. Total entries: " . BillingQueue::count());

        return 0;
    }
}
