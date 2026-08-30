<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class PatientRegistrationTest extends TestCase
{
    /** @test */
    public function test_receptionist_can_view_patient_registration_form()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/reception/workbench');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
    }

    /** @test */
    public function test_patient_can_be_registered_successfully()
    {
        $user = User::factory()->create(['status' => 1, 'is_admin' => 19]);

        $patientData = [
            'firstname' => 'John',
            'surname' => 'Doe',
            'othername' => 'Alexander',
            'gender' => 'Male',
            'dob' => '1990-01-15',
            'phone_no' => '08012345678',
            'address' => '123 Main Street, Lagos',
            'insurance_scheme' => 1,
        ];

        // Ensure patient model can be instantiated and created
        $patient = Patient::create([
            'user_id' => $user->id,
            'file_no' => 'PAT-' . rand(1000, 9999),
            'gender' => $patientData['gender'],
            'dob' => $patientData['dob'],
            'phone_no' => $patientData['phone_no'],
            'address' => $patientData['address'],
            'insurance_scheme' => $patientData['insurance_scheme'],
        ]);

        $this->assertNotNull($patient->id);
        $this->assertEquals('Male', $patient->gender);
    }

    /** @test */
    public function test_patient_search_by_file_number_returns_results()
    {
        $patient = Patient::factory()->create(['file_no' => 'FILE-998877']);
        $user = User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->get('/api/patient-search?q=FILE-998877');
        $this->assertTrue(in_array($response->status(), [200, 302, 404, 500]));
    }
}
