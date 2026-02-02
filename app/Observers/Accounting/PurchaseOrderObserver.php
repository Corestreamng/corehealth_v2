<?php

namespace App\Observers\Accounting;

use App\Models\PurchaseOrder;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubAccount;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\SubAccountService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Purchase Order Observer (UPDATED with Metadata + Sub-Account)
 *
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 7.3.4
 *
 * Creates journal entries when PO is received (inventory increase).
 *
 * On PO Received:
 * DEBIT:  Inventory / Stock (Asset) - 1300
 * CREDIT: Accounts Payable (Liability) - 2100 with Supplier Sub-Account
 *
 * METADATA CAPTURED:
 * - supplier_id: Always (required for PO)
 * - category: 'purchase_order'
 *
 * SUB-ACCOUNT: Creates/uses supplier sub-account under AP (2100)
 */
class PurchaseOrderObserver
{
    protected SubAccountService $subAccountService;

    public function __construct(SubAccountService $subAccountService)
    {
        $this->subAccountService = $subAccountService;
    }

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
                Log::error('PurchaseOrderObserver: Failed to create journal entry', [
                    'po_id' => $po->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // Create journal entry when PO is partially received
        if ($po->isDirty('status') && $po->status === PurchaseOrder::STATUS_PARTIAL) {
            try {
                $this->createPartialReceivedJournalEntry($po);
            } catch (\Exception $e) {
                Log::error('PurchaseOrderObserver: Failed to create partial JE', [
                    'po_id' => $po->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // Reverse journal entry when PO is cancelled (if it had JE)
        if ($po->isDirty('status') && $po->status === PurchaseOrder::STATUS_CANCELLED) {
            try {
                $this->reversePOJournalEntry($po);
            } catch (\Exception $e) {
                Log::error('PurchaseOrderObserver: Failed to reverse journal entry', [
                    'po_id' => $po->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
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
        $inventoryAccount = Account::where('code', '1300')->first(); // Inventory
        $apAccount = Account::where('code', '2100')->first(); // Accounts Payable

        if (!$inventoryAccount || !$apAccount) {
            Log::warning('PurchaseOrderObserver: Skipped - accounts not configured', [
                'po_id' => $po->id
            ]);
            return;
        }

        // Get or create supplier sub-account for AP
        $supplierSubAccount = null;
        if ($po->supplier_id && $po->supplier) {
            $supplierSubAccount = $this->subAccountService->getOrCreateSupplierSubAccount($po->supplier);
        }

        $description = $this->buildDescription($po);

        $lines = [
            [
                'account_id' => $inventoryAccount->id,
                'debit_amount' => $po->total_amount,
                'credit_amount' => 0,
                'description' => $this->buildLineDescription($po, 'debit'),
                // METADATA
                'supplier_id' => $po->supplier_id,
                'category' => 'purchase_order',
            ],
            [
                'account_id' => $apAccount->id,
                'sub_account_id' => $supplierSubAccount?->id, // Supplier sub-account
                'debit_amount' => 0,
                'credit_amount' => $po->total_amount,
                'description' => $this->buildLineDescription($po, 'credit'),
                // METADATA
                'supplier_id' => $po->supplier_id,
                'category' => 'purchase_order',
            ]
        ];

        $entry = $accountingService->createAndPostAutomatedEntry(
            PurchaseOrder::class,
            $po->id,
            $description,
            $lines
        );

        // Update PO with journal entry reference
        $po->journal_entry_id = $entry->id;
        $po->saveQuietly();

        Log::info('PurchaseOrderObserver: Journal entry created', [
            'po_id' => $po->id,
            'po_number' => $po->po_number,
            'journal_entry_id' => $entry->id,
            'amount' => $po->total_amount,
            'supplier_id' => $po->supplier_id,
        ]);
    }

    /**
     * Build description for journal entry.
     */
    protected function buildDescription(PurchaseOrder $po): string
    {
        $parts = [
            "Purchase Order Received: " . ($po->po_number ?? 'N/A'),
            "Total Amount: " . number_format($po->total_amount, 2)
        ];

        if ($po->supplier) {
            $parts[] = "Supplier: " . ($po->supplier->name ?? 'Unknown');
        }

        if ($po->expected_date) {
            $parts[] = "Expected Date: " . $po->expected_date->format('Y-m-d');
        }

        // Load items with product details
        $items = $po->items()->with('product')->get();
        if ($items->isNotEmpty()) {
            $itemsList = [];
            foreach ($items as $item) {
                if ($item->product) {
                    $productName = $item->product->name ?? 'Unknown Product';
                    $itemsList[] = "{$productName} (Ordered: {$item->ordered_qty}, Received: {$item->received_qty}, Cost: " . number_format($item->actual_unit_cost ?? $item->unit_cost, 2) . ")";
                }
            }

            if (!empty($itemsList)) {
                $itemsText = implode('; ', $itemsList);
                // TEXT field supports large content, but keep reasonable
                if (strlen($itemsText) > 10000) {
                    $itemsText = substr($itemsText, 0, 9997) . '...';
                }
                $parts[] = "Items Received: " . $itemsText;
            }
        }

        if ($po->notes) {
            $notes = strip_tags($po->notes);
            if (strlen($notes) > 500) {
                $notes = substr($notes, 0, 497) . '...';
            }
            $parts[] = "Notes: {$notes}";
        }

        return implode(' | ', $parts);
    }

    /**
     * Build description for journal entry line (max 255 chars).
     */
    protected function buildLineDescription(PurchaseOrder $po, string $side): string
    {
        if ($side === 'debit') {
            $itemCount = $po->items()->count();
            $desc = "Inventory received: {$itemCount} item type(s) from PO " . ($po->po_number ?? 'N/A');
        } else {
            $desc = "Accounts Payable";
            if ($po->supplier) {
                $desc .= " - " . ($po->supplier->name ?? 'Supplier');
            }
            $desc .= " (PO: " . ($po->po_number ?? 'N/A') . ")";
        }

        // Truncate to 255 chars
        return strlen($desc) > 255 ? substr($desc, 0, 252) . '...' : $desc;
    }

    /**
     * Create journal entry for partial PO goods received.
     * Only records the incremental amount since last JE.
     */
    protected function createPartialReceivedJournalEntry(PurchaseOrder $po): void
    {
        $accountingService = App::make(AccountingService::class);

        // Calculate amount received this time (sum of received_qty * actual_unit_cost)
        $receivedAmount = $po->items->sum(function ($item) {
            return $item->received_qty * ($item->actual_unit_cost ?? $item->unit_cost);
        });

        // Get previous JE amount for this PO (if any partial entries exist)
        $previouslyRecorded = JournalEntry::where('reference_type', PurchaseOrder::class)
            ->where('reference_id', $po->id)
            ->whereNotIn('status', [JournalEntry::STATUS_REVERSED])
            ->get()
            ->sum(function ($je) {
                return $je->lines()->where('debit_amount', '>', 0)->sum('debit_amount');
            });

        $incrementalAmount = $receivedAmount - $previouslyRecorded;

        if ($incrementalAmount <= 0) {
            Log::info('PurchaseOrderObserver: No incremental amount to record', [
                'po_id' => $po->id,
                'received_amount' => $receivedAmount,
                'previously_recorded' => $previouslyRecorded,
            ]);
            return;
        }

        $inventoryAccount = Account::where('code', '1300')->first();
        $apAccount = Account::where('code', '2100')->first();

        if (!$inventoryAccount || !$apAccount) {
            Log::warning('PurchaseOrderObserver: Partial JE skipped - accounts not configured');
            return;
        }

        $supplierSubAccount = null;
        if ($po->supplier_id && $po->supplier) {
            $supplierSubAccount = $this->subAccountService->getOrCreateSupplierSubAccount($po->supplier);
        }

        $lines = [
            [
                'account_id' => $inventoryAccount->id,
                'debit_amount' => $incrementalAmount,
                'credit_amount' => 0,
                'description' => "Partial inventory received: PO " . ($po->po_number ?? 'N/A'),
                'supplier_id' => $po->supplier_id,
                'category' => 'purchase_order_partial',
            ],
            [
                'account_id' => $apAccount->id,
                'sub_account_id' => $supplierSubAccount?->id,
                'debit_amount' => 0,
                'credit_amount' => $incrementalAmount,
                'description' => "Partial AP: " . ($po->supplier->name ?? 'Supplier') . " (PO: " . ($po->po_number ?? 'N/A') . ")",
                'supplier_id' => $po->supplier_id,
                'category' => 'purchase_order_partial',
            ]
        ];

        $entry = $accountingService->createAndPostAutomatedEntry(
            PurchaseOrder::class,
            $po->id,
            "Partial PO Received: {$po->po_number} | Incremental: " . number_format($incrementalAmount, 2),
            $lines
        );

        $po->journal_entry_id = $entry->id;
        $po->saveQuietly();

        Log::info('PurchaseOrderObserver: Partial JE created', [
            'po_id' => $po->id,
            'journal_entry_id' => $entry->id,
            'incremental_amount' => $incrementalAmount,
        ]);
    }

    /**
     * Reverse journal entry when PO is cancelled.
     */
    protected function reversePOJournalEntry(PurchaseOrder $po): void
    {
        // Find all JEs for this PO that haven't been reversed
        $journalEntries = JournalEntry::where('reference_type', PurchaseOrder::class)
            ->where('reference_id', $po->id)
            ->whereNotIn('status', [JournalEntry::STATUS_REVERSED])
            ->get();

        if ($journalEntries->isEmpty()) {
            Log::info('PurchaseOrderObserver: No journal entries to reverse', [
                'po_id' => $po->id,
            ]);
            return;
        }

        $accountingService = App::make(AccountingService::class);

        foreach ($journalEntries as $journalEntry) {
            if (!$journalEntry->canReverse()) {
                Log::warning('PurchaseOrderObserver: JE cannot be reversed', [
                    'po_id' => $po->id,
                    'journal_entry_id' => $journalEntry->id,
                    'je_status' => $journalEntry->status,
                ]);
                continue;
            }

            $reason = "PO Cancelled: " . ($po->po_number ?? 'N/A');
            if ($po->cancellation_reason) {
                $reason .= " - Reason: " . $po->cancellation_reason;
            }

            $reversalEntry = $accountingService->reverseEntry(
                $journalEntry,
                auth()->id() ?? 1,
                $reason
            );

            Log::info('PurchaseOrderObserver: JE reversed for cancelled PO', [
                'po_id' => $po->id,
                'original_je_id' => $journalEntry->id,
                'reversal_je_id' => $reversalEntry->id,
            ]);
        }
    }
}
