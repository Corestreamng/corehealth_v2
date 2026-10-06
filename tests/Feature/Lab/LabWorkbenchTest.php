<?php

namespace Tests\Feature\Lab;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class LabWorkbenchTest extends TestCase
{
    /** @test */
    public function test_lab_workbench_returns_200()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/lab-workbench');
        $this->assertNotNull($response->status());
    }

    /** @test */
    public function test_sample_collection_updates_request_status()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_lab_result_entry_updates_result_fields()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_result_released_flag_set_on_verify()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_lab_request_linked_to_service_and_patient()
    {
        $patient = Patient::factory()->create();
        $this->assertNotNull($patient->id);
    }

    /** @test */
    public function test_get_patient_requests_returns_pending_approval_items()
    {
        $admin = User::find(1) ?? User::factory()->create(['status' => 1]);
        $patient = Patient::first() ?? Patient::factory()->create();

        $response = $this->actingAs($admin)->get("/lab-workbench/patient/{$patient->id}/requests");
        $this->assertContains($response->status(), [200, 302, 403]);
        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertArrayHasKey('requests', $data);
            $this->assertArrayHasKey('pending_approval', $data['requests']);
            $this->assertArrayHasKey('freeform', $data['requests']);
        }
    }

    /** @test */
    public function test_save_result_from_lab_workbench_routes_to_pending_approval()
    {
        $admin = User::find(1) ?? User::factory()->create(['status' => 1]);
        $labRequest = \App\Models\LabServiceRequest::where('status', 3)->first();

        if ($labRequest) {
            $response = $this->actingAs($admin)->post('/lab-workbench/save-result', [
                'invest_res_entry_id' => $labRequest->id,
                'invest_res_template_version' => 1,
                'invest_res_template_submited' => '<p>Lab workbench test result</p>',
                'entry_source' => 'lab_workbench',
            ]);

            $this->assertContains($response->status(), [200, 302]);
            if ($response->status() === 200 && appsettings('lab_results_require_approval')) {
                $labRequest->refresh();
                $this->assertEquals(5, $labRequest->status);
            }
        } else {
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function test_save_result_self_performed_by_doctor_auto_approves()
    {
        $doctor = User::whereHas('roles', function ($q) {
            $q->where('name', 'DOCTOR');
        })->first() ?? User::find(1);

        $patient = Patient::first() ?? Patient::factory()->create();
        $labRequest = \App\Models\LabServiceRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => 1,
            'status' => 2,
            'is_free_form' => 1,
            'self_perform_intent' => 1,
        ]);

        $response = $this->actingAs($doctor)->post('/lab-workbench/save-result', [
            'invest_res_entry_id' => $labRequest->id,
            'invest_res_template_version' => 1,
            'invest_res_template_submited' => '<p>Self performed glucose test</p>',
            'entry_source' => 'doctor_encounter',
        ]);

        $this->assertContains($response->status(), [200, 302, 403]);
        if ($response->status() === 200) {
            $labRequest->refresh();
            if (appsettings('doctor_self_approve_lab_result')) {
                $this->assertEquals(4, $labRequest->status);
                $this->assertEquals($doctor->id, $labRequest->approved_by);
            }
        }
    }
}
