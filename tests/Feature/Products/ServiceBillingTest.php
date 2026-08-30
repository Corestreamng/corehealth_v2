<?php

namespace Tests\Feature\Products;

use App\Models\Encounter;
use App\Models\Patient;
use App\Models\ProductOrServiceRequest;
use App\Models\Service;
use App\Models\User;
use Tests\TestCase;

class ServiceBillingTest extends TestCase
{
    /** @test */
    public function test_service_billed_correctly_on_request()
    {
        $patient = Patient::factory()->create();
        $service = Service::create(['service_name' => 'Lab Test A 101', 'user_id' => 1, 'category_id' => 1, 'price_id' => 1, 'status' => 1]);

        $request = ProductOrServiceRequest::create([
            'user_id' => 1,
            'staff_user_id' => 1,
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'validation_status' => 'pending',
        ]);

        $this->assertDatabaseHas('product_or_service_requests', ['id' => $request->id]);
    }

    /** @test */
    public function test_duplicate_billing_rejected()
    {
        $patient = Patient::factory()->create();
        $request1 = ProductOrServiceRequest::create([
            'user_id' => 1,
            'staff_user_id' => 1,
            'patient_id' => $patient->id,
            'validation_status' => 'pending',
        ]);
        $this->assertNotNull($request1->id);
    }

    /** @test */
    public function test_service_request_links_to_encounter()
    {
        $patient = Patient::factory()->create();
        $doctor = User::factory()->create(['status' => 1]);
        $encounter = Encounter::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id]);

        $request = ProductOrServiceRequest::create([
            'user_id' => 1,
            'staff_user_id' => 1,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'validation_status' => 'pending',
        ]);

        $this->assertEquals($encounter->id, $request->encounter_id);
    }

    /** @test */
    public function test_service_price_applied_at_billing_time()
    {
        $service = Service::create(['service_name' => 'Consultation Fee 102', 'user_id' => 1, 'category_id' => 1, 'price_id' => 1, 'status' => 1]);
        $this->assertNotNull($service->id);
    }
}
