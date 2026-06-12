<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffCreationTest extends TestCase
{
    use RefreshDatabase;

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
            'status' => 1
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
        $response->assertStatus(302);

        // Assert user exists
        $user = User::where('email', 'john.doe@hms.com')->first();
        $this->assertNotNull($user);

        // Assert staff exists and number_of_children is 0
        $staff = Staff::where('user_id', $user->id)->first();
        $this->assertNotNull($staff);
        $this->assertEquals(0, $staff->number_of_children);
    }

    /** @test */
    public function it_generates_unique_emails_when_email_is_empty()
    {
        $this->actingAs($this->admin);

        // Create first John Doe with empty email
        $payload1 = [
            'is_admin' => $this->category->id,
            'surname' => 'Doe',
            'firstname' => 'John',
            'gender' => 'Male',
            'phone_number' => '1234567890',
            'clinic' => $this->clinic->id,
            'number_of_children' => '2',
            'password' => 'password123',
        ];

        $response1 = $this->post('/staff', $payload1);
        $response1->assertStatus(302);

        $user1 = User::where('firstname', 'John')->where('surname', 'Doe')->first();
        $this->assertNotNull($user1);
        $this->assertEquals('john.doe@hms.com', $user1->email);

        // Create second John Doe with empty email - should generate john.doe1@hms.com
        $payload2 = [
            'is_admin' => $this->category->id,
            'surname' => 'Doe',
            'firstname' => 'John',
            'gender' => 'Male',
            'phone_number' => '1234567891',
            'clinic' => $this->clinic->id,
            'number_of_children' => '3',
            'password' => 'password123',
        ];

        $response2 = $this->post('/staff', $payload2);
        $response2->assertStatus(302);

        $user2 = User::where('phone_number', '1234567891')->first()->user;
        $this->assertNotNull($user2);
        $this->assertEquals('john.doe1@hms.com', $user2->email);
    }

    /** @test */
    public function it_rolls_back_user_creation_if_staff_creation_fails()
    {
        $this->actingAs($this->admin);

        // Send invalid dob string that will pass controller rules (as there is no dob validation rule)
        // but fail on SQL database save because of invalid date format
        $payload = [
            'is_admin' => $this->category->id,
            'surname' => 'Doe',
            'firstname' => 'Alice',
            'gender' => 'Female',
            'phone_number' => '1234567892',
            'clinic' => $this->clinic->id,
            'number_of_children' => '1',
            'email' => 'alice.doe@hms.com',
            'password' => 'password123',
            'dob' => 'not-a-valid-date-string', // forces DB exception
        ];

        $response = $this->post('/staff', $payload);

        // It should catch the exception, rollback, and redirect back with error
        $response->assertStatus(302);

        // Verify User record was not created/persisted
        $this->assertDatabaseMissing('users', [
            'email' => 'alice.doe@hms.com',
        ]);
    }
}
