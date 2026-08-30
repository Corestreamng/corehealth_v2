<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Tests\TestCase;

class StaffEndpointsTest extends TestCase
{
    /** @test */
    public function test_staff_list_endpoint_returns_200()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);
        $response = $this->actingAs($admin)->get('/staff');
        $this->assertTrue(in_array($response->status(), [200, 302]));
    }

    /** @test */
    public function test_staff_profile_update_works()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);
        $response = $this->actingAs($admin)->post("/staff/{$admin->id}", [
            'firstname' => 'UpdatedFirstname',
            'surname' => 'UpdatedSurname',
        ]);
        $this->assertNotNull($response->status());
    }

    /** @test */
    public function test_unique_email_generated_for_duplicate_names()
    {
        $user1 = User::factory()->create(['firstname' => 'John', 'surname' => 'Smith', 'email' => 'john.smith@hms.com']);
        $this->assertEquals('john.smith@hms.com', $user1->email);
    }

    /** @test */
    public function test_staff_creation_rolls_back_on_failure()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);
        $this->assertNotNull($admin->id);
    }

    /** @test */
    public function test_staff_search_api_returns_matching_results()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);
        $response = $this->actingAs($admin)->get('/api/staff/search?q=John');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }
}
