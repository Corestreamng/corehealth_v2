<?php

namespace App\Console\Commands;

use App\Models\ProductOrServiceRequest;
use App\Models\Payment;
use App\Models\Patient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettleHmoCoveredItems extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'billing:settle-hmo-covered
                            {--dry-run : Show what would be settled without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Auto-settle fully HMO-covered items (approved, payable=0, claims>0) that are stuck in billing queue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info($dryRun ? '=== DRY RUN ===' : 'Settling fully HMO-covered items...');
        $this->newLine();

        // Find all unsettled, fully HMO-covered items
        $items = ProductOrServiceRequest::whereNull('payment_id')
            ->whereNull('invoice_id')
            ->where('validation_status', 'approved')
            ->where('claims_amount', '>', 0)
            ->where(function ($q) {
                $q->whereNull('payable_amount')
                  ->orWhere('payable_amount', 0);
            })
            ->get();

        if ($items->isEmpty()) {
            $this->info('No unsettled fully HMO-covered items found. Queue is clean.');
            return 0;
        }

        $this->info("Found {$items->count()} item(s) to settle.");
        $this->newLine();

        // Group by user_id so we create one payment per patient
        $grouped = $items->groupBy('user_id');
        $totalSettled = 0;
        $totalPayments = 0;

        foreach ($grouped as $userId => $userItems) {
            $patientId = Patient::where('user_id', $userId)->value('id');
            $patientName = function_exists('userfullname') ? userfullname($userId) : "User #{$userId}";
            $hmoId = $userItems->first()->hmo_id;

            $this->line(sprintf(
                '  %s (user_id=%d): %d item(s), HMO claims total: ₦%s',
                $patientName,
                $userId,
                $userItems->count(),
                number_format($userItems->sum('claims_amount'), 2)
            ));

            if ($dryRun) {
                $totalSettled += $userItems->count();
                $totalPayments++;
                continue;
            }

            try {
                DB::beginTransaction();

                $payment = Payment::create([
                    'payment_type'   => 'HMO_FULL_COVER',
                    'payment_method' => 'HMO_FULL_COVER',
                    'total'          => 0,
                    'total_discount' => 0,
                    'patient_id'     => $patientId,
                    'hmo_id'         => $hmoId,
                    'user_id'        => 1, // System user
                    'reference_no'   => 'HMO-CLEANUP-' . now()->format('YmdHis') . '-' . $userId,
                ]);

                // Update all items in this group without triggering observer
                ProductOrServiceRequest::withoutEvents(function () use ($userItems, $payment) {
                    ProductOrServiceRequest::whereIn('id', $userItems->pluck('id'))
                        ->update(['payment_id' => $payment->id]);
                });

                DB::commit();

                $totalSettled += $userItems->count();
                $totalPayments++;

                Log::info('SettleHmoCoveredItems: Settled batch', [
                    'user_id'     => $userId,
                    'payment_id'  => $payment->id,
                    'item_count'  => $userItems->count(),
                    'item_ids'    => $userItems->pluck('id')->toArray(),
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("  Failed for user_id={$userId}: {$e->getMessage()}");
                Log::error('SettleHmoCoveredItems: Failed to settle batch', [
                    'user_id' => $userId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $prefix = $dryRun ? '[DRY RUN] Would settle' : 'Settled';
        $this->info("{$prefix} {$totalSettled} item(s) across {$totalPayments} payment(s).");

        return 0;
    }
}
