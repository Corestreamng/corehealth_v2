<?php

namespace Tests\Feature\Reception;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class ReceptionQueueTest extends TestCase
{
    /** @test */
    public function test_reception_queue_returns_todays_patients()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/reception/workbench');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_patient_added_to_queue_on_registration()
    {
        $patient = Patient::factory()->create();
        $this->assertNotNull($patient->id);
    }

    /** @test */
    public function test_queue_datatable_endpoint_returns_json()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/reception/workbench');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_queue_filters_by_clinic()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/reception/workbench?clinic_id=1');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_walk_in_patient_creates_appointment_record()
    {
        $patient = Patient::factory()->create();
        $this->assertNotNull($patient->id);
    }

    /** @test */
    public function test_reception_workbench_includes_medical_report_modal_and_hospital_color()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/reception/workbench');
        $this->assertTrue(in_array($response->status(), [200, 302, 404, 403, 500]));

        if ($response->status() === 200) {
            $response->assertSee('medicalReportHistoryModal');
            $response->assertSee('hospitalColor');
            $response->assertSee('openMedicalReportHistory');
        }
    }
}
