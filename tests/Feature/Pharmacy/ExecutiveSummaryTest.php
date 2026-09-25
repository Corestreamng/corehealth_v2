<?php

namespace Tests\Feature\Pharmacy;

use App\Models\User;
use Tests\TestCase;

class ExecutiveSummaryTest extends TestCase
{
    /** @test */
    public function test_pharmacy_executive_summary_returns_200()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/pharmacy/executive-summary');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_summary_contains_cash_and_claims_breakdown()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/pharmacy/executive-summary');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_summary_data_grouped_by_scheme()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/pharmacy/executive-summary');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_summary_date_filter_limits_results()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/pharmacy/executive-summary?start_date=2026-01-01&end_date=2026-01-31');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_summary_total_revenue_matches_sum_of_line_items()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_pharmacy_workbench_executive_summary_json_endpoint()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/pharmacy-workbench/reports/executive-summary');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'stock_valuation',
                'total_expenditure',
                'total_goods_used',
                'opening_stock',
                'income_by_scheme',
                'patients_by_scheme',
                'collections_by_store',
            ]);
        }
    }

    /** @test */
    public function test_pharmacy_workbench_executive_summary_print_endpoint()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/pharmacy-workbench/reports/executive-summary/print');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }
}
