<?php

namespace Tests\Feature\Billing;

use App\Models\ApplicationStatu;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\ProductOrServiceRequest;
use App\Models\User;
use Tests\TestCase;

class FreeFormBillingTest extends TestCase
{
    protected User $user;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->user = User::first() ?? User::factory()->create(['status' => 1]);

        $patientUser = User::factory()->create(['status' => 1]);
        $this->patient = Patient::create([
            'user_id' => $patientUser->id,
            'file_no' => 'FREE_' . uniqid(),
            'status' => 1,
        ]);

        // Ensure walk-in configuration enables free-form
        $config = ApplicationStatu::first();
        if ($config) {
            $config->update([
                'walkin_reception_enabled' => 1,
                'walkin_biller_enabled' => 1,
                'walkin_free_form_enabled' => 1,
            ]);
        }
    }

    /** @test */
    public function test_walkin_creates_free_form_billable_request()
    {
        $response = $this->actingAs($this->user)->postJson(route('reception.book-walkin'), [
            'patient_id' => $this->patient->id,
            'items' => [
                [
                    'type' => 'free_form',
                    'id' => 'free_' . uniqid(),
                    'name' => 'Custom Knee Orthosis',
                    'price' => 35000,
                    'qty' => 1,
                    'is_billable' => 1,
                    'description' => 'Unlisted custom orthopedic equipment',
                ],
            ],
        ]);

        $this->assertTrue(in_array($response->status(), [200, 201]), 'Response: ' . $response->getContent());
        $data = $response->json();
        $this->assertTrue($data['success'] ?? false);

        $posr = ProductOrServiceRequest::where('patient_id', $this->patient->id)
            ->where('is_free_form', true)
            ->first();

        $this->assertNotNull($posr);
        $this->assertEquals('Custom Knee Orthosis', $posr->free_form_name);
        $this->assertTrue((bool) $posr->is_billable);
        $this->assertEquals(35000, (float) $posr->payable_amount);

        // Test display name helper
        $this->assertEquals('Custom Knee Orthosis', getItemDisplayName($posr));
    }

    /** @test */
    public function test_free_form_request_can_be_billed_and_paid()
    {
        $posr = ProductOrServiceRequest::create([
            'user_id' => $this->patient->user_id,
            'staff_user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'service_id' => null,
            'product_id' => null,
            'is_free_form' => true,
            'free_form_name' => 'Specialized Foreign Consultation',
            'free_form_description' => 'Tele-consultation with external specialist',
            'is_billable' => true,
            'qty' => 1,
            'amount' => 50000,
            'payable_amount' => 50000,
            'claims_amount' => 0,
            'status' => 0,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('billing.process-payment'), [
            'patient_id' => $this->patient->id,
            'payment_type' => 'CASH',
            'payment_method' => 'CASH',
            'items' => [
                ['id' => $posr->id, 'qty' => 1, 'discount' => 0],
            ],
        ]);

        $this->assertTrue(in_array($response->status(), [200, 201]), 'Response: ' . $response->getContent());
        $data = $response->json();
        $this->assertTrue($data['success'] ?? false);

        $paymentId = $data['payment_id'] ?? null;
        $this->assertNotNull($paymentId);

        $payment = Payment::find($paymentId);
        $this->assertNotNull($payment);
        $this->assertEquals(50000, (float) $payment->total);

        $posr->refresh();
        $this->assertEquals($payment->id, $posr->payment_id);

        // Test receipt output includes custom item name
        $receiptResponse = $this->actingAs($this->user)->post(route('billing.print-receipt'), [
            'patient_id' => $this->patient->id,
            'payment_ids' => [$payment->id],
        ]);

        $this->assertTrue(in_array($receiptResponse->status(), [200, 302]));
        $html = $receiptResponse->getContent();
        $this->assertStringContainsString('Specialized Foreign Consultation', $html);
    }
}
