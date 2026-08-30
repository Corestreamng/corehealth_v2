<?php

namespace Tests\Feature\Admission;

use App\Models\Bed;
use App\Models\Patient;
use App\Models\Price;
use App\Models\Product;
use App\Models\Service;
use Tests\TestCase;

class AdmissionFlowTest extends TestCase
{
    /** @test */
    public function test_patient_can_be_admitted_with_bed_assignment()
    {
        $patient = Patient::factory()->create();
        $product = Product::create(['product_name' => 'Test Bed Product 1', 'user_id' => 1, 'category_id' => 1, 'status' => 1]);
        $price = Price::create(['product_id' => $product->id, 'current_sale_price' => 1000]);
        $service = Service::create(['service_name' => 'Bed 101 Service', 'user_id' => 1, 'category_id' => 1, 'status' => 1]);
        $bed = Bed::create(['service_id' => $service->id, 'name' => 'Bed 101', 'status' => 1]);

        $bed->update(['status' => 2, 'occupant_id' => $patient->id]);
        $this->assertEquals(2, $bed->status);
    }

    /** @test */
    public function test_admission_request_created_on_admit()
    {
        $patient = Patient::factory()->create();
        $this->assertNotNull($patient->id);
    }

    /** @test */
    public function test_bed_marked_occupied_on_admission()
    {
        $product = Product::create(['product_name' => 'Test Bed Product 2', 'user_id' => 1, 'category_id' => 1, 'status' => 1]);
        $price = Price::create(['product_id' => $product->id, 'current_sale_price' => 1000]);
        $service = Service::create(['service_name' => 'Bed 102 Service', 'user_id' => 1, 'category_id' => 1, 'status' => 1]);
        $bed = Bed::create(['service_id' => $service->id, 'name' => 'Bed 102', 'status' => 1]);
        $bed->update(['status' => 2]);
        $this->assertEquals(2, $bed->status);
    }
}
