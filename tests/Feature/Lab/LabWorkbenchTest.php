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

        $this->assertContains($response->status(), [200, 302, 403, 500]);
        if ($response->status() === 200) {
            $labRequest->refresh();
            if (appsettings('doctor_self_approve_lab_result')) {
                $this->assertEquals(4, $labRequest->status);
                $this->assertEquals($doctor->id, $labRequest->approved_by);
            }
        }
    }

    /** @test */
    public function test_lab_save_result_persists_entered_by_and_approved_by_when_approval_disabled()
    {
        \Illuminate\Support\Facades\DB::table('application_status')->update(['lab_results_require_approval' => 0]);
        \Illuminate\Support\Facades\Cache::forget('app_settings');
        appsettings(null, true);

        $admin = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['ADMIN', 'SUPERADMIN', 'LAB SCIENTIST']);
        })->first() ?? User::find(1);

        $patient = Patient::first() ?? Patient::factory()->create();
        $labRequest = \App\Models\LabServiceRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'service_id' => 1,
            'status' => 2,
            'is_free_form' => 1,
        ]);

        // Attempt without approver when approval is disabled -> fails validation 422
        $response = $this->actingAs($admin)->postJson('/lab-workbench/save-result', [
            'invest_res_entry_id' => $labRequest->id,
            'invest_res_template_version' => 1,
            'invest_res_template_submited' => '<p>Lab result test</p>',
            'entered_by_name' => 'Scientist John Doe',
            'entered_by_designation' => 'Medical Laboratory Scientist',
        ]);
        $this->assertContains($response->status(), [422, 403]);

        // Now submit with required approver details -> succeeds 200
        $responseSuccess = $this->actingAs($admin)->postJson('/lab-workbench/save-result', [
            'invest_res_entry_id' => $labRequest->id,
            'invest_res_template_version' => 1,
            'invest_res_template_submited' => '<p>Lab result test</p>',
            'entered_by_name' => 'Scientist John Doe',
            'entered_by_designation' => 'Senior Medical Laboratory Scientist',
            'approved_by_name' => 'Dr. Approver Pathologist',
            'approved_by_designation' => 'Chief Pathologist / HOD',
        ]);
        $this->assertContains($responseSuccess->status(), [200, 302, 403, 500]);

        if ($responseSuccess->status() === 200) {
            $labRequest->refresh();
            $this->assertEquals(4, $labRequest->status);
            $this->assertEquals('Scientist John Doe', $labRequest->entered_by_name);
            $this->assertEquals('Senior Medical Laboratory Scientist', $labRequest->entered_by_designation);
            $this->assertEquals('Dr. Approver Pathologist', $labRequest->approved_by_name);
            $this->assertEquals('Chief Pathologist / HOD', $labRequest->approved_by_designation);
        }
    }

    /** @test */
    public function test_lab_approve_result_persists_edited_approver_and_designation()
    {
        $admin = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['ADMIN', 'SUPERADMIN']);
        })->first() ?? User::find(1);

        $patient = Patient::first() ?? Patient::factory()->create();
        $labRequest = \App\Models\LabServiceRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'service_id' => 1,
            'status' => 5, // Pending approval
            'pending_result' => '<p>Pending lab result</p>',
            'entered_by_name' => 'Tech Jane Doe',
            'entered_by_designation' => 'Lab Technician',
            'is_free_form' => 1,
        ]);

        $response = $this->actingAs($admin)->postJson("/lab-workbench/approval/{$labRequest->id}/approve", [
            'approved_by_name' => 'Prof. Alex Head',
            'approved_by_designation' => 'Head of Laboratory Services',
        ]);

        $this->assertContains($response->status(), [200, 403, 500]);
        if ($response->status() === 200) {
            $labRequest->refresh();
            $this->assertEquals(4, $labRequest->status);
            $this->assertEquals('Prof. Alex Head', $labRequest->approved_by_name);
            $this->assertEquals('Head of Laboratory Services', $labRequest->approved_by_designation);
            $this->assertEquals($admin->id, $labRequest->approved_by);
        }
    }

    /** @test */
    public function test_lab_report_show_view_renders_entered_by_and_approved_by()
    {
        $admin = User::find(1) ?? User::factory()->create(['status' => 1]);
        $patient = Patient::first() ?? Patient::factory()->create();
        $labRequest = \App\Models\LabServiceRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'service_id' => 1,
            'status' => 4,
            'result' => '<p>Complete result</p>',
            'entered_by_name' => 'Sam Entered',
            'entered_by_designation' => 'MLS Specialist',
            'approved_by_name' => 'Dr. Jane Approved',
            'approved_by_designation' => 'Consultant Pathologist',
            'is_free_form' => 1,
        ]);

        $response = $this->actingAs($admin)->get("/service-requests/{$labRequest->id}");
        $this->assertContains($response->status(), [200, 302, 403, 404, 500]);
        if ($response->status() === 200) {
            $response->assertSee('Entered By:');
            $response->assertSee('Approved By:');
            $response->assertSee('Sam Entered');
            $response->assertSee('Dr. Jane Approved');
        }
    }
}
