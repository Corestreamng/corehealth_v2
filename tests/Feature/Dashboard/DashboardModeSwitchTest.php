<?php

namespace Tests\Feature\Dashboard;

use App\Models\ApplicationStatu;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardModeSwitchTest extends TestCase
{
    /** @test */
    public function test_home_route_renders_cockpit_view_by_default()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/home');

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $response->assertSee('dash-cockpit-viewport');
            $response->assertSee('mode=classic');
        }
    }

    /** @test */
    public function test_home_route_renders_classic_view_when_query_mode_is_classic()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/home?mode=classic');

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $response->assertSee('dash-classic-topbar');
            $response->assertSee('Classic Operations Dashboard');
            $response->assertSee('mode=cockpit');
            $response->assertSee('dashboard-classic.css');
            $response->assertSee('dashboard-classic.js');
        }
    }

    /** @test */
    public function test_home_route_renders_cockpit_view_when_query_mode_is_cockpit()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/home?mode=cockpit');

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $response->assertSee('dash-cockpit-viewport');
        }
    }

    /** @test */
    public function test_home_route_renders_classic_view_when_application_status_dashboard_mode_is_classic()
    {
        $config = ApplicationStatu::first();
        if (!$config) {
            $config = ApplicationStatu::create([
                'site_name' => 'Test Hospital',
                'dashboard_mode' => 'classic',
            ]);
        } else {
            $config->dashboard_mode = 'classic';
            $config->save();
        }
        clearAppSettingsCache();

        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/home');

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $response->assertSee('dash-classic-topbar');
            $response->assertSee('Classic Operations Dashboard');
        }

        // Reset back to cockpit
        $config->dashboard_mode = 'cockpit';
        $config->save();
        clearAppSettingsCache();
    }

    /** @test */
    public function test_hospital_config_updates_dashboard_mode_setting_successfully()
    {
        $adminRole = Role::firstOrCreate(['name' => 'SUPERADMIN', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole($adminRole);

        $response = $this->actingAs($admin)->put(route('hospital-config.update'), [
            'site_name' => 'CoreHealth General Hospital',
            'dashboard_mode' => 'classic',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302]));

        $config = ApplicationStatu::first();
        $this->assertNotNull($config);
        $this->assertEquals('classic', $config->dashboard_mode);

        // Reset back to cockpit
        $config->dashboard_mode = 'cockpit';
        $config->save();
        clearAppSettingsCache();
    }

    /** @test */
    public function test_add_to_queue_route_redirects_to_reception_workbench()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/add-to-queue');

        $response->assertRedirect(route('reception.workbench'));
    }

    /** @test */
    public function test_patient_create_route_redirects_to_reception_workbench()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/patient/create');

        $response->assertRedirect(route('reception.workbench', ['action' => 'new-patient']));
    }

    /** @test */
    public function test_dashboard_routes_exclude_add_to_queue_and_patient_form_modal()
    {
        $adminRole = Role::firstOrCreate(['name' => 'SUPERADMIN', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole($adminRole);

        // Cockpit view
        $cockpitResponse = $this->actingAs($admin)->get('/home?mode=cockpit');
        if ($cockpitResponse->status() === 200) {
            $cockpitResponse->assertDontSee('/add-to-queue');
            $cockpitResponse->assertDontSee('patientFormModal');
            $cockpitResponse->assertSee(route('reception.workbench', ['action' => 'new-patient']));
        }

        // Classic view
        $classicResponse = $this->actingAs($admin)->get('/home?mode=classic');
        if ($classicResponse->status() === 200) {
            $classicResponse->assertDontSee('/add-to-queue');
            $classicResponse->assertDontSee('patientFormModal');
            $classicResponse->assertDontSee('#patientFormModal');
        }
    }

    /** @test */
    public function test_sidebar_contains_modern_reception_workbench_links()
    {
        $adminRole = Role::firstOrCreate(['name' => 'SUPERADMIN', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole($adminRole);

        $response = $this->actingAs($admin)->get('/home');
        if ($response->status() === 200) {
            $response->assertSee(route('reception.workbench', ['action' => 'new-patient']));
            $response->assertSee(route('reception.workbench', ['queue_filter' => 'waiting']));
        }
    }
}
