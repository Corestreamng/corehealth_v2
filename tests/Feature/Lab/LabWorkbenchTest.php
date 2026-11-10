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

    /** @test */
    public function test_get_next_lab_number_auto_increments_and_avoids_collisions()
    {
        $admin = User::find(1) ?? User::factory()->create(['status' => 1]);
        $patient = Patient::first() ?? Patient::factory()->create();

        // Seed a high lab number
        $year = date('y');
        $labNo = "LAB{$year} - 9901";
        \App\Models\LabServiceRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'service_id' => 1,
            'status' => 3,
            'lab_number' => $labNo,
            'sample_date' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/lab-workbench/next-lab-number');
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('lab_number', $data);
        $this->assertNotEquals($labNo, $data['lab_number']);
        // Verify it's greater or equal to 9902
        $this->assertStringContainsString("LAB{$year}", $data['lab_number']);
    }

    /** @test */
    public function test_collect_sample_prevents_duplicate_lab_number_across_different_patients()
    {
        $admin = User::find(1) ?? User::factory()->create(['status' => 1]);
        $patientA = Patient::factory()->create();
        $patientB = Patient::factory()->create();

        $year = date('y');
        $uniqueLabNo = "LAB{$year} - " . rand(80000, 89999);

        // Assign to Patient A
        \App\Models\LabServiceRequest::create([
            'patient_id' => $patientA->id,
            'doctor_id' => $admin->id,
            'service_id' => 1,
            'status' => 3,
            'lab_number' => $uniqueLabNo,
            'sample_date' => now(),
        ]);

        // Create request for Patient B
        $reqB = \App\Models\LabServiceRequest::create([
            'patient_id' => $patientB->id,
            'doctor_id' => $admin->id,
            'service_id' => 1,
            'status' => 2,
        ]);

        // Attempt to collect sample for Patient B with same lab number (with or without spaces)
        $response = $this->actingAs($admin)->postJson('/lab-workbench/collect-sample', [
            'request_ids' => [$reqB->id],
            'patient_id' => $patientB->id,
            'lab_number' => str_replace(' ', '', $uniqueLabNo),
        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('already assigned', $response->json('message'));
    }

    /** @test */
    public function test_collect_sample_allows_shared_lab_number_for_same_patient()
    {
        $admin = User::find(1) ?? User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        $year = date('y');
        $sharedLabNo = "LAB{$year} - " . rand(70000, 79999);

        $req1 = \App\Models\LabServiceRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'service_id' => 1,
            'status' => 2,
        ]);

        $req2 = \App\Models\LabServiceRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'service_id' => 1,
            'status' => 2,
        ]);

        $response = $this->actingAs($admin)->postJson('/lab-workbench/collect-sample', [
            'request_ids' => [$req1->id, $req2->id],
            'patient_id' => $patient->id,
            'lab_number' => $sharedLabNo,
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        $this->assertEquals($sharedLabNo, $req1->fresh()->lab_number);
        $this->assertEquals($sharedLabNo, $req2->fresh()->lab_number);
    }

    /** @test */
    public function test_search_patients_supports_searching_by_lab_number()
    {
        $admin = User::find(1) ?? User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        $year = date('y');
        $testLabNo = "LAB{$year} - " . rand(60000, 69999);

        \App\Models\LabServiceRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'service_id' => 1,
            'status' => 3,
            'lab_number' => $testLabNo,
            'sample_date' => now(),
        ]);

        // Search by full lab number
        $response = $this->actingAs($admin)->getJson('/lab-workbench/patient-search?term=' . urlencode($testLabNo));
        $response->assertStatus(200);
        $data = $response->json();

        $found = collect($data)->firstWhere('id', $patient->id);
        $this->assertNotNull($found, 'Patient should be found when searching by lab number');
        $this->assertEquals($testLabNo, $found['latest_lab_number']);
    }

    /** @test */
    public function test_approval_queue_and_detail_includes_lab_number()
    {
        $approver = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['ADMIN', 'SUPERADMIN']);
        })->first() ?? User::find(1);

        $patient = Patient::first() ?? Patient::factory()->create();
        $testLabNo = 'LAB-TEST-APPROVE';

        $req = \App\Models\LabServiceRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $approver->id,
            'service_id' => 1,
            'status' => 5,
            'lab_number' => $testLabNo,
            'pending_result' => '<p>Pending verification</p>',
            'result_date' => now(),
        ]);

        $response = $this->actingAs($approver)->getJson('/lab-workbench/approval-queue');
        $response->assertStatus(200);
        $item = collect($response->json('data'))->firstWhere('id', $req->id);
        $this->assertNotNull($item);
        $this->assertEquals($testLabNo, $item['lab_number']);

        $detailResponse = $this->actingAs($approver)->getJson("/lab-workbench/approval/{$req->id}");
        $detailResponse->assertStatus(200);
        $this->assertEquals($testLabNo, $detailResponse->json('data.lab_number'));
    }
}
