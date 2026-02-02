<?php

namespace App\Observers\Accounting;

use App\Models\PurchaseOrderPayment;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubAccount;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\SubAccountService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Purchase Order Payment Observer
 *
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 7.2.3
 *
 * ACCRUAL ACCOUNTING: Creates journal entry when supplier is paid.
 * This offsets the AP that was created when PO was received.
 *
 * On Payment Created:
 * DEBIT:  Accounts Payable (2100) + Supplier Sub-Account
 * CREDIT: Cash/Bank (1010/1020)
 *
 * METADATA CAPTURED:
 * - supplier_id (for supplier-specific reports)
 * - category ('po_payment')
 */
class PurchaseOrderPaymentObserver
{
    protected SubAccountService $subAccountService;

    public function __construct(SubAccountService $subAccountService)
    {
        $this->subAccountService = $subAccountService;
    }

    /**
     * Handle the PurchaseOrderPayment "created" event.
     */
    public function created(PurchaseOrderPayment $payment): void
    {
        try {
            $this->createPaymentJournalEntry($payment);
        } catch (\Exception $e) {
            Log::error('PurchaseOrderPaymentObserver: Failed to create journal entry', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Create the payment journal entry.
     */
    protected function createPaymentJournalEntry(PurchaseOrderPayment $payment): void
    {
        $accountingService = App::make(AccountingService::class);

        $apAccount = Account::where('code', '2100')->first(); // Accounts Payable
        $cashBankAccount = $this->getCashBankAccount($payment);

        if (!$apAccount || !$cashBankAccount) {
            Log::warning('PurchaseOrderPaymentObserver: Skipped - accounts not configured', [
                'payment_id' => $payment->id,
                'ap_account_found' => !is_null($apAccount),
                'cash_bank_found' => !is_null($cashBankAccount),
            ]);
            return;
        }

        $po = $payment->purchaseOrder;
        if (!$po) {
            Log::warning('PurchaseOrderPaymentObserver: Skipped - PO not found', [
                'payment_id' => $payment->id,
                'po_id' => $payment->purchase_order_id,
            ]);
            return;
        }

        // Get or create Supplier sub-account for AP tracking
        $supplierSubAccount = $this->subAccountService->getOrCreateSupplierSubAccount($po->supplier);

        $description = $this->buildDescription($payment, $po);

        // Build lines WITH METADATA
        $lines = [
            [
                'account_id' => $apAccount->id,
                'sub_account_id' => $supplierSubAccount?->id,
                'debit_amount' => $payment->amount,
                'credit_amount' => 0,
                'description' => "AP cleared: PO {$po->po_number} - " . ($po->supplier?->name ?? 'Unknown Supplier'),
                // METADATA
                'supplier_id' => $po->supplier_id,
                'category' => 'po_payment',
            ],
            [
                'account_id' => $cashBankAccount->id,
                'sub_account_id' => null,
                'debit_amount' => 0,
                'credit_amount' => $payment->amount,
                'description' => "Supplier payment via " . ($payment->payment_method ?? 'unknown'),
                // METADATA
                'supplier_id' => $po->supplier_id,
                'category' => 'po_payment',
            ]
        ];

        $entry = $accountingService->createAndPostAutomatedEntry(
            PurchaseOrderPayment::class,
            $payment->id,
            $description,
            $lines
        );

        // Link journal entry back to payment
        $payment->journal_entry_id = $entry->id;
        $payment->saveQuietly();

        Log::info('PurchaseOrderPaymentObserver: Journal entry created', [
            'payment_id' => $payment->id,
            'journal_entry_id' => $entry->id,
            'po_id' => $po->id,
            'supplier_id' => $po->supplier_id,
            'amount' => $payment->amount,
        ]);
    }

    /**
     * Get the cash/bank account for this payment.
     */
    protected function getCashBankAccount(PurchaseOrderPayment $payment): ?Account
    {
        // If specific account_id set, use it
        if ($payment->account_id) {
            return Account::find($payment->account_id);
        }

        // If bank_id set, find its linked account
        if ($payment->bank_id) {
            $bank = $payment->bank;
            if ($bank && $bank->account_id) {
                return Account::find($bank->account_id);
            }
        }

        // Map by payment method
        $code = match ($payment->payment_method ?? 'cash') {
            'cash' => '1010',
            'bank_transfer', 'bank', 'transfer' => '1020',
            'cheque', 'check' => '1020',
            default => '1010'
        };

        return Account::where('code', $code)->first();
    }

    /**
     * Build description for journal entry.
     */
    protected function buildDescription(PurchaseOrderPayment $payment, $po): string
    {
        $parts = [
            "Supplier Payment",
            "PO: " . ($po->po_number ?? 'N/A'),
            "Supplier: " . ($po->supplier?->name ?? 'Unknown'),
            "Amount: " . number_format($payment->amount, 2),
            "Method: " . ($payment->payment_method ?? 'Unknown'),
        ];

        if ($payment->reference_number) {
            $parts[] = "Ref: {$payment->reference_number}";
        }

        return implode(' | ', $parts);
    }
}
