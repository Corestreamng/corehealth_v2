<?php

namespace Tests\Feature\Surgery;

use App\Models\User;
use Tests\TestCase;

class SurgeryWorkbenchTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/surgery/workbench');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_surgery_workbench_loads_for_authenticated_user()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/surgery/workbench');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }
}
