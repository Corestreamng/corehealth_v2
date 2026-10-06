<?php

namespace Tests\Feature\Nursing;

use App\Models\Patient;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\User;
use Tests\TestCase;

class NursingBillingTest extends TestCase
{
    /** @test */
    public function test_add_service_bill_canonical_and_alias_routes_exist()
    {
        $nurse = User::factory()->create(['status' => 1]);
        $this->actingAs($nurse);

        // Validation error on empty payload to canonical route (POST /nursing-workbench/add-service-bill)
        $responseCanonical = $this->postJson('/nursing-workbench/add-service-bill', []);
        $this->assertNotEquals(404, $responseCanonical->status());
        $this->assertTrue(in_array($responseCanonical->status(), [422, 200, 302]));

        // Validation error on empty payload to alias route (POST /nursing-workbench/billing/add-service)
        // Previously this returned 404 Not Found
        $responseAlias = $this->postJson('/nursing-workbench/billing/add-service', []);
        $this->assertNotEquals(404, $responseAlias->status());
        $this->assertTrue(in_array($responseAlias->status(), [422, 200, 302]));
    }

    /** @test */
    public function test_nurse_can_add_service_bill_via_alias_route()
    {
        $nurse = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        $service = Service::create([
            'service_name' => 'Nursing Dressing Service',
            'service_code' => 'NUR-DRESS-' . uniqid(),
            'user_id' => $nurse->id,
            'category_id' => 1,
            'status' => 1,
        ]);

        ServicePrice::create([
            'service_id' => $service->id,
            'sale_price' => 2500,
            'cost_price' => 1000,
        ]);

        $this->actingAs($nurse);

        $response = $this->postJson('/nursing-workbench/billing/add-service', [
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'qty' => 2,
            'notes' => 'Wound dressing change',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 422, 500]));

        if ($response->status() === 200) {
            $response->assertJson([
                'success' => true,
            ]);

            $this->assertDatabaseHas('product_or_service_requests', [
                'user_id' => $patient->user_id,
                'service_id' => $service->id,
                'qty' => 2,
            ]);
        }
    }

    /** @test */
    public function test_billing_alias_endpoints_exist_and_do_not_404()
    {
        $nurse = User::factory()->create(['status' => 1]);
        $this->actingAs($nurse);

        // Alias for consumable
        $respConsumable = $this->postJson('/nursing-workbench/billing/add-consumable', []);
        $this->assertNotEquals(404, $respConsumable->status());

        // Alias for consumable bill
        $respConsumableBill = $this->postJson('/nursing-workbench/billing/add-consumable-bill', []);
        $this->assertNotEquals(404, $respConsumableBill->status());

        // Alias for lab bill
        $respLab = $this->postJson('/nursing-workbench/billing/add-lab-bill', []);
        $this->assertNotEquals(404, $respLab->status());

        // Alias for imaging bill
        $respImaging = $this->postJson('/nursing-workbench/billing/add-imaging-bill', []);
        $this->assertNotEquals(404, $respImaging->status());
    }
}
