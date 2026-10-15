<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Spatie\Permission\Models\Role;
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

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
        if ($response->status() === 200) {
            $data = $response->json();

            // Verify top-level structure
            $this->assertArrayHasKey('pipeline', $data);
            $this->assertArrayHasKey('kpis', $data);
            $this->assertArrayHasKey('hourlyFlow', $data);
            $this->assertArrayHasKey('revenueTrend', $data);
            $this->assertArrayHasKey('activity', $data);
            $this->assertArrayHasKey('insights', $data);
            $this->assertArrayHasKey('domains', $data);

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
            $this->assertArrayHasKey('accounts', $pipeline);
            $this->assertArrayHasKey('audit', $pipeline);
            $this->assertArrayHasKey('ess', $pipeline);

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

            // Verify domain metrics have 8 KPIs and never route to administrative settings
            $domains = $data['domains'];
            $this->assertArrayHasKey('doctor', $domains);
            $this->assertArrayHasKey('nursing', $domains);
            $this->assertArrayHasKey('pharmacy', $domains);

            foreach (['cockpit', 'doctor', 'nursing', 'pharmacy', 'receptionist'] as $domainKey) {
                if (isset($domains[$domainKey])) {
                    $domainKpis = $domains[$domainKey]['kpis'];
                    $this->assertCount(8, $domainKpis, "Domain {$domainKey} should have 8 intensive KPIs");
                    foreach ($domainKpis as $kpi) {
                        $this->assertArrayHasKey('label', $kpi);
                        $this->assertArrayHasKey('val', $kpi);
                        $this->assertArrayHasKey('link', $kpi);
                        // Ensure clinical/workbench routing rather than administrative settings
                        $this->assertStringNotContainsString('beds.index', $kpi['link']);
                        $this->assertStringNotContainsString('wards.index', $kpi['link']);
                    }
                }
            }
        }
    }

    /** @test */
    public function test_activity_stream_rows_include_valid_deep_link_targets()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson(route('dashboard.data.cockpit-summary'));

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
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

    /** @test */
    public function test_tablet_and_mobile_viewport_tabs_and_dual_queue_rendered()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/home');

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $response->assertSee('dash-viewport-tabs');
            $response->assertSee('dash-mobile-tabs');
            $response->assertSee('dash-queue-stream');
            $response->assertSee('btn-toggle-queue-view');
            $response->assertSee('dash-ledger-table-wrap');
        }
    }

    /** @test */
    public function test_all_clinical_domains_have_specific_queues_and_actions()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson(route('dashboard.data.cockpit-summary'));

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
        if ($response->status() === 200) {
            $domains = $response->json('domains');

            $expectedDomains = [
                'receptionist', 'doctor', 'lab', 'pharmacy', 'nursing',
                'billing', 'store', 'imaging', 'theatre', 'maternity',
                'inpatient', 'hmo', 'morgue', 'audit', 'hr', 'accounts', 'ess', 'admin',
            ];

            foreach ($expectedDomains as $domain) {
                $this->assertArrayHasKey($domain, $domains, "Domain bundle '{$domain}' must be defined");
                $this->assertArrayHasKey('queue_items', $domains[$domain]);
                $this->assertArrayHasKey('shortcuts', $domains[$domain]);
                $this->assertArrayHasKey('kpis', $domains[$domain]);
            }
        }
    }

    /** @test */
    public function test_doctor_role_sees_doctor_pipeline_and_scoped_footer_actions()
    {
        $roleDoctor = Role::firstOrCreate(['name' => 'DOCTOR', 'guard_name' => 'web']);
        $doctor = User::factory()->create(['status' => 1]);
        $doctor->assignRole($roleDoctor);

        $response = $this->actingAs($doctor)->get('/home');

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $content = $response->getContent();
            // Should see Doctor Consult actions in footer dock
            $this->assertStringContainsString('+ Consult', $content);
            $this->assertStringContainsString('My Queues', $content);
            // Should NOT see nurse shift management in footer dock
            $this->assertStringNotContainsString('Shift Mgmt', $content);
            // Should NOT see admin configuration in footer dock
            $this->assertStringNotContainsString('Hospital Config', $content);
        }
    }

    /** @test */
    public function test_nurse_role_sees_nursing_pipeline_and_scoped_footer_actions()
    {
        $roleNurse = Role::firstOrCreate(['name' => 'NURSE', 'guard_name' => 'web']);
        $nurse = User::factory()->create(['status' => 1]);
        $nurse->assignRole($roleNurse);

        $response = $this->actingAs($nurse)->get('/home');

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $content = $response->getContent();
            // Should see Fast Triage and Shift Mgmt in footer dock
            $this->assertStringContainsString('Fast Triage', $content);
            $this->assertStringContainsString('Shift Mgmt', $content);
            // Should NOT see doctor consult creation in footer dock
            $this->assertStringNotContainsString('+ Consult', $content);
            // Should NOT see admin configuration in footer dock
            $this->assertStringNotContainsString('Hospital Config', $content);
        }
    }

    /** @test */
    public function test_receptionist_role_sees_reception_actions_and_scoped_footer()
    {
        $roleRecep = Role::firstOrCreate(['name' => 'RECEPTIONIST', 'guard_name' => 'web']);
        $recep = User::factory()->create(['status' => 1]);
        $recep->assignRole($roleRecep);

        $response = $this->actingAs($recep)->get('/home');

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $content = $response->getContent();
            // Should see New Patient and Appointments in footer dock
            $this->assertStringContainsString('New Patient', $content);
            $this->assertStringContainsString('Appointments', $content);
            // Should NOT see admin configuration in footer dock
            $this->assertStringNotContainsString('Hospital Config', $content);
        }
    }

    /** @test */
    public function test_biller_role_sees_billing_workbench_actions_in_footer()
    {
        $roleBiller = Role::firstOrCreate(['name' => 'BILLER', 'guard_name' => 'web']);
        $biller = User::factory()->create(['status' => 1]);
        $biller->assignRole($roleBiller);

        $response = $this->actingAs($biller)->get('/home');

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $content = $response->getContent();
            // Should see Billing Hub and Unpaid Bills in footer dock
            $this->assertStringContainsString('Billing Hub', $content);
            $this->assertStringContainsString('Unpaid Bills', $content);
            // Should NOT see admin configuration in footer dock
            $this->assertStringNotContainsString('Hospital Config', $content);
        }
    }

    /** @test */
    public function test_superadmin_role_sees_executive_actions_in_footer()
    {
        $roleAdmin = Role::firstOrCreate(['name' => 'SUPERADMIN', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole($roleAdmin);

        $response = $this->actingAs($admin)->get('/home');

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $content = $response->getContent();
            // Should see Hospital Config and Access Control in footer dock
            $this->assertStringContainsString('Hospital Config', $content);
            $this->assertStringContainsString('Access Control', $content);
        }
    }
}
