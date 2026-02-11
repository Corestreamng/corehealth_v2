<?php

namespace App\Observers\Accounting;

use App\Models\PharmacyReturn;
use App\Models\ProductOrServiceRequest;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Pharmacy Return Observer
 *
 * Creates journal entries when pharmacy returns are approved and refunded.
 *
 * IMPORTANT: This REVERSES the original pharmacy sale transaction.
 *
 * Original Sale (from PaymentObserver):
 *   DR: Cash/Bank (1010/1020)
 *   CR: Revenue - Pharmacy (4020)
 *
 * On Return Approved & Refunded:
 *   If Restockable (good condition):
 *     DR: Inventory - Pharmacy (1300) - [refund_amount]
 *     CR: Cash/Bank (1010/1020) - [refund_amount]
 *
 *   If Not Restockable (damaged/expired):
 *     DR: Loss on Returns (5060) - [refund_amount]
 *     CR: Cash/Bank (1010/1020) - [refund_amount]
 *
 * For HMO Split Returns:
 *   DR: Inventory/Loss (based on condition)
 *   CR: Cash (Patient Refund) - [refund_to_patient]
 *   CR: AR-HMO (Reverse HMO claim) - [refund_to_hmo]
 *
 * DUPLICATE JE PREVENTION:
 * - Check if JE already exists for this return before creating new one
 * - Store journal_entry_id in pharmacy_returns table
 * - Skip if status changes back to pending after approval
 */
class PharmacyReturnObserver
{
    // Account codes
    private const CASH_ACCOUNT = '1010';
    private const BANK_ACCOUNT = '1020';
    private const INVENTORY_PHARMACY = '1300';
    private const AR_HMO = '1110';
    private const LOSS_ON_RETURNS = '5060';
    private const PATIENT_DEPOSITS_LIABILITY = '2200';

    /**
     * Handle the PharmacyReturn "updated" event.
     */
    public function updated(PharmacyReturn $return): void
    {
        // Only process when status changes to 'approved'
        if ($return->isDirty('status') && $return->status === 'approved') {
            try {
                // DUPLICATE CHECK: Skip if JE already exists
                if ($return->journal_entry_id) {
                    Log::info('PharmacyReturnObserver: Journal entry already exists, skipping', [
                        'return_id' => $return->id,
                        'existing_je_id' => $return->journal_entry_id,
                    ]);
                    return;
                }

                $this->createReturnJournalEntry($return);
            } catch (\Exception $e) {
                Log::error('PharmacyReturnObserver: Failed to create journal entry', [
                    'return_id' => $return->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Create the return refund journal entry.
     */
    protected function createReturnJournalEntry(PharmacyReturn $return): void
    {
        $accountingService = App::make(AccountingService::class);

        // Get accounts based on return condition
        $debitAccount = $return->restock
            ? Account::where('code', self::INVENTORY_PHARMACY)->first()
            : Account::where('code', self::LOSS_ON_RETURNS)->first();

        if (!$debitAccount) {
            Log::warning('PharmacyReturnObserver: Debit account not found', [
                'return_id' => $return->id,
                'restock' => $return->restock,
                'expected_account' => $return->restock ? self::INVENTORY_PHARMACY : self::LOSS_ON_RETURNS,
            ]);
            return;
        }

        $description = $this->buildDescription($return);
        $lines = [];

        // Refund goes to patient wallet → credit Customer Deposits (2200)
        // This keeps it consistent with the ACC_DEPOSIT system and shows correctly
        // in Aged Payables (hospital owes the patient).
        $patientLiabilityAccount = Account::where('code', self::PATIENT_DEPOSITS_LIABILITY)->first();

        if (!$patientLiabilityAccount) {
            Log::warning('PharmacyReturnObserver: Customer Deposits account (2200) not found', [
                'return_id' => $return->id,
            ]);
            return;
        }

        // Handle HMO split returns vs regular returns
        if ($return->refund_to_hmo > 0) {
            // HMO Split Return
            $arHmoAccount = Account::where('code', self::AR_HMO)->first();

            if (!$arHmoAccount) {
                Log::warning('PharmacyReturnObserver: AR-HMO account (1110) not found', [
                    'return_id' => $return->id,
                ]);
                return;
            }

            $lines = [
                [
                    'account_id' => $debitAccount->id,
                    'debit_amount' => $return->refund_amount,
                    'credit_amount' => 0,
                    'description' => $return->restock
                        ? "Restocked return: {$return->product->product_name}"
                        : "Non-restockable return loss: {$return->product->product_name}",
                    'product_id' => $return->product_id,
                    'patient_id' => $return->patient_id,
                    'category' => 'pharmacy_return',
                ],
                [
                    'account_id' => $patientLiabilityAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $return->refund_to_patient,
                    'description' => "Patient wallet refund: " . ($return->patient->user->name ?? 'Unknown'),
                    'product_id' => $return->product_id,
                    'patient_id' => $return->patient_id,
                    'category' => 'pharmacy_return',
                ],
                [
                    'account_id' => $arHmoAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $return->refund_to_hmo,
                    'description' => "HMO claim reversal: {$return->billRequest->hmo->name}",
                    'product_id' => $return->product_id,
                    'patient_id' => $return->patient_id,
                    'hmo_id' => $return->billRequest->hmo_id,
                    'category' => 'pharmacy_return',
                ],
            ];
        } else {
            // Regular Return (no HMO) — full refund to patient wallet
            $lines = [
                [
                    'account_id' => $debitAccount->id,
                    'debit_amount' => $return->refund_amount,
                    'credit_amount' => 0,
                    'description' => $return->restock
                        ? "Restocked return: {$return->product->product_name}"
                        : "Non-restockable return loss: {$return->product->product_name}",
                    'product_id' => $return->product_id,
                    'patient_id' => $return->patient_id,
                    'category' => 'pharmacy_return',
                ],
                [
                    'account_id' => $patientLiabilityAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $return->refund_amount,
                    'description' => "Patient wallet refund: " . ($return->patient->user->name ?? 'Unknown'),
                    'product_id' => $return->product_id,
                    'patient_id' => $return->patient_id,
                    'category' => 'pharmacy_return',
                ],
            ];
        }

        // Create the journal entry
        $journalEntry = $accountingService->createAndPostAutomatedEntry(
            PharmacyReturn::class,
            $return->id,
            $description,
            $lines,
            now()->toDateString(),
            auth()->id() ?? $return->approved_by ?? 1
        );

        // Store JE reference to prevent duplicates
        $return->update(['journal_entry_id' => $journalEntry->id]);

        Log::info('PharmacyReturnObserver: Journal entry created', [
            'return_id' => $return->id,
            'journal_entry_id' => $journalEntry->id,
            'refund_amount' => $return->refund_amount,
            'restock' => $return->restock,
            'hmo_split' => $return->refund_to_hmo > 0,
        ]);
    }

    /**
     * Build the journal entry description.
     */
    protected function buildDescription(PharmacyReturn $return): string
    {
        $productName = $return->product->product_name ?? 'Unknown Product';
        $patientName = $return->patient->user->name ?? 'Unknown Patient';
        $condition = $return->restock ? 'Restockable' : 'Non-Restockable';

        return "Pharmacy Return ({$condition}) | Return #{$return->id} | Product: {$productName} | Patient: {$patientName} | Qty: {$return->qty_returned}";
    }
}
