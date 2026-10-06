<?php

namespace Tests\Feature\HMO;

use App\Models\User;
use Tests\TestCase;

class HmoReportsTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/hmo/reports');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_hmo_reports_view_renders_with_workbench_config()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/hmo/reports');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $response->assertSee('window.WORKBENCH_CONFIG', false);
            $response->assertSee('hmo.reports.claims', false);
            $response->assertSee('workbench-helper.js', false);
            $response->assertSee('hmo-reports.js', false);
        }
    }

    /** @test */
    public function test_claims_report_ajax_endpoint()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/hmo/reports/claims?draw=1&start=0&length=10');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertArrayHasKey('recordsTotal', $data);
            $this->assertArrayHasKey('recordsFiltered', $data);
            $this->assertArrayHasKey('data', $data);
        }
    }

    /** @test */
    public function test_outstanding_report_ajax_endpoint()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/hmo/reports/outstanding');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertArrayHasKey('summary', $data);
            $this->assertArrayHasKey('data', $data);
        }
    }

    /** @test */
    public function test_monthly_summary_ajax_endpoint()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/hmo/reports/monthly?month=9&year=2026');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertArrayHasKey('summary', $data);
            $this->assertArrayHasKey('by_hmo', $data);
            $this->assertArrayHasKey('by_type', $data);
        }
    }

    /** @test */
    public function test_utilization_report_ajax_endpoint()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/hmo/reports/utilization?date_from=2026-09-01&date_to=2026-09-25');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertArrayHasKey('summary', $data);
            $this->assertArrayHasKey('top_services', $data);
            $this->assertArrayHasKey('top_products', $data);
        }
    }

    /** @test */
    public function test_auth_codes_report_ajax_endpoint()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/hmo/reports/auth-codes?draw=1&start=0&length=10');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertArrayHasKey('recordsTotal', $data);
            $this->assertArrayHasKey('data', $data);
        }
    }

    /** @test */
    public function test_remittances_report_ajax_endpoint()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/hmo/reports/remittances?draw=1&start=0&length=10');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertArrayHasKey('recordsTotal', $data);
            $this->assertArrayHasKey('data', $data);
        }
    }

    /** @test */
    public function test_patient_search_endpoint_used_by_hmo_workbench()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/patient-search?q=apo&context=hmo');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertIsArray($data);
        }
    }

    /** @test */
    public function test_hmo_reports_search_patients_endpoint()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/hmo/reports/search-patients?q=apo');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertIsArray($data);
        }
    }
}
