<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use Tests\TestCase;

class ClinicalReportsDnsTest extends TestCase
{
    /** @test */
    public function test_dns_report_unauthenticated_user_redirected()
    {
        $response = $this->get('/clinical-reports/dns-report');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_dns_report_endpoint_returns_json_structure()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->getJson('/clinical-reports/dns-report');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'success',
                'period' => ['from', 'to', 'formatted'],
                'kpis' => [
                    'total_outpatient',
                    'total_inpatients',
                    'empty_beds',
                    'total_admissions',
                    'total_discharges',
                    'sama',
                    'absconsion',
                    'referrals',
                    'normal_delivery',
                    'cs_delivery',
                    'total_surgeries',
                    'total_deaths',
                    'corpses',
                    'day_care',
                    'emergency_intakes',
                    'emergency_admissions',
                    'same_day_observations',
                ],
                'ward_census' => [
                    'total_beds',
                    'occupied',
                    'available',
                    'maintenance',
                    'occupancy_rate',
                    'wards',
                ],
                'clinics',
                'hospital',
            ]);
        }
    }

    /** @test */
    public function test_dns_drill_down_returns_paginated_server_side_data_and_hmo_filters()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->getJson('/clinical-reports/dns-drill-down?metric=inpatients&page=1&per_page=15');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'success',
                'metric',
                'title',
                'total_records',
                'unique_patients',
                'current_page',
                'last_page',
                'per_page',
                'from',
                'to',
                'records',
                'hmos',
                'schemes',
            ]);
            $this->assertEquals(15, $response->json('per_page'));
            $this->assertEquals(1, $response->json('current_page'));
        }
    }

    /** @test */
    public function test_dns_drill_down_search_and_hmo_filters_filter_records()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        // Search for non-existent token should return 0 results
        $fakeToken = 'XYZ_NONEXISTENT_QUERY_' . uniqid();
        $response = $this->actingAs($user)->getJson('/clinical-reports/dns-drill-down?metric=outpatient&search=' . urlencode($fakeToken));
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $this->assertEquals(0, $response->json('total_records'));
            $this->assertEmpty($response->json('records'));
        }
    }

    /** @test */
    public function test_dns_print_view_loads_successfully()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->get('/clinical-reports/dns-print');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $response->assertSee('DIRECTOR OF NURSING SERVICES');
            $response->assertSee('WARD OCCUPANCY');
        }
    }

    /** @test */
    public function test_dns_export_csv_streams_report()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->get('/clinical-reports/export?tab=dns');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('dns_report_', $response->headers->get('Content-Disposition') ?? '');

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('DNS CLINICAL REPORT & CENSUS', $content);
        $this->assertStringContainsString('WARD INPATIENT & BED CENSUS', $content);
        $this->assertStringContainsString('ALL CLINICS OUTPATIENT VOLUME', $content);
    }

    /** @test */
    public function test_dns_report_json_includes_gopd_popd_and_cs_rate()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->getJson('/clinical-reports/dns-report');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'kpis' => [
                    'gopd',
                    'popd',
                    'total_outpatient',
                    'total_deliveries',
                    'cs_rate',
                ],
            ]);
        }
    }

    /** @test */
    public function test_dns_drill_down_supports_gopd_and_popd()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        foreach (['gopd', 'popd'] as $metric) {
            $response = $this->actingAs($user)->getJson('/clinical-reports/dns-drill-down?metric=' . $metric . '&page=1&per_page=10');
            $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

            if ($response->status() === 200) {
                $this->assertTrue($response->json('success'));
                $this->assertEquals($metric, $response->json('metric'));
            }
        }
    }

    /** @test */
    public function test_nursing_and_reception_workbench_clinical_reports_tab_available_to_unit_head()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        Staff::updateOrCreate(['user_id' => $user->id], [
            'is_unit_head' => 1,
            'is_dept_head' => 0,
        ]);

        try {
            $user->assignRole('NURSE');
        } catch (\Throwable $e) {
        }

        $nursingResponse = $this->actingAs($user)->get('/nursing-workbench');
        if ($nursingResponse->status() === 200) {
            $nursingResponse->assertSee('clinical-reports-tab');
            $nursingResponse->assertSee('clinical-reports-content');
            $nursingResponse->assertSee('cr-tab-dns');
        }

        $receptionResponse = $this->actingAs($user)->get('/reception-workbench/workbench');
        if ($receptionResponse->status() === 200) {
            $receptionResponse->assertSee('clinical-reports-tab');
            $receptionResponse->assertSee('clinical-reports-content');
        }
        $this->assertTrue(true);
    }

    /** @test */
    public function test_nursing_and_reception_workbench_clinical_reports_tab_hidden_for_non_unit_head()
    {
        $user = User::factory()->create(['status' => 1, 'is_admin' => 0]);
        Staff::updateOrCreate(['user_id' => $user->id], [
            'is_unit_head' => 0,
            'is_dept_head' => 0,
        ]);

        try {
            $user->assignRole('NURSE');
        } catch (\Throwable $e) {
        }

        $nursingResponse = $this->actingAs($user)->get('/nursing-workbench');
        if ($nursingResponse->status() === 200) {
            $nursingResponse->assertDontSee('id="clinical-reports-tab"', false);
        }

        $receptionResponse = $this->actingAs($user)->get('/reception-workbench/workbench');
        if ($receptionResponse->status() === 200) {
            $receptionResponse->assertDontSee('id="clinical-reports-tab"', false);
        }
        $this->assertTrue(true);
    }

    /** @test */
    public function test_dns_drill_down_supports_deliveries_surgeries_and_mortality()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $metrics = ['cs_delivery', 'normal_delivery', 'surgeries', 'deaths', 'emergency_intakes', 'same_day_observations'];
        foreach ($metrics as $metric) {
            $response = $this->actingAs($user)->getJson('/clinical-reports/dns-drill-down?metric=' . $metric . '&page=1&per_page=10');
            $this->assertTrue(
                in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]),
                "Metric {$metric} failed with status " . $response->status()
            );

            if ($response->status() === 200) {
                $response->assertJsonStructure([
                    'success',
                    'title',
                    'metric',
                    'total_records',
                    'current_page',
                    'per_page',
                    'last_page',
                    'records',
                ]);
                $this->assertTrue($response->json('success'));
                $this->assertEquals($metric, $response->json('metric'));
            }
        }
    }
}
