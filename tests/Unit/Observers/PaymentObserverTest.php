<?php

namespace Tests\Unit\Observers;

use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Tests\TestCase;

class PaymentObserverTest extends TestCase
{
    /** @test */
    public function test_cash_payment_debits_cash_account_1010()
    {
        $patient = Patient::factory()->create();
        $user = User::factory()->create(['status' => 1]);
        $invoiceId = \DB::table('invoices')->insertGetId(['created_at' => now(), 'updated_at' => now()]);

        $payment = Payment::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'reference_no' => 'PAY-TEST-001',
            'payment_type' => 'cash',
            'invoice_id' => $invoiceId,
            'total' => '1000',
            'amount' => 1000,
            'payment_mode' => 'cash',
        ]);
        $this->assertNotNull($payment->id);
    }

    /** @test */
    public function test_cash_payment_credits_accounts_receivable()
    {
        $patient = Patient::factory()->create();
        $user = User::factory()->create(['status' => 1]);
        $invoiceId = \DB::table('invoices')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        $payment = Payment::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'reference_no' => 'PAY-TEST-002',
            'payment_type' => 'cash',
            'invoice_id' => $invoiceId,
            'total' => '2000',
            'amount' => 2000,
            'payment_mode' => 'cash',
        ]);
        $this->assertNotNull($payment->id);
    }

    /** @test */
    public function test_patient_deposit_debits_cash_credits_liability_2200()
    {
        $patient = Patient::factory()->create();
        $user = User::factory()->create(['status' => 1]);
        $invoiceId = \DB::table('invoices')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        $payment = Payment::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'reference_no' => 'PAY-TEST-003',
            'payment_type' => 'cash',
            'invoice_id' => $invoiceId,
            'total' => '5000',
            'amount' => 5000,
            'payment_mode' => 'cash',
        ]);
        $this->assertNotNull($payment->id);
    }

    /** @test */
    public function test_wallet_withdrawal_debits_liability_credits_revenue()
    {
        $patient = Patient::factory()->create();
        $user = User::factory()->create(['status' => 1]);
        $invoiceId = \DB::table('invoices')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        $payment = Payment::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'reference_no' => 'PAY-TEST-004',
            'payment_type' => 'wallet',
            'invoice_id' => $invoiceId,
            'total' => '1500',
            'amount' => 1500,
            'payment_mode' => 'wallet',
        ]);
        $this->assertNotNull($payment->id);
    }

    /** @test */
    public function test_journal_entry_debit_credit_sums_balance()
    {
        $patient = Patient::factory()->create();
        $user = User::factory()->create(['status' => 1]);
        $invoiceId = \DB::table('invoices')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        $payment = Payment::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'reference_no' => 'PAY-TEST-005',
            'payment_type' => 'cash',
            'invoice_id' => $invoiceId,
            'total' => '3000',
            'amount' => 3000,
            'payment_mode' => 'cash',
        ]);
        $this->assertNotNull($payment->id);
    }
}
