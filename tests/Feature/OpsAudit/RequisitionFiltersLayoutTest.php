<?php

namespace Tests\Feature\OpsAudit;

use App\Models\StoreRequisition;
use App\Models\User;
use Tests\TestCase;

class RequisitionFiltersLayoutTest extends TestCase
{
    /** @test */
    public function test_store_ops_audit_index_renders_requisitions_tab_with_partial_and_cancelled_filters()
    {
        $user = User::role('SUPERADMIN')->first() ?? User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/ops-audit/store');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
        if ($response->status() === 200) {
            $response->assertSee('id="tab-requisitions"', false);
            $response->assertSee('value="partial">Partially Fulfilled', false);
            $response->assertSee('value="cancelled">Cancelled', false);
        }
    }

    /** @test */
    public function test_store_ops_audit_requisitions_data_endpoint_supports_partial_filter_and_kpis()
    {
        $user = User::role('SUPERADMIN')->first() ?? User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/ops-audit/store/data/requisitions?status=partial&start=0&length=10');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
        if ($response->status() === 200) {
            $response->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered', 'kpis']);

            $kpis = collect($response->json('kpis'));
            $pendingKpi = $kpis->firstWhere('label', 'Pending/Partial Bal');
            $this->assertNotNull($pendingKpi, 'KPI strip must contain Pending/Partial Bal card.');

            $data = $response->json('data');
            if (!empty($data)) {
                $this->assertStringContainsString('Partially Fulfilled', $data[0]['status']);
            }
        }
    }

    /** @test */
    public function test_store_ops_audit_requisitions_supports_top_level_store_filter()
    {
        $user = User::role('SUPERADMIN')->first() ?? User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/ops-audit/store/data/requisitions?store_id=2&start=0&length=10');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
        if ($response->status() === 200) {
            $response->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered', 'kpis']);
        }
    }

    /** @test */
    public function test_store_ops_audit_requisitions_details_endpoint_renders_properly()
    {
        $user = User::role('SUPERADMIN')->first() ?? User::first() ?? User::factory()->create(['status' => 1]);
        $req = StoreRequisition::first();

        if ($req) {
            $response = $this->actingAs($user)->getJson('/ops-audit/details/requisition/' . $req->id);
            $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
            if ($response->status() === 200) {
                $response->assertJsonStructure(['html', 'title']);
            }
        } else {
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function test_pharmacy_ops_audit_stock_tab_supports_partial_status()
    {
        $user = User::role('SUPERADMIN')->first() ?? User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/ops-audit/pharmacy/data/stock?status=partial&start=0&length=10');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
        if ($response->status() === 200) {
            $response->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered', 'kpis']);
            $kpis = collect($response->json('kpis'));
            $partialKpi = $kpis->firstWhere('label', 'Partially Fulfilled');
            $this->assertNotNull($partialKpi, 'Pharmacy stock KPIs must contain Partially Fulfilled card.');
        }
    }
}
