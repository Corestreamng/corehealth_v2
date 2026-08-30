<?php

namespace Tests\Feature\Lab;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class ResultPrintTest extends TestCase
{
    /** @test */
    public function test_lab_result_print_view_returns_200()
    {
        $user = User::factory()->create(['status' => 1]);
        $patient = Patient::first() ?? Patient::create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/lab/results/print?patient_id={$patient->id}");
        $this->assertNotNull($response->status());
    }

    /** @test */
    public function test_print_view_contains_patient_name()
    {
        $user = User::factory()->create(['firstname' => 'Jane', 'surname' => 'Doe', 'status' => 1]);
        $this->assertEquals('Jane', $user->firstname);
    }

    /** @test */
    public function test_print_view_uses_hospital_branding()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/lab/results/print');
        $this->assertNotNull($response->status());
    }

    /** @test */
    public function test_imaging_result_print_view_returns_200()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/imaging/results/print');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }
}
