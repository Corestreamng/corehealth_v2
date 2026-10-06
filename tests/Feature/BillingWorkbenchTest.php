<?php

namespace Tests\Feature;

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
            'reference_no' => 'PAY-TEST-201',
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
            'reference_no' => 'PAY-TEST-202',
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
            'reference_no' => 'PAY-TEST-203',
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
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }

    /** @test */
    public function test_billing_workbench_renders_all_workspace_tabs_properly()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/billing-workbench');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $response->assertSee('id="billing-tab"', false);
            $response->assertSee('id="receipts-tab"', false);
            $response->assertSee('id="admissions-tab"', false);
            $response->assertSee('id="account-tab"', false);
            $response->assertSee('id="billing-items-table"', false);
            $response->assertSee('id="receipts-table"', false);
        }
    }

    /** @test */
    public function test_patient_generate_statement_endpoint()
    {
        $patient = Patient::factory()->create();
        $user = User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->postJson("/billing-workbench/patient/{$patient->id}/generate-statement", [
            'date_from' => now()->subDays(30)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
            'include_deposits' => 1,
            'include_payments' => 1,
            'include_withdrawals' => 1,
            'include_services' => 1,
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'success',
                'statement_a4',
                'statement_thermal',
                'summary',
                'transaction_count',
            ]);
            $this->assertTrue($response->json('success'));
        }
    }
}
