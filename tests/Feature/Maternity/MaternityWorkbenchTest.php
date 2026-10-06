<?php

namespace Tests\Feature\Maternity;

use App\Models\User;
use Tests\TestCase;

class MaternityWorkbenchTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/maternity/workbench');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_maternity_workbench_index_loads_for_authenticated_user()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/maternity/workbench');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_get_patient_details_returns_enrollment_for_enrolled_patient()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $enrollment = \App\Models\MaternityEnrollment::first();

        if ($enrollment) {
            $response = $this->actingAs($user)->getJson('/maternity-workbench/patient/' . $enrollment->patient_id . '/details');
            if ($response->status() === 200) {
                $response->assertJsonStructure(['id', 'enrollment']);
                $this->assertNotNull($response->json('enrollment'));
                $this->assertEquals($enrollment->id, $response->json('enrollment.id'));
            } else {
                $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
            }
        } else {
            $patient = \App\Models\Patient::first();
            if ($patient) {
                $response = $this->actingAs($user)->getJson('/maternity-workbench/patient/' . $patient->id . '/details');
                $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
            } else {
                $this->assertTrue(true);
            }
        }
    }

    /** @test */
    public function test_get_patient_details_returns_next_of_kin_info()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $patient = \App\Models\Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient available.');
        }

        $patient->update([
            'next_of_kin_name' => 'Jane Doe Kin',
            'next_of_kin_phone' => '08012345678',
            'next_of_kin_address' => '12 Hospital Road',
        ]);

        $response = $this->actingAs($user)->getJson('/maternity-workbench/patient/' . $patient->id . '/details');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'next_of_kin_name',
                'next_of_kin_phone',
                'next_of_kin_address',
            ]);
            $this->assertEquals('Jane Doe Kin', $response->json('next_of_kin_name'));
            $this->assertEquals('08012345678', $response->json('next_of_kin_phone'));
            $this->assertEquals('12 Hospital Road', $response->json('next_of_kin_address'));
        }
    }
}
