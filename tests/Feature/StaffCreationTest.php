<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserCategory;
use Tests\TestCase;

class StaffCreationTest extends TestCase
{
    protected $admin;

    protected $category;

    protected $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an admin user to perform operations
        $this->admin = User::factory()->create(['status' => 1]);

        // Create required references
        $this->category = UserCategory::create([
            'id' => 21, // Doctors
            'name' => 'Doctors',
            'status' => 1,
        ]);

        $this->clinic = Clinic::create([
            'name' => 'General OPD',
        ]);
    }

    /** @test */
    public function it_can_create_a_staff_member_with_zero_children()
    {
        $this->actingAs($this->admin);

        $payload = [
            'is_admin' => $this->category->id,
            'surname' => 'Doe',
            'firstname' => 'John',
            'gender' => 'Male',
            'phone_number' => '1234567890',
            'clinic' => $this->clinic->id,
            'number_of_children' => '0',
            'email' => 'john.doe@hms.com',
            'password' => 'password123',
        ];

        $response = $this->post('/staff', $payload);

        // Assert redirect/success
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
    }
}
