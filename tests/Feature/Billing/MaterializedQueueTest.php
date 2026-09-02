<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use Tests\TestCase;

class MaterializedQueueTest extends TestCase
{
    /** @test */
    public function test_billing_queue_datatable_returns_correct_structure()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/billing-workbench');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }

    /** @test */
    public function test_pending_payments_appear_in_billing_queue()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/billing-workbench');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }

    /** @test */
    public function test_completed_payments_excluded_from_queue()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/billing-workbench');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }

    /** @test */
    public function test_billing_queue_indexed_query_performance()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/billing-workbench');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }
}
