<?php

namespace Tests\Feature\Billing;

use App\Models\Bank;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\ProductOrServiceRequest;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentAdjustmentAndVoidTest extends TestCase
{
    protected User $biller;

    protected User $otherBiller;

    protected User $admin;

    protected Patient $patient;

    protected Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->biller = User::factory()->create(['status' => 1]);
        $this->otherBiller = User::factory()->create(['status' => 1]);
        $this->admin = User::factory()->create(['status' => 1]);

        // Assign roles if Spatie roles exist
        $adminRole = Role::where('name', 'Admin')->orWhere('name', 'super-admin')->first();
        if ($adminRole) {
            $this->admin->assignRole($adminRole);
        }

        $patientUser = User::factory()->create(['status' => 1]);
        $this->patient = Patient::create([
            'user_id' => $patientUser->id,
            'file_no' => 'ADJ_' . uniqid(),
            'status' => 1,
        ]);

        $this->bank = Bank::first() ?? Bank::create([
            'name' => 'Zenith Bank',
            'account_number' => '9876543210',
            'status' => 1,
        ]);
    }

    /** @test */
    public function test_biller_can_adjust_own_payment_to_single_method()
    {
        $payment = Payment::create([
            'reference_no' => 'ADJ-TEST-' . uniqid(),
            'total' => 5000,
            'payment_type' => 'CASH',
            'payment_method' => 'CASH',
            'patient_id' => $this->patient->id,
            'user_id' => $this->biller->id,
        ]);

        $response = $this->actingAs($this->biller)->postJson(route('billing.adjust-payment'), [
            'payment_id' => $payment->id,
            'payment_method' => 'POS',
            'bank_id' => $this->bank->id,
            'adjustment_reason' => 'Customer mistakenly charged as cash instead of POS',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 201]), 'Response: ' . $response->getContent());
        $data = $response->json();
        $this->assertTrue($data['success'] ?? false);

        $payment->refresh();
        $this->assertEquals('POS', $payment->payment_method);
        $this->assertEquals($this->bank->id, $payment->bank_id);
        $this->assertFalse((bool) $payment->is_split_payment);

        // Verify audit adjustment record exists
        $adj = PaymentAdjustment::where('payment_id', $payment->id)->first();
        $this->assertNotNull($adj);
        $this->assertEquals('CASH', $adj->original_payment_method);
        $this->assertEquals('POS', $adj->new_payment_method);
        $this->assertEquals($this->biller->id, $adj->adjusted_by);
    }

    /** @test */
    public function test_biller_can_adjust_payment_to_split_payment()
    {
        $payment = Payment::create([
            'reference_no' => 'ADJ-SPLIT-' . uniqid(),
            'total' => 10000,
            'payment_type' => 'CASH',
            'payment_method' => 'CASH',
            'patient_id' => $this->patient->id,
            'user_id' => $this->biller->id,
        ]);

        $response = $this->actingAs($this->biller)->postJson(route('billing.adjust-payment'), [
            'payment_id' => $payment->id,
            'payment_method' => 'SPLIT',
            'splits' => [
                ['payment_method' => 'CASH', 'amount' => 4000],
                ['payment_method' => 'POS', 'amount' => 6000, 'bank_id' => $this->bank->id],
            ],
            'adjustment_reason' => 'Split cash and card re-allocation',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 201]), 'Response: ' . $response->getContent());

        $payment->refresh();
        $this->assertEquals('SPLIT', $payment->payment_method);
        $this->assertTrue((bool) $payment->is_split_payment);
        $this->assertCount(2, $payment->splits);
        $this->assertEquals(4000, (float) $payment->splits->firstWhere('payment_method', 'CASH')->amount);
        $this->assertEquals(6000, (float) $payment->splits->firstWhere('payment_method', 'POS')->amount);
    }

    /** @test */
    public function test_void_transaction_marks_voided_and_unlinks_items()
    {
        $service = Service::create([
            'user_id' => $this->biller->id,
            'service_name' => 'Ultrasound Scan ' . uniqid(),
            'category_id' => 1,
            'status' => 1,
        ]);

        ServicePrice::create([
            'user_id' => $this->biller->id,
            'service_id' => $service->id,
            'sale_price' => 7500,
        ]);

        $payment = Payment::create([
            'reference_no' => 'VOID-TEST-' . uniqid(),
            'total' => 7500,
            'payment_type' => 'CASH',
            'payment_method' => 'CASH',
            'patient_id' => $this->patient->id,
            'user_id' => $this->biller->id,
        ]);

        $posr = ProductOrServiceRequest::create([
            'user_id' => $this->patient->user_id,
            'staff_user_id' => $this->biller->id,
            'patient_id' => $this->patient->id,
            'service_id' => $service->id,
            'payment_id' => $payment->id,
            'qty' => 1,
            'payable_amount' => 7500,
            'claims_amount' => 0,
            'status' => 1,
        ]);

        $response = $this->actingAs($this->biller)->postJson(route('billing.void-transaction'), [
            'payment_id' => $payment->id,
            'void_reason' => 'Patient requested refund and cancellation before service delivery',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 201]), 'Response: ' . $response->getContent());
        $data = $response->json();
        $this->assertTrue($data['success'] ?? false);

        $payment->refresh();
        $this->assertNotNull($payment->voided_at);
        $this->assertEquals($this->biller->id, $payment->voided_by);
        $this->assertEquals('Patient requested refund and cancellation before service delivery', $payment->void_reason);
        $this->assertTrue($payment->is_voided);

        // Verify request was unlinked
        $posr->refresh();
        $this->assertNull($posr->payment_id);
        $this->assertEquals(0, $posr->status);

        // Verify Payment::notVoided() excludes this payment
        $notVoidedIds = Payment::notVoided()->pluck('id')->all();
        $this->assertNotContains($payment->id, $notVoidedIds);

        // Verify Payment::onlyVoided() includes this payment
        $onlyVoidedIds = Payment::onlyVoided()->pluck('id')->all();
        $this->assertContains($payment->id, $onlyVoidedIds);
    }
}
