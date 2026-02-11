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
 * Payment Observer (UPDATED with Patient Account Support)
 *
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 7.3.2
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.9
 *
 * Creates journal entries when payments are received (cash/bank receipts).
 *
 * STANDARD PAYMENTS:
 *   DEBIT:  Cash / Bank (Asset)
 *   CREDIT: Accounts Receivable (Asset) or Revenue (Income)
 *
 * PATIENT ACCOUNT TRANSACTIONS:
 *   ACC_DEPOSIT (positive): Patient deposits into account
 *     DEBIT:  Cash/Bank (1010/1020)
 *     CREDIT: Patient Deposits Liability (2200)
 *
 *   ACC_WITHDRAW (negative): Payment from account OR refund withdrawal
 *     When paying for services (linked to product_or_service_request):
 *       DEBIT:  Patient Deposits Liability (2200)
 *       CREDIT: Revenue (4xxx)
 *     When manual refund/withdrawal:
 *       DEBIT:  Patient Deposits Liability (2200)
 *       CREDIT: Cash/Bank (1010/1020)
 *
 *   ACC_ADJUSTMENT: Balance corrections
 *     Positive: DEBIT Cash Overage, CREDIT Patient Deposits Liability
 *     Negative: DEBIT Patient Deposits Liability, CREDIT Cash Shortage
 *
 * This ties PatientAccount balance to the GL and ensures:
 * - Positive PatientAccount balance appears in Aged Payables (hospital liability)
 * - Negative PatientAccount balance appears in Aged Receivables (patient owes)
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
    // Account codes
    private const CASH_ACCOUNT = '1010';
    private const BANK_ACCOUNT = '1020';
    private const PATIENT_DEPOSITS_LIABILITY = '2200';
    private const CASH_OVERAGE = '4900';  // Miscellaneous income
    private const CASH_SHORTAGE = '5900'; // Miscellaneous expense

    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        try {
            // GUARD: Skip if JE already linked (e.g. pharmacy return observer already created the JE)
            if ($payment->journal_entry_id) {
                Log::info('PaymentObserver: Skipping JE - already linked', [
                    'payment_id' => $payment->id,
                    'journal_entry_id' => $payment->journal_entry_id,
                ]);
                return;
            }

            // Route to appropriate handler based on payment type
            if (in_array($payment->payment_type, ['ACC_DEPOSIT', 'ACC_WITHDRAW', 'ACC_ADJUSTMENT'])) {
                // UNIFIED SYSTEM CHECK: Skip JE if a PatientDeposit was created for this payment
                // The PatientDepositObserver will create the JE instead
                if ($payment->payment_type === 'ACC_DEPOSIT') {
                    $linkedDeposit = \App\Models\Accounting\PatientDeposit::where('source_payment_id', $payment->id)->exists();
                    if ($linkedDeposit) {
                        Log::info('PaymentObserver: Skipping JE for ACC_DEPOSIT - PatientDeposit exists', [
                            'payment_id' => $payment->id,
                        ]);
                        return;
                    }
                }
                $this->createPatientAccountJournalEntry($payment);
            } else {
                $this->createPaymentJournalEntry($payment);
            }
        } catch (\Exception $e) {
            Log::error('PaymentObserver: Failed to create journal entry', [
                'payment_id' => $payment->id,
                'payment_type' => $payment->payment_type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Create journal entry for patient account transactions (ACC_DEPOSIT, ACC_WITHDRAW, ACC_ADJUSTMENT).
     * This integrates the PatientAccount system with the GL.
     */
    protected function createPatientAccountJournalEntry(Payment $payment): void
    {
        $accountingService = App::make(AccountingService::class);

        // Get patient deposits liability account
        $liabilityAccount = Account::where('code', self::PATIENT_DEPOSITS_LIABILITY)->first();
        if (!$liabilityAccount) {
            Log::warning('PaymentObserver: Patient Deposits Liability account (2200) not found', [
                'payment_id' => $payment->id,
            ]);
            return;
        }

        $amount = abs($payment->total);
        $patientName = $payment->patient?->full_name ?? $payment->patient?->fullname ?? 'Unknown';
        $lines = [];
        $description = '';

        switch ($payment->payment_type) {
            case 'ACC_DEPOSIT':
                // Patient deposits money into their account
                // DEBIT: Cash/Bank, CREDIT: Patient Deposits Liability
                $cashAccount = $this->getCashBankAccount($payment);
                if (!$cashAccount) return;

                $description = "Patient account deposit - {$patientName} | Ref: {$payment->reference_no}";
                $lines = [
                    [
                        'account_id' => $cashAccount->id,
                        'debit_amount' => $amount,
                        'credit_amount' => 0,
                        'description' => "Deposit received from patient: {$patientName}",
                        'patient_id' => $payment->patient_id,
                        'category' => 'patient_deposit',
                    ],
                    [
                        'account_id' => $liabilityAccount->id,
                        'debit_amount' => 0,
                        'credit_amount' => $amount,
                        'description' => "Patient deposit liability: {$patientName}",
                        'patient_id' => $payment->patient_id,
                        'category' => 'patient_deposit',
                    ],
                ];
                break;

            case 'ACC_WITHDRAW':
                // Withdrawal from patient account (negative total in payment record)
                // Check if this is a payment for services or a manual withdrawal/refund
                $hasServiceItems = $payment->product_or_service_request()->exists();

                if ($hasServiceItems) {
                    // Payment from account for services
                    // DEBIT: Patient Deposits Liability, CREDIT: Revenue
                    $metadata = $this->extractPaymentMetadata($payment);
                    $revenueAccountCode = $this->getRevenueAccountCode($payment);
                    $revenueAccount = Account::where('code', $revenueAccountCode)->first();

                    if (!$revenueAccount) {
                        $revenueAccount = Account::where('code', '4000')->first(); // Default revenue
                    }

                    if (!$revenueAccount) {
                        Log::warning('PaymentObserver: Revenue account not found for ACC_WITHDRAW', [
                            'payment_id' => $payment->id,
                        ]);
                        return;
                    }

                    $description = "Account payment for services - {$patientName} | Ref: {$payment->reference_no}";
                    $lines = [
                        [
                            'account_id' => $liabilityAccount->id,
                            'debit_amount' => $amount,
                            'credit_amount' => 0,
                            'description' => "Reduce patient deposit: {$patientName}",
                            'patient_id' => $payment->patient_id,
                            'category' => 'account_payment',
                        ],
                        [
                            'account_id' => $revenueAccount->id,
                            'debit_amount' => 0,
                            'credit_amount' => $amount,
                            'description' => "Revenue from patient account",
                            'patient_id' => $payment->patient_id,
                            'product_id' => $metadata['product_id'],
                            'service_id' => $metadata['service_id'],
                            'product_category_id' => $metadata['product_category_id'],
                            'service_category_id' => $metadata['service_category_id'],
                            'hmo_id' => $metadata['hmo_id'],
                            'category' => 'account_payment',
                        ],
                    ];
                } else {
                    // Manual withdrawal/refund - cash out to patient
                    // DEBIT: Patient Deposits Liability, CREDIT: Cash/Bank
                    $cashAccount = $this->getCashBankAccount($payment);
                    if (!$cashAccount) return;

                    $description = "Patient account withdrawal - {$patientName} | Ref: {$payment->reference_no}";
                    $lines = [
                        [
                            'account_id' => $liabilityAccount->id,
                            'debit_amount' => $amount,
                            'credit_amount' => 0,
                            'description' => "Reduce patient deposit: {$patientName}",
                            'patient_id' => $payment->patient_id,
                            'category' => 'patient_withdrawal',
                        ],
                        [
                            'account_id' => $cashAccount->id,
                            'debit_amount' => 0,
                            'credit_amount' => $amount,
                            'description' => "Refund/withdrawal to patient: {$patientName}",
                            'patient_id' => $payment->patient_id,
                            'category' => 'patient_withdrawal',
                        ],
                    ];
                }
                break;

            case 'ACC_ADJUSTMENT':
                // Balance adjustments (can be positive or negative)
                $isPositive = $payment->total > 0;

                if ($isPositive) {
                    // Positive adjustment - increase patient balance (found money, correction)
                    // DEBIT: Cash Overage/Suspense, CREDIT: Patient Deposits Liability
                    $overageAccount = Account::where('code', self::CASH_OVERAGE)->first()
                        ?? Account::where('code', '4000')->first();

                    if (!$overageAccount) return;

                    $description = "Patient account adjustment (+) - {$patientName} | Ref: {$payment->reference_no}";
                    $lines = [
                        [
                            'account_id' => $overageAccount->id,
                            'debit_amount' => $amount,
                            'credit_amount' => 0,
                            'description' => "Adjustment to patient account: {$patientName}",
                            'patient_id' => $payment->patient_id,
                            'category' => 'account_adjustment',
                        ],
                        [
                            'account_id' => $liabilityAccount->id,
                            'debit_amount' => 0,
                            'credit_amount' => $amount,
                            'description' => "Increase patient deposit: {$patientName}",
                            'patient_id' => $payment->patient_id,
                            'category' => 'account_adjustment',
                        ],
                    ];
                } else {
                    // Negative adjustment - decrease patient balance (write-off, correction)
                    // DEBIT: Patient Deposits Liability, CREDIT: Cash Shortage/Suspense
                    $shortageAccount = Account::where('code', self::CASH_SHORTAGE)->first()
                        ?? Account::where('code', '5000')->first();

                    if (!$shortageAccount) return;

                    $description = "Patient account adjustment (-) - {$patientName} | Ref: {$payment->reference_no}";
                    $lines = [
                        [
                            'account_id' => $liabilityAccount->id,
                            'debit_amount' => $amount,
                            'credit_amount' => 0,
                            'description' => "Reduce patient deposit: {$patientName}",
                            'patient_id' => $payment->patient_id,
                            'category' => 'account_adjustment',
                        ],
                        [
                            'account_id' => $shortageAccount->id,
                            'debit_amount' => 0,
                            'credit_amount' => $amount,
                            'description' => "Write-off/adjustment: {$patientName}",
                            'patient_id' => $payment->patient_id,
                            'category' => 'account_adjustment',
                        ],
                    ];
                }
                break;

            default:
                return;
        }

        if (empty($lines)) return;

        $entry = $accountingService->createAndPostAutomatedEntry(
            Payment::class,
            $payment->id,
            $description,
            $lines
        );

        // Update payment with journal entry reference
        $payment->journal_entry_id = $entry->id;
        $payment->saveQuietly();

        Log::info('PaymentObserver: Patient account JE created', [
            'payment_id' => $payment->id,
            'payment_type' => $payment->payment_type,
            'journal_entry_id' => $entry->id,
            'amount' => $payment->total,
        ]);
    }

    /**
     * Get Cash or Bank account based on payment method.
     * Uses specific bank's GL account if bank_id is set.
     */
    protected function getCashBankAccount(Payment $payment): ?Account
    {
        // If payment has a specific account_id set, use it directly
        if ($payment->account_id) {
            $account = Account::find($payment->account_id);
            if ($account) return $account;
        }

        // If bank_id is set, use that bank's GL account
        if ($payment->bank_id) {
            $bank = $payment->bank;
            if ($bank && $bank->account_id) {
                $account = Account::find($bank->account_id);
                if ($account) {
                    Log::info('PaymentObserver: Using bank-specific GL account', [
                        'payment_id' => $payment->id,
                        'bank_id' => $bank->id,
                        'bank_name' => $bank->name,
                        'account_id' => $account->id,
                        'account_code' => $account->code,
                    ]);
                    return $account;
                }
            }
        }

        // Fallback to generic cash/bank account codes
        $code = match (strtolower($payment->payment_method ?? 'cash')) {
            'cash' => self::CASH_ACCOUNT,
            'pos', 'transfer', 'bank', 'bank_transfer', 'card' => self::BANK_ACCOUNT,
            default => self::CASH_ACCOUNT,
        };

        $account = Account::where('code', $code)->first();

        if (!$account) {
            Log::warning('PaymentObserver: Cash/Bank account not found', [
                'payment_id' => $payment->id,
                'code' => $code,
            ]);
        }

        return $account;
    }

    /**
     * Get revenue account code based on linked services/products.
     */
    protected function getRevenueAccountCode(Payment $payment): string
    {
        // Check linked items to determine revenue type
        $item = $payment->product_or_service_request()->with(['service', 'product'])->first();

        if ($item) {
            if ($item->service) {
                $categoryName = strtolower($item->service->category?->category_name ?? '');
                if (str_contains($categoryName, 'consult')) return '4010';
                if (str_contains($categoryName, 'lab')) return '4030';
                if (str_contains($categoryName, 'imag') || str_contains($categoryName, 'radio')) return '4040';
                if (str_contains($categoryName, 'procedure')) return '4050';
                if (str_contains($categoryName, 'admission') || str_contains($categoryName, 'ward')) return '4060';
            }
            if ($item->product) {
                return '4020'; // Pharmacy revenue
            }
        }

        return '4000'; // General revenue
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
     * Get debit account based on payment method.
     * Uses specific bank's GL account if bank_id is set.
     */
    protected function getDebitAccountCode(Payment $payment): string
    {
        // For cheques, always use Cheques Receivable
        if (in_array(strtolower($payment->payment_method ?? ''), ['cheque', 'check'])) {
            return '1025'; // Cheques Receivable
        }

        // If bank_id is set, use that bank's GL account
        if ($payment->bank_id) {
            $bank = $payment->bank;
            if ($bank && $bank->account_id) {
                $account = Account::find($bank->account_id);
                if ($account) {
                    return $account->code;
                }
            }
        }

        // Fallback to generic account codes
        return match (strtolower($payment->payment_method ?? 'cash')) {
            'cash' => '1010', // Cash in Hand
            'bank_transfer', 'bank', 'transfer' => '1020', // Bank Account
            'card', 'pos' => '1020', // Bank Account (card payments settle to bank)
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
