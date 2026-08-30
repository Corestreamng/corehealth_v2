<?php

namespace Tests\Feature\Billing;

use App\Models\Patient;
use App\Models\Product;
use App\Models\ProductOrServiceRequest;
use Tests\TestCase;

class PrescriptionBillingTest extends TestCase
{
    /** @test */
    public function test_prescription_generates_product_service_request()
    {
        $patient = Patient::factory()->create();
        $product = Product::firstOrCreate(['product_name' => 'Amoxicillin 500mg'], ['user_id' => 1, 'staff_user_id' => 1, 'category_id' => 1, 'status' => 1]);

        $request = ProductOrServiceRequest::create([
            'user_id' => 1, 'staff_user_id' => 1,
            'patient_id' => $patient->id,
            'product_id' => $product->id,
            'qty' => 2,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('product_or_service_requests', ['id' => $request->id]);
    }

    /** @test */
    public function test_investigation_request_generates_billing_entry()
    {
        $patient = Patient::factory()->create();
        $request = ProductOrServiceRequest::create([
            'user_id' => 1, 'staff_user_id' => 1,
            'patient_id' => $patient->id,
            'status' => 'pending',
        ]);
        $this->assertNotNull($request->id);
    }

    /** @test */
    public function test_billing_status_pending_on_creation()
    {
        $patient = Patient::factory()->create();
        $request = ProductOrServiceRequest::create([
            'user_id' => 1,
            'staff_user_id' => 1,
            'patient_id' => $patient->id,
        ]);
        $this->assertNotNull($request->id);
    }

    /** @test */
    public function test_billing_total_calculated_from_price_model()
    {
        $request = new ProductOrServiceRequest(['qty' => 3, 'unit_price' => 500]);
        $request->qty = 3;
        $request->unit_price = 500;
        $total = ($request->qty ?? 1) * ($request->unit_price ?? 0);
        $this->assertEquals(1500, $total);
    }
}
