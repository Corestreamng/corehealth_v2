<?php

namespace Tests\Feature\Billing;

use App\Models\Bank;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\ProductOrServiceRequest;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\User;
use Tests\TestCase;

class PaymentSplitTest extends TestCase
{
    protected User $user;

    protected Patient $patient;

    protected Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->user = User::first() ?? User::factory()->create(['status' => 1]);

        $patientUser = User::factory()->create(['status' => 1]);
        $this->patient = Patient::create([
            'user_id' => $patientUser->id,
            'file_no' => 'SPLIT_' . uniqid(),
            'status' => 1,
        ]);

        $this->bank = Bank::first() ?? Bank::create([
            'name' => 'First Bank of Nigeria',
            'account_number' => '1234567890',
            'status' => 1,
        ]);
    }

    /** @test */
    public function test_split_payment_creates_payment_and_split_legs()
    {
        $service = Service::create([
            'user_id' => $this->user->id,
            'service_name' => 'General Consultation ' . uniqid(),
            'category_id' => 1,
            'status' => 1,
        ]);

        ServicePrice::create([
            'user_id' => $this->user->id,
            'service_id' => $service->id,
            'sale_price' => 10000,
        ]);

        $posr = ProductOrServiceRequest::create([
            'user_id' => $this->patient->user_id,
            'staff_user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'service_id' => $service->id,
            'qty' => 1,
            'amount' => 10000,
            'payable_amount' => 10000,
            'claims_amount' => 0,
            'status' => 0,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('billing.process-payment'), [
            'patient_id' => $this->patient->id,
            'payment_type' => 'SPLIT',
            'payment_method' => 'SPLIT',
            'is_split_payment' => true,
            'items' => [
                ['id' => $posr->id, 'qty' => 1, 'discount' => 0],
            ],
            'splits' => [
                [
                    'payment_method' => 'CASH',
                    'amount' => 4000,
                    'bank_id' => null,
                ],
                [
                    'payment_method' => 'POS',
                    'amount' => 6000,
                    'bank_id' => $this->bank->id,
                ],
            ],
        ]);

        $this->assertTrue(in_array($response->status(), [200, 201]), 'Response was: ' . $response->getContent());
        $data = $response->json();
        $this->assertTrue($data['success'] ?? false);

        $paymentId = $data['payment_id'] ?? null;
        $this->assertNotNull($paymentId);

        $payment = Payment::with('splits')->find($paymentId);
        $this->assertNotNull($payment);
        $this->assertTrue((bool) $payment->is_split_payment);
        $this->assertEquals(10000, (float) $payment->total);
        $this->assertCount(2, $payment->splits);

        $cashSplit = $payment->splits->firstWhere('payment_method', 'CASH');
        $posSplit = $payment->splits->firstWhere('payment_method', 'POS');

        $this->assertNotNull($cashSplit);
        $this->assertEquals(4000, (float) $cashSplit->amount);

        $this->assertNotNull($posSplit);
        $this->assertEquals(6000, (float) $posSplit->amount);
        $this->assertEquals($this->bank->id, $posSplit->bank_id);

        // Check formatPaymentMethod helper
        $formatted = formatPaymentMethod($payment);
        $this->assertStringContainsString('Split:', $formatted);
        $this->assertStringContainsString('Cash', $formatted);
        $this->assertStringContainsString('POS', $formatted);
    }

    /** @test */
    public function test_split_payment_rejects_when_splits_sum_does_not_match_total()
    {
        $service = Service::create([
            'user_id' => $this->user->id,
            'service_name' => 'Dental Checkup ' . uniqid(),
            'category_id' => 1,
            'status' => 1,
        ]);

        ServicePrice::create([
            'user_id' => $this->user->id,
            'service_id' => $service->id,
            'sale_price' => 10000,
        ]);

        $posr = ProductOrServiceRequest::create([
            'user_id' => $this->patient->user_id,
            'staff_user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'service_id' => $service->id,
            'qty' => 1,
            'amount' => 10000,
            'payable_amount' => 10000,
            'claims_amount' => 0,
            'status' => 0,
        ]);

        // Splits sum is 9000 instead of 10000
        $response = $this->actingAs($this->user)->postJson(route('billing.process-payment'), [
            'patient_id' => $this->patient->id,
            'payment_type' => 'SPLIT',
            'payment_method' => 'SPLIT',
            'is_split_payment' => true,
            'items' => [
                ['id' => $posr->id, 'qty' => 1, 'discount' => 0],
            ],
            'splits' => [
                [
                    'payment_method' => 'CASH',
                    'amount' => 4000,
                ],
                [
                    'payment_method' => 'POS',
                    'amount' => 5000, // 4000 + 5000 = 9000 != 10000
                    'bank_id' => $this->bank->id,
                ],
            ],
        ]);

        $this->assertTrue(in_array($response->status(), [400, 422]));
        $data = $response->json();
        $this->assertFalse($data['success'] ?? true);
        $this->assertStringContainsString('sum', strtolower($data['message'] ?? ''));
    }
}
