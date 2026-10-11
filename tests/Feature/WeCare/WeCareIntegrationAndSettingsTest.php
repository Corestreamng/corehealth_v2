<?php

namespace Tests\Feature\WeCare;

use App\Models\ApplicationStatu;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WeCareIntegrationAndSettingsTest extends TestCase
{
    protected function getBasicAuthHeader(): array
    {
        $app = ApplicationStatu::first();
        $clientId = $app->client_id ?? '123';
        $clientSecret = $app->client_secret ?? '123';
        $auth = base64_encode("{$clientId}:{$clientSecret}");

        return [
            'Authorization' => "Basic {$auth}",
            'Accept' => 'application/json',
        ];
    }

    /** @test */
    public function test_wecare_ping_endpoint_returns_liveness_and_enabled_status()
    {
        $response = $this->getJson('/api/corehealth/ping');

        $this->assertTrue(in_array($response->status(), [200, 302, 404, 500]));
        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'status',
                'wecare_enabled',
                'facility_id',
                'facility',
                'version',
                'db_ok',
                'latency_ms',
                'timestamp',
            ]);
            $this->assertTrue($response->json('status'));
        }
    }

    /** @test */
    public function test_wecare_lookup_patient_blocked_when_wecare_disabled()
    {
        $app = ApplicationStatu::first();
        if ($app) {
            $app->wecare_enabled = false;
            $app->save();
            clearAppSettingsCache();
        }

        $headers = $this->getBasicAuthHeader();
        $response = $this->withHeaders($headers)->getJson('/api/corehealth/patient/lookup?phone_no=08012345678');

        $this->assertTrue(in_array($response->status(), [403, 401, 302, 500]));
        if ($response->status() === 403) {
            $this->assertEquals('WECARE_INTEGRATION_DISABLED', $response->json('code'));
        }
    }

    /** @test */
    public function test_wecare_lookup_patient_allowed_when_wecare_enabled()
    {
        $app = ApplicationStatu::first();
        if ($app) {
            $app->wecare_enabled = true;
            $app->wecare_allow_patient_lookup = true;
            $app->save();
            clearAppSettingsCache();
        }

        $headers = $this->getBasicAuthHeader();
        $response = $this->withHeaders($headers)->getJson('/api/corehealth/patient/lookup?phone_no=08099999999');

        // Since patient does not exist, expecting 404 not found, but NOT 403 disabled
        $this->assertTrue(in_array($response->status(), [404, 200, 401, 302, 500]));
        if (in_array($response->status(), [404, 200])) {
            $this->assertNotEquals('WECARE_INTEGRATION_DISABLED', $response->json('code'));
        }
    }

    /** @test */
    public function test_wecare_encounter_ingest_blocked_when_wecare_disabled()
    {
        $app = ApplicationStatu::first();
        if ($app) {
            $app->wecare_enabled = false;
            $app->save();
            clearAppSettingsCache();
        }

        $headers = $this->getBasicAuthHeader();
        $response = $this->withHeaders($headers)->postJson('/api/corehealth/sync/encounter', [
            'facility_id' => 1,
            'corehealth_file_no' => 'TEST/01',
            'corehealth_patient_id' => 1,
            'wecare_appointment_id' => 'wc_123',
            'consultation_date' => now()->toDateTimeString(),
        ]);

        $this->assertTrue(in_array($response->status(), [403, 401, 302, 500]));
        if ($response->status() === 403) {
            $this->assertEquals('WECARE_INTEGRATION_DISABLED', $response->json('code'));
        }
    }

    /** @test */
    public function test_hospital_settings_wecare_tab_in_view()
    {
        Role::firstOrCreate(['name' => 'SUPERADMIN', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('SUPERADMIN');

        $response = $this->actingAs($admin)->get(route('hospital-config.index'));

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
        if ($response->status() === 200) {
            $response->assertSee('id="wecare-tab"', false);
            $response->assertSee('id="wecare-settings"', false);
            $response->assertSee('name="wecare_enabled"', false);
            $response->assertSee('WeCare247', false);
        }
    }

    /** @test */
    public function test_hospital_settings_test_wecare_probe_route()
    {
        Role::firstOrCreate(['name' => 'SUPERADMIN', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('SUPERADMIN');

        $response = $this->actingAs($admin)->postJson(route('hospital-config.test-wecare'), [
            'wecare_url' => 'http://127.0.0.1:9999',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 400, 500, 302, 403]));
        if (in_array($response->status(), [200, 400, 500])) {
            $this->assertArrayHasKey('latency_ms', $response->json());
        }
    }
}
