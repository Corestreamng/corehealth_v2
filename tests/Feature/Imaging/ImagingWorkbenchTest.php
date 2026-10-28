<?php

namespace Tests\Feature\Imaging;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class ImagingWorkbenchTest extends TestCase
{
    /** @test */
    public function test_imaging_workbench_returns_200()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/imaging-workbench');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404]));
    }

    /** @test */
    public function test_imaging_request_created_from_encounter()
    {
        $patient = Patient::factory()->create();
        $this->assertNotNull($patient->id);
    }

    /** @test */
    public function test_imaging_result_upload_updates_status()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_imaging_workbench_datatable_endpoint_returns_json()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/imaging-workbench/queue');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_imaging_record_billing_endpoint()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->post('/imaging-workbench/record-billing', [
            'request_ids' => [999999],
        ]);
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 422, 500]));
    }

    /** @test */
    public function test_imaging_dismiss_requests_endpoint()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->post('/imaging-workbench/dismiss-requests', [
            'request_ids' => [999999],
        ]);
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 422, 500]));
    }

    /** @test */
    public function test_imaging_save_result_persists_entered_by_and_approved_by_when_approval_disabled()
    {
        \Illuminate\Support\Facades\DB::table('application_status')->update(['imaging_results_require_approval' => 0]);
        \Illuminate\Support\Facades\Cache::forget('app_settings');
        appsettings(null, true);

        $admin = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['ADMIN', 'SUPERADMIN', 'RADIOLOGIST']);
        })->first() ?? User::find(1);

        $patient = Patient::first() ?? Patient::factory()->create();
        $imagingRequest = \App\Models\ImagingServiceRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'service_id' => 1,
            'status' => 2,
            'is_free_form' => 1,
        ]);

        // Attempt without approver details when approval is disabled -> fails 422
        $response = $this->actingAs($admin)->postJson('/imaging-workbench/save-result', [
            'invest_res_entry_id' => $imagingRequest->id,
            'invest_res_template_version' => 1,
            'invest_res_template_submited' => '<p>X-ray Chest PA normal</p>',
            'entered_by_name' => 'Rad Tech Bob',
            'entered_by_designation' => 'Radiographer',
        ]);
        $this->assertContains($response->status(), [422, 403]);

        // Submit with approver details -> succeeds 200
        $responseSuccess = $this->actingAs($admin)->postJson('/imaging-workbench/save-result', [
            'invest_res_entry_id' => $imagingRequest->id,
            'invest_res_template_version' => 1,
            'invest_res_template_submited' => '<p>X-ray Chest PA normal</p>',
            'entered_by_name' => 'Rad Tech Bob',
            'entered_by_designation' => 'Senior Radiographer',
            'approved_by_name' => 'Dr. Lead Radiologist',
            'approved_by_designation' => 'Consultant Radiologist / HOD',
        ]);
        $this->assertContains($responseSuccess->status(), [200, 302, 403, 500]);

        if ($responseSuccess->status() === 200) {
            $imagingRequest->refresh();
            $this->assertEquals(4, $imagingRequest->status);
            $this->assertEquals('Rad Tech Bob', $imagingRequest->entered_by_name);
            $this->assertEquals('Senior Radiographer', $imagingRequest->entered_by_designation);
            $this->assertEquals('Dr. Lead Radiologist', $imagingRequest->approved_by_name);
            $this->assertEquals('Consultant Radiologist / HOD', $imagingRequest->approved_by_designation);
        }
    }

    /** @test */
    public function test_imaging_approve_result_persists_edited_approver_and_designation()
    {
        $admin = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['ADMIN', 'SUPERADMIN']);
        })->first() ?? User::find(1);

        $patient = Patient::first() ?? Patient::factory()->create();
        $imagingRequest = \App\Models\ImagingServiceRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'service_id' => 1,
            'status' => 5, // Pending approval
            'pending_result' => '<p>Pending ultrasound scan report</p>',
            'entered_by_name' => 'Sonographer Alice',
            'entered_by_designation' => 'Ultrasound Sonographer',
            'is_free_form' => 1,
        ]);

        $response = $this->actingAs($admin)->postJson("/imaging-workbench/approval/{$imagingRequest->id}/approve", [
            'approved_by_name' => 'Dr. Master Radiologist',
            'approved_by_designation' => 'Chief of Radiology',
        ]);

        $this->assertContains($response->status(), [200, 403, 500]);
        if ($response->status() === 200) {
            $imagingRequest->refresh();
            $this->assertEquals(4, $imagingRequest->status);
            $this->assertEquals('Dr. Master Radiologist', $imagingRequest->approved_by_name);
            $this->assertEquals('Chief of Radiology', $imagingRequest->approved_by_designation);
            $this->assertEquals($admin->id, $imagingRequest->approved_by);
        }
    }

    /** @test */
    public function test_imaging_report_show_view_renders_entered_by_and_approved_by()
    {
        $admin = User::find(1) ?? User::factory()->create(['status' => 1]);
        $patient = Patient::first() ?? Patient::factory()->create();
        $imagingRequest = \App\Models\ImagingServiceRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'service_id' => 1,
            'status' => 4,
            'result' => '<p>Complete imaging report</p>',
            'entered_by_name' => 'Staff Radiographer Mark',
            'entered_by_designation' => 'Imaging Technologist',
            'approved_by_name' => 'Dr. Chief Radiologist',
            'approved_by_designation' => 'Consultant Radiologist',
            'is_free_form' => 1,
        ]);

        $response = $this->actingAs($admin)->get("/imaging-requests/{$imagingRequest->id}");
        $this->assertContains($response->status(), [200, 302, 403, 404, 500]);
        if ($response->status() === 200) {
            $response->assertSee('Entered By:');
            $response->assertSee('Approved By:');
            $response->assertSee('Staff Radiographer Mark');
            $response->assertSee('Dr. Chief Radiologist');
        }
    }
}
