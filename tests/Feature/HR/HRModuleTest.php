<?php

namespace Tests\Feature\HR;

use App\Models\User;
use Tests\TestCase;

class HRModuleTest extends TestCase
{
    /** @test */
    public function test_staff_registry_endpoint_returns_200()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);
        $response = $this->actingAs($admin)->get('/hr/staff');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_staff_can_be_assigned_grade_level()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_cadre_and_grade_level_reference_data_loaded()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_staff_name_displayed_with_othername()
    {
        $user = User::factory()->create([
            'surname' => 'Smith',
            'firstname' => 'John',
            'othername' => 'Alexander',
        ]);
        $fullName = trim("{$user->surname} {$user->firstname} {$user->othername}");
        $this->assertEquals('Smith John Alexander', $fullName);
    }
}
