<?php

namespace App\Observers\Accounting;

use App\Models\PurchaseOrder;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Purchase Order Observer
 *
 * Reference: Accounting System Plan §6.2 - Automated Journal Entries
 *
 * Creates journal entries when PO is received (inventory increase).
 *
 * On PO Received:
 * DEBIT:  Inventory / Stock (Asset)
 * CREDIT: Accounts Payable (Liability)
 */
class PurchaseOrderObserver
{
    /**
     * Handle the PurchaseOrder "updated" event.
     */
    public function updated(PurchaseOrder $po): void
    {
        // Create journal entry when PO is received
        if ($po->isDirty('status') && $po->status === PurchaseOrder::STATUS_RECEIVED) {
            try {
                $this->createPOReceivedJournalEntry($po);
            } catch (\Exception $e) {
                Log::error('Failed to create journal entry for PO receipt', [
                    'po_id' => $po->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Create journal entry for PO goods received.
     */
    protected function createPOReceivedJournalEntry(PurchaseOrder $po): void
    {
        $accountingService = App::make(AccountingService::class);

        // Debit Inventory, Credit Accounts Payable
        $inventoryAccount = Account::where('account_code', '1300')->first(); // Inventory
        $apAccount = Account::where('account_code', '2100')->first(); // Accounts Payable

        if (!$inventoryAccount || !$apAccount) {
            Log::warning('PO journal entry skipped - accounts not configured', [
                'po_id' => $po->id
            ]);
            return;
        }

        $description = "PO Received: {$po->po_number}";
        if ($po->supplier) {
            $description .= " | Supplier: {$po->supplier->name}";
        }

        $lines = [
            [
                'account_id' => $inventoryAccount->id,
                'debit_amount' => $po->total_amount,
                'credit_amount' => 0,
                'description' => 'Inventory received'
            ],
            [
                'account_id' => $apAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $po->total_amount,
                'description' => 'Payable to supplier'
            ]
        ];

        $entry = $accountingService->createJournalEntry([
            'entry_type' => JournalEntry::TYPE_AUTOMATED,
            'source_type' => PurchaseOrder::class,
            'source_id' => $po->id,
            'description' => $description,
            'status' => JournalEntry::STATUS_POSTED,
            'memo' => "PO: {$po->po_number}"
        ], $lines);

        // Update PO with journal entry reference
        $po->journal_entry_id = $entry->id;
        $po->saveQuietly();
    }
}
