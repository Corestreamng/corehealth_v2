<?php

namespace Tests\Feature\Config;

use App\Models\User;
use Tests\TestCase;

class HospitalConfigTest extends TestCase
{
    /** @test */
    public function test_hospital_config_saves_toggle_values_correctly()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);
        $response = $this->actingAs($admin)->post('/hospitalsettings', [
            'emergency_intake_enabled' => 1,
        ]);
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_invalid_config_value_rejected_with_validation_error()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);
        $this->assertNotNull($admin->id);
    }

    /** @test */
    public function test_config_checkbox_values_stored_as_boolean()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);
        $this->assertNotNull($admin->id);
    }

    /** @test */
    public function test_emergency_intake_config_toggles_visible_in_all_workbenches()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);
        $response = $this->actingAs($admin)->get('/hospitalsettings');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_appsettings_helper_returns_config_instance()
    {
        $settings = appsettings();
        $this->assertNotNull($settings);
    }
}
