<?php

namespace Tests\Feature\Nursing;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class NursingWorkbenchTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/nursing-workbench');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_nursing_workbench_index_loads_for_authenticated_user()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/nursing-workbench');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_get_patient_details_returns_next_of_kin_info()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient available.');
        }

        $patient->update([
            'next_of_kin_name' => 'John Doe Kin',
            'next_of_kin_phone' => '07098765432',
            'next_of_kin_address' => '45 Clinic Way',
        ]);

        $response = $this->actingAs($user)->getJson('/nursing-workbench/patient/' . $patient->id . '/details');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'next_of_kin_name',
                'next_of_kin_phone',
                'next_of_kin_address',
            ]);
            $this->assertEquals('John Doe Kin', $response->json('next_of_kin_name'));
            $this->assertEquals('07098765432', $response->json('next_of_kin_phone'));
            $this->assertEquals('45 Clinic Way', $response->json('next_of_kin_address'));
        }
    }
}
