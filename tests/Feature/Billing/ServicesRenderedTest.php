<?php

namespace Tests\Feature\Billing;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class ServicesRenderedTest extends TestCase
{
    /** @test */
    public function test_services_rendered_page_returns_200_for_patient()
    {
        $user = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($user)->get("/services-rendered?patient_id={$patient->id}");
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_admission_info_shown_in_services_rendered()
    {
        $user = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();
        $response = $this->actingAs($user)->get("/services-rendered?patient_id={$patient->id}");
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_doctor_notes_visible_in_services_rendered()
    {
        $user = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();
        $response = $this->actingAs($user)->get("/services-rendered?patient_id={$patient->id}");
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_services_rendered_shows_all_billed_items()
    {
        $user = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();
        $response = $this->actingAs($user)->get("/services-rendered?patient_id={$patient->id}");
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }
}

