<?php

namespace Tests\Feature\Config;

use App\Models\User;
use Tests\TestCase;

class BankControllerTest extends TestCase
{
    /** @test */
    public function test_active_banks_endpoint_accessible_by_authenticated_users()
    {
        $user = User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->getJson('/banks/active');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'banks'
        ]);
    }
}
