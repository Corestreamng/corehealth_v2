<?php

namespace App\Observers\Accounting;

use App\Models\Payment;
use App\Models\ProductOrServiceRequest;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Payment Observer (UPDATED with Metadata)
 *
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 7.3.2
 *
 * Creates journal entries when payments are received (cash/bank receipts).
 *
 * DEBIT:  Cash / Bank (Asset)
 * CREDIT: Accounts Receivable (Asset) or Revenue (Income)
 *
 * METADATA CAPTURED:
 * - product_id: If payment linked to product (pharmacy)
 * - service_id: If payment linked to service (consultation, lab, etc.)
 * - product_category_id: Product's category
 * - service_category_id: Service's category
 * - hmo_id: If HMO patient payment
 * - patient_id: Always populated
 * - category: Payment type (consultation, pharmacy, lab, etc.)
 */
class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        try {
            $this->createPaymentJournalEntry($payment);
        } catch (\Exception $e) {
            Log::error('PaymentObserver: Failed to create journal entry', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Create journal entry for payment receipt.
     */
    protected function createPaymentJournalEntry(Payment $payment): void
    {
        $accountingService = App::make(AccountingService::class);

        // Determine debit account based on payment method
        $debitAccountCode = $this->getDebitAccountCode($payment);
        $creditAccountCode = $this->getCreditAccountCode($payment);

        $debitAccount = Account::where('code', $debitAccountCode)->first();
        $creditAccount = Account::where('code', $creditAccountCode)->first();

        if (!$debitAccount || !$creditAccount) {
            Log::warning('PaymentObserver: Skipped - accounts not configured', [
                'payment_id' => $payment->id,
                'debit_code' => $debitAccountCode,
                'credit_code' => $creditAccountCode
            ]);
            return;
        }

        // Extract metadata from payment context
        $metadata = $this->extractPaymentMetadata($payment);

        $description = $this->buildDescription($payment);

        $lines = [
            [
                'account_id' => $debitAccount->id,
                'debit_amount' => $payment->total,
                'credit_amount' => 0,
                'description' => $this->buildLineDescription($payment, 'debit'),
                // METADATA
                'product_id' => $metadata['product_id'],
                'service_id' => $metadata['service_id'],
                'product_category_id' => $metadata['product_category_id'],
                'service_category_id' => $metadata['service_category_id'],
                'hmo_id' => $metadata['hmo_id'],
                'patient_id' => $payment->patient_id,
                'category' => $payment->payment_type ?? 'general',
            ],
            [
                'account_id' => $creditAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $payment->total,
                'description' => $this->buildLineDescription($payment, 'credit'),
                // METADATA
                'product_id' => $metadata['product_id'],
                'service_id' => $metadata['service_id'],
                'product_category_id' => $metadata['product_category_id'],
                'service_category_id' => $metadata['service_category_id'],
                'hmo_id' => $metadata['hmo_id'],
                'patient_id' => $payment->patient_id,
                'category' => $payment->payment_type ?? 'general',
            ]
        ];

        $entry = $accountingService->createAndPostAutomatedEntry(
            Payment::class,
            $payment->id,
            $description,
            $lines
        );

        // Update payment with journal entry reference
        $payment->journal_entry_id = $entry->id;
        $payment->saveQuietly();

        Log::info('PaymentObserver: Journal entry created', [
            'payment_id' => $payment->id,
            'journal_entry_id' => $entry->id,
            'amount' => $payment->total,
        ]);
    }

    /**
     * Extract metadata from payment context
     */
    protected function extractPaymentMetadata(Payment $payment): array
    {
        $metadata = [
            'product_id' => null,
            'service_id' => null,
            'product_category_id' => null,
            'service_category_id' => null,
            'hmo_id' => null,
        ];

        // If linked to encounter, get patient's HMO
        if ($payment->encounter_id) {
            $encounter = $payment->encounter;
            if ($encounter && $encounter->patient && $encounter->patient->hmo_id) {
                $metadata['hmo_id'] = $encounter->patient->hmo_id;
            }
        }

        // If linked to product_or_service_requests (many payments are)
        if ($payment->product_or_service_request_id) {
            $psr = ProductOrServiceRequest::find($payment->product_or_service_request_id);
            if ($psr) {
                $metadata['product_id'] = $psr->product_id;
                $metadata['service_id'] = $psr->service_id;
                $metadata['product_category_id'] = $psr->product?->product_category_id ?? $psr->product?->category_id;
                $metadata['service_category_id'] = $psr->service?->service_category_id ?? $psr->service?->category_id;
                if ($psr->hmo_id) {
                    $metadata['hmo_id'] = $psr->hmo_id;
                }
            }
        }

        // Try to get from related items if direct link not available
        if (!$metadata['product_id'] && !$metadata['service_id']) {
            $items = $payment->product_or_service_request()->first();
            if ($items) {
                $metadata['product_id'] = $items->product_id;
                $metadata['service_id'] = $items->service_id;
                $metadata['product_category_id'] = $items->product?->product_category_id ?? $items->product?->category_id;
                $metadata['service_category_id'] = $items->service?->service_category_id ?? $items->service?->category_id;
            }
        }

        return $metadata;
    }

    /**
     * Get debit account code based on payment method.
     */
    protected function getDebitAccountCode(Payment $payment): string
    {
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
    protected function getCreditAccountCode(Payment $payment): string
    {
        // If this is an invoice payment, credit Accounts Receivable
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
    protected function buildDescription(Payment $payment): string
    {
        $parts = [];

        // Main payment info
        $parts[] = "Payment Received: " . ($payment->payment_method ?? 'Unknown');
        $parts[] = "Amount: " . number_format($payment->total, 2);

        if ($payment->reference_no) {
            $parts[] = "Ref: {$payment->reference_no}";
        }

        if ($payment->patient_id && $payment->patient) {
            $parts[] = "Patient: " . ($payment->patient->fullname ?? $payment->patient->full_name ?? 'Unknown') . " (ID: {$payment->patient_id})";
        }

        // Add items/services paid for
        $items = $payment->product_or_service_request()->with(['product', 'service'])->get();
        if ($items->isNotEmpty()) {
            $itemsList = [];
            foreach ($items as $item) {
                if ($item->product) {
                    $itemsList[] = ($item->product->name ?? 'Product') . " (Qty: " . ($item->qty ?? 1) . ", Amt: " . number_format($item->payable_amount ?? 0, 2) . ")";
                } elseif ($item->service) {
                    $itemsList[] = ($item->service->service_name ?? $item->service->name ?? 'Service') . " (Amt: " . number_format($item->payable_amount ?? 0, 2) . ")";
                }
            }

            if (!empty($itemsList)) {
                $itemsText = implode('; ', $itemsList);
                if (strlen($itemsText) > 5000) {
                    $itemsText = substr($itemsText, 0, 4997) . '...';
                }
                $parts[] = "Items: " . $itemsText;
            }
        }

        if ($payment->invoice_id) {
            $parts[] = "Invoice ID: {$payment->invoice_id}";
        }

        return implode(' | ', $parts);
    }

    /**
     * Build description for journal entry line (max 255 chars).
     */
    protected function buildLineDescription(Payment $payment, string $side): string
    {
        if ($side === 'debit') {
            $desc = "Payment via " . ($payment->payment_method ?? 'Unknown');
            if ($payment->bank) {
                $desc .= " - " . ($payment->bank->bank_name ?? $payment->bank->name ?? 'Bank');
            }
        } else {
            $desc = $payment->invoice_id ? 'Reduce Accounts Receivable' : 'Revenue recognized';
            if ($payment->patient) {
                $desc .= " - " . ($payment->patient->fullname ?? $payment->patient->full_name ?? 'Patient');
            }
        }

        return strlen($desc) > 255 ? substr($desc, 0, 252) . '...' : $desc;
    }
}
