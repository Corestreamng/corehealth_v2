<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Patient;
use App\Models\Encounter;
use App\Models\NonPharmOrder;

class NonPharmOrderTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $patient;
    protected $encounter;

    protected function setUp(): void
    {
        parent::setUp();

        // Create standard user
        $this->user = User::factory()->create([
            'status' => 1
        ]);

        // Create patient and encounter
        $this->patient = Patient::factory()->create();
        $this->encounter = Encounter::factory()->create([
            'patient_id' => $this->patient->id
        ]);
    }

    /** @test */
    public function it_can_store_a_new_non_pharmacological_order()
    {
        $this->actingAs($this->user);

        $payload = [
            'patient_id' => $this->patient->id,
            'encounter_id' => $this->encounter->id,
            'category' => 'Diet',
            'instructions' => 'Strict low sodium diabetic diet, fluids at bedside.',
            'target_executor' => 'patient'
        ];

        $response = $this->postJson('/non-pharm-orders', $payload);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Care order added successfully'
                 ]);

        $this->assertDatabaseHas('non_pharm_orders', [
            'patient_id' => $this->patient->id,
            'encounter_id' => $this->encounter->id,
            'category' => 'Diet',
            'instructions' => 'Strict low sodium diabetic diet, fluids at bedside.',
            'target_executor' => 'patient',
            'status' => 'active',
            'requested_by' => $this->user->id
        ]);
    }

    /** @test */
    public function it_can_retrieve_patient_non_pharmacological_orders()
    {
        $this->actingAs($this->user);

        // Create active orders
        NonPharmOrder::create([
            'patient_id' => $this->patient->id,
            'encounter_id' => $this->encounter->id,
            'category' => 'Activity',
            'instructions' => 'Ambulate 3 times daily.',
            'target_executor' => 'patient',
            'status' => 'active',
            'requested_by' => $this->user->id
        ]);

        NonPharmOrder::create([
            'patient_id' => $this->patient->id,
            'encounter_id' => $this->encounter->id,
            'category' => 'Bedside Care',
            'instructions' => 'Turn and reposition patient every 2 hours.',
            'target_executor' => 'nurse',
            'status' => 'active',
            'requested_by' => $this->user->id
        ]);

        $response = $this->getJson("/non-pharm-orders/patient/{$this->patient->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'category' => 'Activity',
                     'instructions' => 'Ambulate 3 times daily.',
                     'target_executor' => 'patient'
                 ])
                 ->assertJsonFragment([
                     'category' => 'Bedside Care',
                     'instructions' => 'Turn and reposition patient every 2 hours.',
                     'target_executor' => 'nurse'
                 ]);
    }

    /** @test */
    public function it_allows_a_nurse_to_perform_and_complete_a_bedside_checklist_task_with_notes()
    {
        $this->actingAs($this->user);

        $order = NonPharmOrder::create([
            'patient_id' => $this->patient->id,
            'encounter_id' => $this->encounter->id,
            'category' => 'Bedside Care',
            'instructions' => 'Wound dressing check.',
            'target_executor' => 'nurse',
            'status' => 'active',
            'requested_by' => $this->user->id
        ]);

        $response = $this->postJson("/non-pharm-orders/{$order->id}/complete", [
            'notes' => 'Wound dressing is clean, dry and intact. No signs of erythema.'
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Care order marked as completed'
                 ]);

        $this->assertDatabaseHas('non_pharm_orders', [
            'id' => $order->id,
            'status' => 'completed',
            'completed_by' => $this->user->id,
            'completed_notes' => 'Wound dressing is clean, dry and intact. No signs of erythema.'
        ]);

        $freshOrder = $order->fresh();
        $this->assertNotNull($freshOrder->completed_at);
    }

    /** @test */
    public function it_allows_a_clinician_to_discontinue_an_active_order_with_a_reason()
    {
        $this->actingAs($this->user);

        $order = NonPharmOrder::create([
            'patient_id' => $this->patient->id,
            'encounter_id' => $this->encounter->id,
            'category' => 'Counseling',
            'instructions' => 'Discuss discharge planning.',
            'target_executor' => 'patient',
            'status' => 'active',
            'requested_by' => $this->user->id
        ]);

        $response = $this->deleteJson("/non-pharm-orders/{$order->id}", [
            'action' => 'discontinue',
            'reason' => 'Patient has been fully discharged.'
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Care order discontinued successfully'
                 ]);

        $this->assertDatabaseHas('non_pharm_orders', [
            'id' => $order->id,
            'status' => 'discontinued',
            'discontinue_reason' => 'Patient has been fully discharged.',
            'discontinued_by' => $this->user->id
        ]);

        $freshOrder = $order->fresh();
        $this->assertNotNull($freshOrder->discontinued_at);
    }
}
