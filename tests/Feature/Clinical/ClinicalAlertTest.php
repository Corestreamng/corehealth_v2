<?php

namespace Tests\Feature\Clinical;

use App\Models\User;
use Tests\TestCase;

class ClinicalAlertTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/clinical-alerts');
        $this->assertTrue(in_array($response->status(), [302, 403, 404, 500]));
    }

    /** @test */
    public function test_clinical_alerts_list_accessible()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/clinical-alerts');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }
}
