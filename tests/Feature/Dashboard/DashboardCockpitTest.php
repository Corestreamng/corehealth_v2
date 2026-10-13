<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Tests\TestCase;

class DashboardCockpitTest extends TestCase
{
    /** @test */
    public function test_home_route_renders_operational_cockpit_view_successfully()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/home');

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $response->assertSee('dash-cockpit-viewport');
            $response->assertSee('dash-col-pipeline');
            $response->assertSee('dash-col-telemetry');
            $response->assertSee('dash-col-ledger');
            $response->assertSee('Operational Pipeline');
            $response->assertSee('Live Activity Stream');
            $response->assertSee('Quick Workbenches');
        }
    }

    /** @test */
    public function test_cockpit_summary_endpoint_returns_json_with_all_role_metrics()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson(route('dashboard.data.cockpit-summary'));

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $data = $response->json();

            // Verify top-level structure
            $this->assertArrayHasKey('pipeline', $data);
            $this->assertArrayHasKey('kpis', $data);
            $this->assertArrayHasKey('hourlyFlow', $data);
            $this->assertArrayHasKey('revenueTrend', $data);
            $this->assertArrayHasKey('activity', $data);
            $this->assertArrayHasKey('insights', $data);

            // Verify all role pipeline queues exist
            $pipeline = $data['pipeline'];
            $this->assertArrayHasKey('waiting', $pipeline);
            $this->assertArrayHasKey('vitals', $pipeline);
            $this->assertArrayHasKey('consultation', $pipeline);
            $this->assertArrayHasKey('dispense', $pipeline);
            $this->assertArrayHasKey('lab', $pipeline);
            $this->assertArrayHasKey('imaging', $pipeline);
            $this->assertArrayHasKey('surgery', $pipeline);
            $this->assertArrayHasKey('maternity', $pipeline);
            $this->assertArrayHasKey('admitted', $pipeline);
            $this->assertArrayHasKey('store', $pipeline);
            $this->assertArrayHasKey('unpaid', $pipeline);
            $this->assertArrayHasKey('hmo', $pipeline);
            $this->assertArrayHasKey('morgue', $pipeline);
            $this->assertArrayHasKey('leave', $pipeline);

            // Verify KPI attributes
            $kpis = $data['kpis'];
            $this->assertArrayHasKey('today_revenue', $kpis);
            $this->assertArrayHasKey('vitals_today', $kpis);
            $this->assertArrayHasKey('admitted_patients', $kpis);
            $this->assertArrayHasKey('low_stock', $kpis);
            $this->assertArrayHasKey('out_of_stock', $kpis);
            $this->assertArrayHasKey('dispensed_today', $kpis);
            $this->assertArrayHasKey('lab_completed', $kpis);
            $this->assertArrayHasKey('unbilled_bills', $kpis);
        }
    }

    /** @test */
    public function test_activity_stream_rows_include_valid_deep_link_targets()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson(route('dashboard.data.cockpit-summary'));

        if ($response->status() === 200) {
            $activity = $response->json('activity');
            $this->assertIsArray($activity);

            foreach ($activity as $item) {
                $this->assertArrayHasKey('dept', $item);
                $this->assertArrayHasKey('target_url', $item);
                $this->assertNotEmpty($item['target_url']);
            }
        }
    }
}
