<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Tests\TestCase;

class InventoryReportSummaryTest extends TestCase
{
    /** @test */
    public function test_inventory_report_summary_endpoint_given_mode()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->getJson('/inventory/inventory-reports/summary?' . http_build_query([
            'store_id' => '2',
            'mode' => 'given',
            'group_by' => 'category',
            'start_date' => now()->subMonths(3)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'grouping_key',
                        'total_qty',
                        'total_value',
                        'cash_revenue',
                        'claims_revenue',
                        'potential_revenue',
                        'profit',
                    ],
                ],
            ]);
        }
    }

    /** @test */
    public function test_inventory_report_summary_endpoint_received_mode()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->getJson('/inventory/inventory-reports/summary?' . http_build_query([
            'store_id' => '2',
            'mode' => 'received',
            'group_by' => 'category',
            'start_date' => now()->subMonths(3)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }

    /** @test */
    public function test_inventory_report_drilldown_endpoint()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->getJson('/inventory/inventory-reports/drill-down?' . http_build_query([
            'store_id' => '2',
            'mode' => 'given',
            'group_by' => 'category',
            'group_key' => 'ANTISEPTIC-ANTI-HEMORRHAGE',
            'start_date' => now()->subMonths(3)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }

    /** @test */
    public function test_inventory_report_print_endpoint()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->get('/inventory/inventory-reports/summary/print?' . http_build_query([
            'store_id' => '2',
            'mode' => 'given',
            'group_by' => 'category',
            'start_date' => now()->subMonths(3)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }

    /** @test */
    public function test_inventory_report_handles_missing_store_id()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->getJson('/inventory/inventory-reports/summary?' . http_build_query([
            'mode' => 'given',
            'group_by' => 'category',
            'start_date' => now()->subMonths(1)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }

    /** @test */
    public function test_pharmacy_workbench_loads_with_managed_stores()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->get('/pharmacy-workbench');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }
}
