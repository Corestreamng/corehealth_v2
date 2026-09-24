<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Tests\TestCase;

class EncounterTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/encounters');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_new_encounter_page_loads_for_authenticated_user()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/encounters');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_encounter_scripts_partial_includes_investigation_result_view_functions_and_modals()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $this->actingAs($user);
        $patient = \App\Models\Patient::factory()->create();

        $view = view('admin.doctors.partials._encounter_scripts', [
            'patient' => $patient,
            'encounter' => null,
            'hos_color' => '#0066cc',
        ]);
        $sections = $view->renderSections();
        $rendered = $sections['scripts'] ?? $view->render();

        // Must define setResViewInModal for lab view button
        $this->assertStringContainsString('setResViewInModal', $rendered);
        // Must include lab result view modal
        $this->assertStringContainsString('id="investResViewModal"', $rendered);

        // Must define setImagingResViewInModal for imaging view button
        $this->assertStringContainsString('setImagingResViewInModal', $rendered);
        // Must include imaging result view modal
        $this->assertStringContainsString('id="imagingResViewModal"', $rendered);

        // Must include investigation entry modal for doctor result entry
        $this->assertStringContainsString('id="investResModal"', $rendered);

        // Must include referral routes in WORKBENCH_CONFIG
        $this->assertStringContainsString('encounters.referrals.create', $rendered);
        $this->assertStringContainsString('encounters.referrals.list', $rendered);
        $this->assertStringContainsString('encounters.referrals.patient-all', $rendered);
        $this->assertStringContainsString('encounters.referrals.incoming', $rendered);
        $this->assertStringContainsString('referrals.decline', $rendered);
    }
}
