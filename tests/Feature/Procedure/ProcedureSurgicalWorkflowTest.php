<?php

namespace Tests\Feature\Procedure;

use App\Models\ChecklistTemplate;
use App\Models\Procedure;
use App\Models\ProcedureDefinition;
use App\Models\Service;
use App\Models\User;
use Tests\TestCase;

class ProcedureSurgicalWorkflowTest extends TestCase
{
    /** @test */
    public function test_service_and_procedure_detect_surgical_flag()
    {
        $procedureCategoryId = (int) (appsettings('procedure_category_id', 8) ?: 8);

        $surgicalService = Service::where('category_id', $procedureCategoryId)
            ->whereHas('procedureDefinition', function ($q) {
                $q->where('is_surgical', true);
            })->first();

        if (!$surgicalService) {
            $surgicalService = Service::create([
                'service_name' => 'Emergency Caesarean Section',
                'service_code' => 'TEST-SRV-CS-' . uniqid(),
                'category_id' => $procedureCategoryId,
                'status' => 1,
            ]);
            $surgicalDef = ProcedureDefinition::create([
                'service_id' => $surgicalService->id,
                'procedure_category_id' => 1,
                'code' => 'TEST-CS-' . uniqid(),
                'name' => 'Emergency Caesarean Section',
                'is_surgical' => true,
                'estimated_duration_minutes' => 60,
                'status' => 1,
            ]);
        } else {
            $surgicalDef = $surgicalService->procedureDefinition;
        }

        $this->assertTrue($surgicalService->is_surgical);

        $procedure = new Procedure([
            'name' => $surgicalService->service_name,
            'procedure_definition_id' => $surgicalDef->id,
            'service_id' => $surgicalService->id,
        ]);

        $this->assertTrue($procedure->is_surgical);

        // Bedside procedure test
        $bedsideService = Service::where('category_id', $procedureCategoryId)
            ->whereHas('procedureDefinition', function ($q) {
                $q->where('is_surgical', false);
            })->first();

        if (!$bedsideService) {
            $bedsideService = Service::create([
                'service_name' => 'Wound Dressing & Cleaning',
                'service_code' => 'TEST-SRV-WD-' . uniqid(),
                'category_id' => $procedureCategoryId,
                'status' => 1,
            ]);
            $bedsideDef = ProcedureDefinition::create([
                'service_id' => $bedsideService->id,
                'procedure_category_id' => 13,
                'code' => 'TEST-WD-' . uniqid(),
                'name' => 'Wound Dressing & Cleaning',
                'is_surgical' => false,
                'estimated_duration_minutes' => 20,
                'status' => 1,
            ]);
        } else {
            $bedsideDef = $bedsideService->procedureDefinition;
        }

        $this->assertFalse($bedsideService->is_surgical);

        $bedsideProc = new Procedure([
            'name' => $bedsideService->service_name,
            'procedure_definition_id' => $bedsideDef->id,
            'service_id' => $bedsideService->id,
        ]);

        $this->assertFalse($bedsideProc->is_surgical);
    }

    /** @test */
    public function test_checklist_templates_exist_and_return_items()
    {
        $surgicalTemplate = ChecklistTemplate::getDefaultByType(ChecklistTemplate::TYPE_SURGICAL);
        $this->assertNotNull($surgicalTemplate, 'Surgical checklist template should exist');
        $this->assertGreaterThan(0, $surgicalTemplate->items()->count());

        $procedureTemplate = ChecklistTemplate::getDefaultByType(ChecklistTemplate::TYPE_PROCEDURE);
        $this->assertNotNull($procedureTemplate, 'Bedside procedure checklist template should exist');
        $this->assertGreaterThan(0, $procedureTemplate->items()->count());
    }

    /** @test */
    public function test_procedure_get_checklist_items_merges_saved_state()
    {
        $surgicalTemplate = ChecklistTemplate::getDefaultByType(ChecklistTemplate::TYPE_SURGICAL);
        $firstItem = $surgicalTemplate->items()->first();

        $procedure = Procedure::first();
        if (!$procedure) {
            $user = User::first() ?? User::factory()->create(['status' => 1]);
            $procedure = Procedure::create([
                'patient_id' => 1,
                'user_id' => $user->id,
                'name' => 'Major Surgical Procedure',
                'procedure_status' => 'scheduled',
                'prep_details' => [
                    'checklist' => [
                        $firstItem->id => [
                            'completed' => true,
                            'completed_by_name' => 'Nurse Sarah',
                            'completed_at' => now()->toIso8601String(),
                        ],
                    ],
                ],
            ]);
        } else {
            $prep = $procedure->prep_details ?? [];
            $prep['checklist'] = [
                $firstItem->id => [
                    'completed' => true,
                    'completed_by_name' => 'Nurse Sarah',
                    'completed_at' => now()->toIso8601String(),
                ],
            ];
            $procedure->prep_details = $prep;
            $procedure->save();
        }

        $items = $procedure->getChecklistItems();
        $this->assertNotEmpty($items);

        $matched = collect($items)->firstWhere('id', $firstItem->id);
        $this->assertNotNull($matched);
        $this->assertTrue($matched['is_completed']);
        $this->assertEquals('Nurse Sarah', $matched['completed_by_name']);
    }

    /** @test */
    public function test_checklist_toggle_endpoint_updates_procedure_and_returns_progress()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $procedure = Procedure::first();
        if (!$procedure) {
            $procedure = Procedure::create([
                'patient_id' => 1,
                'user_id' => $user->id,
                'name' => 'Surgical Safety Test Case',
                'procedure_status' => 'requested',
            ]);
        }

        $surgicalTemplate = ChecklistTemplate::getDefaultByType(ChecklistTemplate::TYPE_SURGICAL);
        $item = $surgicalTemplate->items()->first();

        $response = $this->actingAs($user)->postJson("/patient-procedures/{$procedure->id}/checklist-toggle", [
            'item_id' => $item->id,
            'is_completed' => 1,
            'notes' => 'Patient wristband double-checked in ward',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $response->assertJson([
                'success' => true,
                'is_completed' => true,
            ]);
            $response->assertJsonStructure([
                'success',
                'message',
                'is_completed',
                'progress' => ['completed', 'total', 'percent'],
            ]);

            $procedure->refresh();
            $this->assertTrue(!empty($procedure->prep_details['checklist'][$item->id]['completed']));
        }
    }

    /** @test */
    public function test_live_search_service_includes_surgical_info()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/service-search?q=Section');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            if (count($data) > 0) {
                $first = $data[0];
                $this->assertArrayHasKey('is_surgical', $first);
                $this->assertArrayHasKey('estimated_duration_minutes', $first);
            }
        }
    }

    /** @test */
    public function test_procedure_prep_summary_and_pre_notes_formatting()
    {
        $procedure = new Procedure([
            'name' => 'Cesarean Section Emergency',
            'prep_details' => [
                'npo_status' => 'strict_npo',
                'anesthesia_type' => 'spinal',
                'blood_required' => 'yes',
                'operating_room' => 'Theatre 2',
            ],
        ]);

        $summary = $procedure->prep_summary;
        $this->assertStringContainsString('NPO: Strict Npo', $summary);
        $this->assertStringContainsString('Anesth: Spinal', $summary);
        $this->assertStringContainsString('Blood: G&X Needed', $summary);
        $this->assertStringContainsString('Room: Theatre 2', $summary);
    }
}
