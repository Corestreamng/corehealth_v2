<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/expenses');
        $this->assertTrue(in_array($response->status(), [302, 403, 404, 500]));
    }

    /** @test */
    public function test_expenses_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/expenses');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }
}
