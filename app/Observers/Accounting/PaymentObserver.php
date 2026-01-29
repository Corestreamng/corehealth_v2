<?php

namespace App\Observers\Accounting;

use App\Models\payment;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Payment Observer
 *
 * Reference: Accounting System Plan §6.2 - Automated Journal Entries
 *
 * Creates journal entries when payments are received (cash/bank receipts).
 *
 * DEBIT:  Cash / Bank (Asset)
 * CREDIT: Accounts Receivable (Asset) or Revenue (Income)
 */
class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(payment $payment): void
    {
        try {
            $this->createPaymentJournalEntry($payment);
        } catch (\Exception $e) {
            Log::error('Failed to create journal entry for payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create journal entry for payment receipt.
     */
    protected function createPaymentJournalEntry(payment $payment): void
    {
        $accountingService = App::make(AccountingService::class);

        // Determine debit account based on payment method
        $debitAccountCode = $this->getDebitAccountCode($payment);
        $creditAccountCode = $this->getCreditAccountCode($payment);

        $debitAccount = Account::where('account_code', $debitAccountCode)->first();
        $creditAccount = Account::where('account_code', $creditAccountCode)->first();

        if (!$debitAccount || !$creditAccount) {
            Log::warning('Payment journal entry skipped - accounts not configured', [
                'payment_id' => $payment->id,
                'debit_code' => $debitAccountCode,
                'credit_code' => $creditAccountCode
            ]);
            return;
        }

        $description = $this->buildDescription($payment);

        $lines = [
            [
                'account_id' => $debitAccount->id,
                'debit_amount' => $payment->total,
                'credit_amount' => 0,
                'description' => 'Payment received'
            ],
            [
                'account_id' => $creditAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $payment->total,
                'description' => 'Revenue recognized / AR reduced'
            ]
        ];

        $entry = $accountingService->createJournalEntry([
            'entry_type' => JournalEntry::TYPE_AUTOMATED,
            'source_type' => payment::class,
            'source_id' => $payment->id,
            'description' => $description,
            'status' => JournalEntry::STATUS_POSTED, // Auto-post for automated entries
            'memo' => "Ref: {$payment->reference_no}"
        ], $lines);

        // Update payment with journal entry reference
        $payment->journal_entry_id = $entry->id;
        $payment->saveQuietly();
    }

    /**
     * Get debit account code based on payment method.
     */
    protected function getDebitAccountCode(payment $payment): string
    {
        // Map payment methods to account codes
        // These codes should match your chart of accounts
        return match ($payment->payment_method) {
            'cash' => '1010', // Cash in Hand
            'bank_transfer', 'bank', 'transfer' => '1020', // Bank Account
            'card', 'pos' => '1020', // Bank Account (card payments settle to bank)
            'cheque', 'check' => '1025', // Cheques Receivable
            default => '1010' // Default to Cash
        };
    }

    /**
     * Get credit account code based on payment type.
     */
    protected function getCreditAccountCode(payment $payment): string
    {
        // If this is an invoice payment, credit Accounts Receivable
        // Otherwise credit the appropriate revenue account
        if ($payment->invoice_id) {
            return '1200'; // Accounts Receivable
        }

        return match ($payment->payment_type) {
            'consultation' => '4010', // Consultation Revenue
            'pharmacy' => '4020', // Pharmacy Revenue
            'lab', 'laboratory' => '4030', // Laboratory Revenue
            'imaging', 'radiology' => '4040', // Imaging Revenue
            'procedure' => '4050', // Procedure Revenue
            'admission' => '4060', // Admission Revenue
            default => '4000' // General Revenue
        };
    }

    /**
     * Build description for journal entry.
     */
    protected function buildDescription(payment $payment): string
    {
        $parts = ['Payment received'];

        if ($payment->reference_no) {
            $parts[] = "Ref: {$payment->reference_no}";
        }

        if ($payment->patient_id && $payment->patient) {
            $parts[] = "Patient: {$payment->patient->fullname}";
        }

        return implode(' | ', $parts);
    }
}
