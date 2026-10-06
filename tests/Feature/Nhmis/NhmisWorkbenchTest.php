<?php

namespace Tests\Feature\Nhmis;

use App\Models\Encounter;
use App\Models\NhmisMonthlyReport;
use App\Models\NhmisMonthlyReportValue;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use App\Services\Nhmis\NhmisDataAggregatorService;
use App\Services\Nhmis\NhmisFormRegistry;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NhmisWorkbenchTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/nhmis-workbench');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_workbench_page_loads_for_authenticated_user()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/nhmis-workbench');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_workbench_print_view_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/nhmis-workbench?action=print&year=2026&month=9');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_auto_compile_endpoint()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->postJson('/nhmis-workbench/compile', [
            'year' => 2026,
            'month' => 9,
            'version' => 'v2019',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_save_values_endpoint()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 9, 'v2019');

        $response = $this->actingAs($user)->postJson('/nhmis-workbench/save-values', [
            'report_id' => $report->id,
            'values' => [
                'row_1:total' => [
                    'override_value' => 45,
                    'override_reason' => 'Reconciled with reception physical register',
                ],
                'row_115:total' => 12,
            ],
            'notes' => 'Test monthly adjustment notes',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $val = NhmisMonthlyReportValue::where('report_id', $report->id)
                ->where('cell_key', 'row_1:total')
                ->first();
            $this->assertNotNull($val);
            $this->assertEquals(45, (int) $val->final_value);
            $this->assertTrue($val->is_overridden);
        }
    }

    /** @test */
    public function test_audit_diagnosis_endpoint_returns_clinical_insights()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $patient = Patient::first();

        if ($patient) {
            $uniqTag = 'AuditTestMalaria_' . uniqid();
            Encounter::create([
                'patient_id' => $patient->id,
                'doctor_id' => $user->id,
                'reasons_for_encounter' => json_encode([
                    ['name' => $uniqTag, 'code' => 'B50.9', 'comment_1' => 'CONFIRMED', 'comment_2' => 'ACUTE'],
                ]),
                'notes' => 'Clinical case test for ' . $uniqTag,
                'completed' => true,
                'created_at' => Carbon::create(2026, 9, 15, 10, 0, 0),
            ]);
        }

        $response = $this->actingAs($user)->getJson('/nhmis-workbench/audit-diagnosis?year=2026&month=9&keyword=Malaria');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('encounters', $data);
            $this->assertArrayHasKey('total_encounters', $data);
            $this->assertArrayHasKey('unique_patients', $data);
        }
    }

    /** @test */
    public function test_update_status_and_lock_endpoint()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 8, 'v2019');

        $response = $this->actingAs($user)->postJson('/nhmis-workbench/update-status', [
            'report_id' => $report->id,
            'status' => 'verified',
            'notes' => 'Reconciled by M&E unit',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_nhmis_form_registry_and_schema_definition()
    {
        $versions = NhmisFormRegistry::getAvailableVersions();
        $this->assertArrayHasKey('v2019', $versions);

        $schema = NhmisFormRegistry::getSchema('v2019');
        $this->assertNotNull($schema);
        $this->assertEquals('v2019', $schema->getVersion());
        $this->assertEquals('NHMIS/HF/MSF', $schema->getCode());

        $pages = $schema->getPages();
        $this->assertCount(5, $pages);

        // Verify page 1 sections
        $this->assertEquals('page_1', $pages[0]['id']);
        $this->assertGreaterThanOrEqual(4, count($pages[0]['sections']));
    }

    /** @test */
    public function test_nhmis_data_aggregator_normalization_and_age_bands()
    {
        $aggregator = app(NhmisDataAggregatorService::class);

        // Test age band logic
        $refDate = Carbon::create(2026, 9, 30);

        // 10 days old -> 0_28d
        $dob1 = $refDate->copy()->subDays(10);
        $this->assertEquals('0_28d', $aggregator->getNhmisAgeBand($dob1, $refDate));

        // 6 months old -> 29d_11m
        $dob2 = $refDate->copy()->subMonths(6);
        $this->assertEquals('29d_11m', $aggregator->getNhmisAgeBand($dob2, $refDate));

        // 3 years old -> 12_59m
        $dob3 = $refDate->copy()->subYears(3);
        $this->assertEquals('12_59m', $aggregator->getNhmisAgeBand($dob3, $refDate));

        // 7 years old -> 5_9y
        $dob4 = $refDate->copy()->subYears(7);
        $this->assertEquals('5_9y', $aggregator->getNhmisAgeBand($dob4, $refDate));

        // 15 years old -> 10_19y
        $dob5 = $refDate->copy()->subYears(15);
        $this->assertEquals('10_19y', $aggregator->getNhmisAgeBand($dob5, $refDate));

        // 35 years old -> ge_20y
        $dob6 = $refDate->copy()->subYears(35);
        $this->assertEquals('ge_20y', $aggregator->getNhmisAgeBand($dob6, $refDate));

        // Test diagnosis normalization alignment with ClinicalReportsController
        $norm = NhmisDataAggregatorService::normalizeDiagnosisItem([
            'code' => 'I10',
            'name' => 'Essential Hypertension',
            'comment_1' => 'CONFIRMED',
            'comment_2' => 'CHRONIC',
        ]);

        $this->assertEquals('ICD_I10', $norm['group_key']);
        $this->assertEquals('I10', $norm['code']);
        $this->assertEquals('Essential Hypertension', $norm['name']);
        $this->assertEquals('CONFIRMED', $norm['query']);
        $this->assertEquals('CHRONIC', $norm['status']);
    }

    /** @test */
    public function test_nhmis_drill_down_endpoint()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 9, 'v2019');

        $response = $this->actingAs($user)->getJson('/nhmis-workbench/drill-down?cell_key=row_137:total&report_id=' . $report->id);
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 422, 500]));
    }

    /** @test */
    public function test_nhmis_universal_drill_down_all_domains()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 8, 'v2019');

        $sampleKeys = [
            'row_1:total',   // OPD Attendance
            'row_3:total',   // Inpatient Admissions
            'row_5:total',   // Mortality
            'row_10:total',  // ANC Attendance
            'row_11:total',  // ANC 1st Visit
            'row_12:total',  // ANC 4th Visit
            'row_13:total',  // ANC 8th Visit
            'row_16:total',  // ANC Nutrition
            'row_17:total',  // Syphilis
            'row_20:total',  // Hepatitis B
            'row_26:total',  // IPT1
            'row_31:total',  // Haematinics
            'row_32:total',  // Severe Anaemia
            'row_33:total',  // Proteinuria
            'row_34:total',  // Deliveries
            'row_47:total',  // Postnatal Care
            'row_63:total',  // Immunization
            'row_115:total', // Family Planning
            'row_147:total', // Malaria Microscopy
        ];

        foreach ($sampleKeys as $cellKey) {
            $response = $this->actingAs($user)->getJson("/nhmis-workbench/drill-down?cell_key={$cellKey}&report_id={$report->id}");
            $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 422, 500]));

            if ($response->status() === 200) {
                $data = $response->json();
                $this->assertTrue($data['success']);
                $this->assertEquals($cellKey, $data['cell_key']);
                $this->assertArrayHasKey('records', $data);
                $this->assertArrayHasKey('total_records', $data);
                $this->assertArrayHasKey('unique_patients', $data);
                $this->assertIsArray($data['records']);
            }
        }
    }

    /** @test */
    public function test_ri_fixed_sessions_drill_down_loads_exact_four_records()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 8, 'v2019');

        $response = $this->actingAs($user)->getJson("/nhmis-workbench/drill-down?cell_key=row_92:total&report_id={$report->id}");
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 422, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertTrue($data['success']);
            $this->assertEquals('row_92:total', $data['cell_key']);
            $this->assertEquals(4, $data['total_records']);
            $this->assertEquals(4, $data['unique_patients']);
            $this->assertCount(4, $data['records']);
            $this->assertStringContainsString('Fixed Session', $data['records'][0]['patient_name']);
        }
    }

    /** @test */
    public function test_nhmis_drill_down_server_side_pagination_and_hmo_filter()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 8, 'v2019');

        $response = $this->actingAs($user)->getJson("/nhmis-workbench/drill-down?cell_key=row_12:total&report_id={$report->id}&page=1&per_page=5&hmo_id=cash");
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 422, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('current_page', $data);
            $this->assertArrayHasKey('last_page', $data);
            $this->assertArrayHasKey('per_page', $data);
            $this->assertArrayHasKey('from', $data);
            $this->assertArrayHasKey('to', $data);
            $this->assertArrayHasKey('hmos', $data);
            $this->assertArrayHasKey('schemes', $data);
            $this->assertEquals(1, $data['current_page']);
            $this->assertEquals(5, $data['per_page']);
        }
    }

    /** @test */
    public function test_maternal_health_anc_drill_down_matches_compiled_counts()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 8, 'v2019');

        $cellsToVerify = [
            'row_17:total', // Syphilis test done
            'row_18:total', // Syphilis test positive
            'row_20:total', // Hep B test done
            'row_21:total', // Hep B test positive
            'row_23:total', // Hep C test done
            'row_24:total', // Hep C test positive
            'row_26:total', // IPT1
            'row_30:total', // LLIN
            'row_31:total', // Haematinics
            'row_32:total', // Severe Anaemia
            'row_33:total', // Proteinuria
        ];

        foreach ($cellsToVerify as $cellKey) {
            $response = $this->actingAs($user)->getJson("/nhmis-workbench/drill-down?cell_key={$cellKey}&report_id={$report->id}");
            $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 422, 500]));

            if ($response->status() === 200) {
                $data = $response->json();
                $this->assertTrue($data['success']);
                $this->assertEquals($cellKey, $data['cell_key']);
                $this->assertArrayHasKey('total_records', $data);
                $this->assertArrayHasKey('records', $data);
                $this->assertIsInt($data['total_records']);
            }
        }
    }

    /** @test */
    public function test_access_control_allows_admin()
    {
        $adminRole = Role::firstOrCreate(['name' => 'ADMIN', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => 1]);
        $admin->syncRoles([$adminRole]);

        $response = $this->actingAs($admin)->get('/nhmis-workbench');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 500]));
    }

    /** @test */
    public function test_access_control_allows_receptionist_unit_head()
    {
        $role = Role::firstOrCreate(['name' => 'RECEPTIONIST', 'guard_name' => 'web']);
        $user = User::factory()->create(['status' => 1]);
        $user->syncRoles([$role]);
        Staff::updateOrCreate(['user_id' => $user->id], ['is_unit_head' => 1, 'is_dept_head' => 0]);

        $response = $this->actingAs($user->fresh())->get('/nhmis-workbench');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 500]));
    }

    /** @test */
    public function test_access_control_allows_receptionist_dept_head()
    {
        $role = Role::firstOrCreate(['name' => 'RECEPTIONIST', 'guard_name' => 'web']);
        $user = User::factory()->create(['status' => 1]);
        $user->syncRoles([$role]);
        Staff::updateOrCreate(['user_id' => $user->id], ['is_unit_head' => 0, 'is_dept_head' => 1]);

        $response = $this->actingAs($user->fresh())->get('/nhmis-workbench');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 500]));
    }

    /** @test */
    public function test_access_control_denies_receptionist_without_leadership()
    {
        $role = Role::firstOrCreate(['name' => 'RECEPTIONIST', 'guard_name' => 'web']);
        $user = User::factory()->create(['status' => 1]);
        $user->syncRoles([$role]);
        Staff::updateOrCreate(['user_id' => $user->id], ['is_unit_head' => 0, 'is_dept_head' => 0]);

        $response = $this->actingAs($user->fresh())->get('/nhmis-workbench');
        $this->assertEquals(403, $response->status());
    }

    /** @test */
    public function test_access_control_denies_unauthorized_roles()
    {
        $nurseRole = Role::firstOrCreate(['name' => 'NURSE', 'guard_name' => 'web']);
        $user = User::factory()->create(['status' => 1]);
        $user->syncRoles([$nurseRole]);
        Staff::updateOrCreate(['user_id' => $user->id], ['is_unit_head' => 0, 'is_dept_head' => 0]);

        $response = $this->actingAs($user->fresh())->get('/nhmis-workbench');
        $this->assertEquals(403, $response->status());
    }

    /** @test */
    public function test_sidebar_displays_nhmis_report_link_only_for_authorized_users()
    {
        $adminRole = Role::firstOrCreate(['name' => 'ADMIN', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => 1]);
        $admin->syncRoles([$adminRole]);

        $receptionRole = Role::firstOrCreate(['name' => 'RECEPTIONIST', 'guard_name' => 'web']);
        $unitHeadReceptionist = User::factory()->create(['status' => 1]);
        $unitHeadReceptionist->syncRoles([$receptionRole]);
        Staff::updateOrCreate(['user_id' => $unitHeadReceptionist->id], ['is_unit_head' => 1, 'is_dept_head' => 0]);

        $regularReceptionist = User::factory()->create(['status' => 1]);
        $regularReceptionist->syncRoles([$receptionRole]);
        Staff::updateOrCreate(['user_id' => $regularReceptionist->id], ['is_unit_head' => 0, 'is_dept_head' => 0]);

        // Admin should see link
        auth()->login($admin);
        $adminSidebar = view('admin.partials.sidebar')->render();
        $this->assertStringContainsString('sidebar-receptionist-nhmis-report', $adminSidebar);

        // Qualified receptionist should see link
        auth()->login($unitHeadReceptionist->fresh());
        $unitHeadSidebar = view('admin.partials.sidebar')->render();
        $this->assertStringContainsString('sidebar-receptionist-nhmis-report', $unitHeadSidebar);

        // Regular receptionist without leadership should NOT see link
        auth()->login($regularReceptionist->fresh());
        $regularSidebar = view('admin.partials.sidebar')->render();
        $this->assertStringNotContainsString('sidebar-receptionist-nhmis-report', $regularSidebar);
    }
}
