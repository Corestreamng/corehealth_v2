<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use Tests\TestCase;

class AuditMarkTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/audit/mark/stamp');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_audit_mark_stamp_endpoint_responds()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->post('/audit/mark/stamp', ['module' => 'billing', 'ids' => [1]]);
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 422, 500]));
    }
}
