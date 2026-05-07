<?php

namespace App\Observers\Accounting;

use App\Models\PurchaseOrderReturn;
use App\Models\Accounting\Account;
use App\Services\Accounting\AccountingService;
use App\Services\PurchaseOrderService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purchase Order Return Observer
 *
 * Handles stock deduction and journal entry when a PO return is approved.
 *
 * On Approved — JE based on payment status at time of return:
 *
 *   UNPAID PO:
 *     DR: Accounts Payable - Suppliers (2110) — reduces AP liability
 *     CR: Inventory - Medical Supplies (1310) — removes inventory
 *
 *   PAID or PARTIAL PO:
 *     DR: Accounts Receivable (1200) — supplier owes us money back
 *     CR: Inventory - Medical Supplies (1310) — removes inventory
 */
class PurchaseOrderReturnObserver
{
    // Account codes
    private const INVENTORY_STORE  = '1310'; // Inventory - Medical Supplies
    private const AP_SUPPLIERS     = '2110'; // Accounts Payable - Suppliers
    private const AR               = '1200'; // Accounts Receivable (supplier owes us)

    public function updated(PurchaseOrderReturn $return): void
    {
        if (!($return->isDirty('status') && $return->status === 'approved')) {
            return;
        }

        try {
            if ($return->expense_adjusted) {
                Log::info('PurchaseOrderReturnObserver: Already processed, skipping', ['return_id' => $return->id]);
                return;
            }

            DB::transaction(function () use ($return) {
                $this->createJournalEntry($return);
                
                $poService = App::make(PurchaseOrderService::class);
                $poService->returnItems($return);

                $return->update([
                    'stock_deducted'    => true,
                    'stock_deducted_at' => now(),
                ]);
            });

        } catch (\Exception $e) {
            Log::error('PurchaseOrderReturnObserver: Failed', [
                'return_id' => $return->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    protected function createJournalEntry(PurchaseOrderReturn $return): void
    {
        $accountingService = App::make(AccountingService::class);

        // Determine DR account based on payment status at time of return
        $drCode = $this->getDebitAccountCode($return->payment_status_at_return);
        $drAccount        = Account::where('code', $drCode)->first();
        $inventoryAccount = Account::where('code', self::INVENTORY_STORE)->first();

        if (!$drAccount || !$inventoryAccount) {
            Log::warning('PurchaseOrderReturnObserver: Required accounts not found', [
                'return_id'  => $return->id,
                'dr_code'    => $drCode,
                'inv_code'   => self::INVENTORY_STORE,
            ]);
            return;
        }

        $productName = $return->product->product_name ?? 'Product #' . $return->product_id;
        $poNumber    = $return->purchaseOrder->po_number ?? 'PO #' . $return->purchase_order_id;

        $description = sprintf(
            'PO Return %s — %s × %s from %s',
            $return->return_number,
            $return->qty_returned,
            $productName,
            $poNumber
        );

        $lines = [
            [
                'account_id'    => $drAccount->id,
                'debit_amount'  => $return->total_value,
                'credit_amount' => 0,
                'description'   => $drCode === self::AP_SUPPLIERS
                    ? "AP reduced — return to supplier: {$productName}"
                    : "Receivable from supplier for return: {$productName}",
                'product_id'    => $return->product_id,
                'category'      => 'po_return',
            ],
            [
                'account_id'    => $inventoryAccount->id,
                'debit_amount'  => 0,
                'credit_amount' => $return->total_value,
                'description'   => "Inventory removed — PO return {$return->return_number}",
                'product_id'    => $return->product_id,
                'category'      => 'po_return',
            ],
        ];

        $journalEntry = $accountingService->createAndPostAutomatedEntry(
            PurchaseOrderReturn::class,
            $return->id,
            $description,
            $lines,
            now()->toDateString(),
            auth()->id() ?? $return->approved_by ?? 1
        );

        $return->update([
            'journal_entry_id' => $journalEntry->id,
            'expense_adjusted' => true,
            'expense_adjusted_at' => now(),
        ]);

        Log::info('PurchaseOrderReturnObserver: JE created', [
            'return_id'        => $return->id,
            'return_number'    => $return->return_number,
            'journal_entry_id' => $journalEntry->id,
            'total_value'      => $return->total_value,
            'payment_status'   => $return->payment_status_at_return,
            'dr_code'          => $drCode,
        ]);
    }

    private function getDebitAccountCode(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            'paid', 'partial' => self::AR,      // Supplier owes us money back
            default            => self::AP_SUPPLIERS, // Still outstanding, just reduce AP
        };
    }
}
