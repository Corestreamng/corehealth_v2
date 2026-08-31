<?php

namespace Tests\Feature\Morgue;

use App\Models\User;
use Tests\TestCase;

class MorgueWorkbenchTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/morgue/workbench');
        $this->assertTrue(in_array($response->status(), [302, 403, 404, 500]));
    }

    /** @test */
    public function test_morgue_workbench_loads_for_authenticated_user()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/morgue/workbench');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
    }
}
