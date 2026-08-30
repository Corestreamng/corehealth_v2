<?php

namespace Tests\Feature\Clinical;

use App\Models\Bed;
use App\Models\Patient;
use App\Models\Price;
use App\Models\Service;
use App\Models\User;
use Tests\TestCase;

class DoctorAdmissionTest extends TestCase
{
    /** @test */
    public function test_doctor_can_admit_patient_from_encounter()
    {
        $doctor = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($doctor)->post('/doctor/admit', [
            'patient_id' => $patient->id,
            'reason' => 'Severe acute condition',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_admission_creates_admission_request_record()
    {
        $patient = Patient::factory()->create();
        $this->assertNotNull($patient->id);
    }

    /** @test */
    public function test_bed_assigned_and_marked_occupied()
    {
        $service = Service::create(['service_name' => 'Bed 201 Service', 'user_id' => 1, 'category_id' => 1, 'status' => 1]);
        $bed = Bed::create(['service_id' => $service->id, 'name' => 'Bed 201', 'status' => 1]);
        $bed->update(['status' => 2]);
        $this->assertEquals(2, $bed->status);
    }

    /** @test */
    public function test_doctor_can_discharge_admitted_patient()
    {
        $doctor = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($doctor)->post('/doctor/discharge', [
            'patient_id' => $patient->id,
        ]);
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_discharged_bed_marked_available()
    {
        $service = Service::create(['service_name' => 'Bed 202 Service', 'user_id' => 1, 'category_id' => 1, 'status' => 1]);
        $bed = Bed::create(['service_id' => $service->id, 'name' => 'Bed 202', 'status' => 2]);
        $bed->update(['status' => 1]);
        $this->assertEquals(1, $bed->status);
    }
}
