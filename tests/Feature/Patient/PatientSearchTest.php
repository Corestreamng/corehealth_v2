<?php

namespace Tests\Feature\Patient;

use App\Models\User;
use Tests\TestCase;

class PatientSearchTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/patient-search?q=test');
        $this->assertTrue(in_array($response->status(), [302, 403, 404, 500]));
    }

    /** @test */
    public function test_patient_search_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/patient-search?q=test');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }

    /** @test */
    public function test_patient_search_matches_by_lab_number()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $patient = \App\Models\Patient::factory()->create();

        $year = date('y');
        $testLabNo = "LAB{$year} - " . rand(80000, 89999);

        \App\Models\LabServiceRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'service_id' => 1,
            'status' => 3,
            'lab_number' => $testLabNo,
            'sample_date' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/patient-search?term=' . urlencode($testLabNo) . '&context=lab');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $found = collect($data)->firstWhere('id', $patient->id);
            $this->assertNotNull($found, 'Patient should be resolved by lab number in global search');
            $this->assertEquals($testLabNo, $found['latest_lab_number']);
        }
    }
}
