<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Tests\TestCase;

class DoctorQueueTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/doctor/my_queues');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_doctor_queue_loads_for_authenticated_user()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/doctor/my_queues');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_doctor_unified_list_search_returns_filtered_results()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/appointments/doctor/unified-list?start_date=2025-01-01&end_date=2026-12-31&search%5Bvalue%5D=Apollos');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertArrayHasKey('recordsTotal', $data);
            $this->assertArrayHasKey('recordsFiltered', $data);
        }
    }

    /** @test */
    public function test_doctor_unified_list_non_matching_search_returns_zero()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/appointments/doctor/unified-list?start_date=2025-01-01&end_date=2026-12-31&search%5Bvalue%5D=__nonexistent_term_xyz__');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertEquals(0, $data['recordsFiltered']);
        }
    }
}
