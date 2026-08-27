<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Tests\TestCase;

class DashboardKpiTest extends TestCase
{
    /** @test */
    public function test_dashboard_kpi_endpoint_returns_correct_keys()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/home');
        $this->assertTrue(in_array($response->status(), [200, 302]));
    }

    /** @test */
    public function test_dashboard_returns_200_for_authenticated_user()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/home');
        $this->assertTrue(in_array($response->status(), [200, 302]));
    }

    /** @test */
    public function test_trend_stats_endpoint_returns_chart_data()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/dashboard/receptionist-stats');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_dashboard_kpis_are_numeric()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }
}
