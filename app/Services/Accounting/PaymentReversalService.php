<?php

namespace App\Services\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\PatientDeposit;
use App\Models\Accounting\PatientDepositApplication;
use App\Models\NursingShift;
use App\Models\OrganizationBill;
use App\Models\PatientAccount;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\PaymentSplit;
use App\Models\ProductOrServiceRequest;
use App\Models\StaffBill;
use App\Observers\Accounting\PaymentObserver;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentReversalService
{
    public function __construct(
        protected AccountingService $accountingService,
        protected PaymentObserver $paymentObserver
    ) {
    }

    /**
     * Reverse the journal entry linked to a payment.
     */
    public function reversePaymentJournalEntry(Payment $payment, int $userId, string $reason): ?JournalEntry
    {
        if (!$payment->journal_entry_id) {
            return null;
        }

        $entry = JournalEntry::find($payment->journal_entry_id);
        if (!$entry) {
            Log::warning('PaymentReversalService: Linked journal entry not found', [
                'payment_id' => $payment->id,
                'journal_entry_id' => $payment->journal_entry_id,
            ]);

            return null;
        }

        if (!$entry->canReverse()) {
            Log::warning('PaymentReversalService: Journal entry cannot be reversed (wrong status or unposted)', [
                'entry_id' => $entry->id,
                'status' => $entry->status,
            ]);

            return null;
        }

        return $this->accountingService->reverseEntry($entry, $userId, $reason);
    }

    /**
     * Adjust the payment method/legs of a payment and maintain complete GL balance.
     *
     * @param  Payment  $payment
     * @param  array  $newPaymentData [is_split, payment_method, bank_id, splits => [...]]
     * @param  int  $userId
     * @param  string  $reason
     * @return PaymentAdjustment
     * @throws Exception
     */
    public function adjustPayment(Payment $payment, array $newPaymentData, int $userId, string $reason): PaymentAdjustment
    {
        if ($payment->voided_at) {
            throw new Exception('Cannot adjust a voided transaction.');
        }

        return DB::transaction(function () use ($payment, $newPaymentData, $userId, $reason) {
            // 1. Snapshot original state
            $originalMethod = $payment->payment_method;
            $originalBankId = $payment->bank_id;
            $originalIsSplit = (bool) $payment->is_split_payment;
            $originalSplits = $payment->splits()->get()->map(function ($s) {
                return [
                    'payment_method' => $s->payment_method,
                    'bank_id' => $s->bank_id,
                    'amount' => (float) $s->amount,
                    'reference_no' => $s->reference_no,
                ];
            })->toArray();

            // 2. Reverse existing Journal Entry
            $reversalEntry = $this->reversePaymentJournalEntry($payment, $userId, "Adjustment reason: {$reason}");

            // 3. Process new method / splits
            $newIsSplit = !empty($newPaymentData['is_split']) || (($newPaymentData['payment_method'] ?? '') === 'SPLIT') || !empty($newPaymentData['splits']);
            $newMethod = $newIsSplit ? 'SPLIT' : ($newPaymentData['payment_method'] ?? 'CASH');
            $newBankId = $newIsSplit ? null : ($newPaymentData['bank_id'] ?? null);
            $newSplitsPayload = $newPaymentData['splits'] ?? [];

            if ($newIsSplit) {
                if (empty($newSplitsPayload)) {
                    throw new Exception('Split payment requires at least one split leg.');
                }
                $sum = collect($newSplitsPayload)->sum(fn ($s) => (float) ($s['amount'] ?? 0));
                if (abs($sum - abs((float) $payment->total)) > 0.01) {
                    throw new Exception("Split total (₦" . number_format($sum, 2) . ") does not equal payment total (₦" . number_format(abs($payment->total), 2) . ").");
                }
            }

            // 4. Update payment record
            $payment->payment_method = $newMethod;
            $payment->bank_id = $newBankId;
            $payment->is_split_payment = $newIsSplit;
            $payment->journal_entry_id = null; // Cleared so new JE can be created
            $payment->saveQuietly();

            // 5. Update splits table
            $payment->splits()->delete();

            $createdSplits = [];
            if ($newIsSplit) {
                foreach ($newSplitsPayload as $sp) {
                    $created = PaymentSplit::create([
                        'payment_id' => $payment->id,
                        'payment_method' => $sp['payment_method'],
                        'bank_id' => $sp['bank_id'] ?? null,
                        'amount' => $sp['amount'],
                        'reference_no' => $sp['reference_no'] ?? null,
                    ]);
                    $createdSplits[] = [
                        'payment_method' => $created->payment_method,
                        'bank_id' => $created->bank_id,
                        'amount' => (float) $created->amount,
                        'reference_no' => $created->reference_no,
                    ];
                }
            }

            // 6. Generate replacement Journal Entry
            $payment->refresh();
            $newEntry = null;
            if (in_array($payment->payment_type, ['ACC_DEPOSIT', 'ACC_WITHDRAW', 'ACC_ADJUSTMENT'])) {
                $newEntry = $this->paymentObserver->createPatientAccountJournalEntry($payment);
            } else {
                $newEntry = $this->paymentObserver->createPaymentJournalEntry($payment);
            }

            // 7. Record adjustment audit trail
            return PaymentAdjustment::create([
                'payment_id' => $payment->id,
                'original_payment_method' => $originalMethod,
                'original_bank_id' => $originalBankId,
                'original_is_split' => $originalIsSplit,
                'original_splits' => $originalSplits,
                'new_payment_method' => $newMethod,
                'new_bank_id' => $newBankId,
                'new_is_split' => $newIsSplit,
                'new_splits' => $createdSplits,
                'reversal_journal_entry_id' => $reversalEntry?->id,
                'new_journal_entry_id' => $newEntry?->id,
                'adjusted_by' => $userId,
                'reason' => $reason,
                'adjusted_at' => now(),
            ]);
        });
    }

    /**
     * Void a transaction with complete reversal across General Ledger,
     * ProductOrServiceRequests, patient accounts, shifts, and bills.
     *
     * @param  Payment  $payment
     * @param  int  $userId
     * @param  string  $reason
     * @return Payment
     * @throws Exception
     */
    public function voidPayment(Payment $payment, int $userId, string $reason): Payment
    {
        if ($payment->voided_at) {
            throw new Exception('Transaction has already been voided.');
        }

        return DB::transaction(function () use ($payment, $userId, $reason) {
            // 1. Reverse linked Journal Entry
            $reversalEntry = $this->reversePaymentJournalEntry($payment, $userId, "Void reason: {$reason}");

            // 2. Unlink ProductOrServiceRequests so items return to unbilled state
            $items = ProductOrServiceRequest::where('payment_id', $payment->id)->get();
            foreach ($items as $item) {
                $item->payment_id = null;
                $item->removed_by = $userId;
                $item->removed_at = now();
                $item->saveQuietly();
            }

            // 3. Reverse patient deposit balance deduction if paid from account
            if ($payment->payment_type === 'ACC_WITHDRAW') {
                $patient = $payment->patient;
                if ($patient) {
                    $account = PatientAccount::where('patient_id', $patient->billing_patient_id)->first();
                    if ($account) {
                        $account->balance += abs((float) $payment->total);
                        $account->save();
                    }
                }
            }

            // 4. Reverse PatientDepositApplications if any
            $applications = PatientDepositApplication::where('payment_id', $payment->id)->get();
            foreach ($applications as $app) {
                $deposit = $app->deposit;
                if ($deposit) {
                    $deposit->utilized_amount = max(0, $deposit->utilized_amount - $app->amount);
                    if ($deposit->balance > 0 && $deposit->status === PatientDeposit::STATUS_FULLY_APPLIED) {
                        $deposit->status = PatientDeposit::STATUS_ACTIVE;
                    }
                    $deposit->save();
                }
                $app->status = PatientDepositApplication::STATUS_REVERSED;
                $app->notes = ($app->notes ? $app->notes . ' | ' : '') . "Reversed on payment void: {$reason}";
                $app->save();
            }

            // 5. Decrement shift counters if tied to an active shift
            if ($payment->shift_id) {
                $shift = NursingShift::find($payment->shift_id);
                if ($shift && $shift->status === 'active') {
                    $shift->decrement('payments_count');
                    $shift->decrement('total_collected', min($shift->total_collected, abs((float) $payment->total)));
                }
            }

            // 6. Handle StaffBill / OrganizationBill
            if ($payment->staffBill) {
                $payment->staffBill->status = 'cancelled';
                $payment->staffBill->notes = ($payment->staffBill->notes ? $payment->staffBill->notes . ' | ' : '') . "Cancelled on payment void: {$reason}";
                $payment->staffBill->save();
            }

            if ($payment->organizationBill) {
                $payment->organizationBill->status = 'cancelled';
                $payment->organizationBill->notes = ($payment->organizationBill->notes ? $payment->organizationBill->notes . ' | ' : '') . "Cancelled on payment void: {$reason}";
                $payment->organizationBill->save();
            }

            // 7. Mark payment voided
            $payment->voided_at = now();
            $payment->voided_by = $userId;
            $payment->void_reason = $reason;
            $payment->void_reversal_je_id = $reversalEntry?->id;
            $payment->saveQuietly();

            Log::info('PaymentReversalService: Payment voided', [
                'payment_id' => $payment->id,
                'voided_by' => $userId,
                'reason' => $reason,
                'reversal_je_id' => $reversalEntry?->id,
            ]);

            return $payment;
        });
    }
}
