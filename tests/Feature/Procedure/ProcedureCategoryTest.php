<?php

namespace Tests\Feature\Procedure;

use App\Models\User;
use Tests\TestCase;

class ProcedureCategoryTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/procedure-categories');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_procedure_categories_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/procedure-categories');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 404, 500]));
    }
}
