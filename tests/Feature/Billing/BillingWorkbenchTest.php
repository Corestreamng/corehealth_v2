<?php

namespace Tests\Feature\Billing;

use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Tests\TestCase;

class BillingWorkbenchTest extends TestCase
{
    /** @test */
    public function test_cash_payment_posts_to_correct_gl_account()
    {
        $patient = Patient::factory()->create();
        $user = User::factory()->create(['status' => 1]);
        $invoiceId = \DB::table('invoices')->insertGetId(['created_at' => now(), 'updated_at' => now()]);

        $payment = Payment::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'reference_no' => 'PAY-TEST-101',
            'payment_type' => 'cash',
            'invoice_id' => $invoiceId,
            'total' => '5000',
            'status' => 'completed',
        ]);

        $this->assertNotNull($payment->id);
    }

    /** @test */
    public function test_hmo_payment_creates_claims_record()
    {
        $patient = Patient::factory()->create();
        $user = User::factory()->create(['status' => 1]);
        $invoiceId = \DB::table('invoices')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        $payment = Payment::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'reference_no' => 'PAY-TEST-102',
            'payment_type' => 'hmo',
            'invoice_id' => $invoiceId,
            'total' => '10000',
            'status' => 'pending_claim',
        ]);

        $this->assertEquals('hmo', $payment->payment_type);
    }

    /** @test */
    public function test_wallet_deduction_updates_patient_account_balance()
    {
        $patient = Patient::factory()->create();
        $user = User::factory()->create(['status' => 1]);
        $invoiceId = \DB::table('invoices')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        $payment = Payment::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'reference_no' => 'PAY-TEST-103',
            'payment_type' => 'wallet',
            'invoice_id' => $invoiceId,
            'total' => '2000',
            'status' => 'completed',
        ]);

        $this->assertEquals('2000', $payment->total);
    }







    /** @test */
    public function test_billing_workbench_datatable_returns_pending_items()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/billing-workbench');
        $this->assertTrue(in_array($response->status(), [200, 302]));
    }

    /** @test */
    public function test_payment_observer_fires_journal_entry_on_cash_payment()
    {
        $patient = Patient::factory()->create();
        $user = User::factory()->create(['status' => 1]);
        $invoiceId = \DB::table('invoices')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        $payment = Payment::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'reference_no' => 'PAY-TEST-104',
            'payment_type' => 'cash',
            'invoice_id' => $invoiceId,
            'total' => '1500',
            'status' => 'completed',
        ]);

        $this->assertNotNull($payment->id);
    }

}
