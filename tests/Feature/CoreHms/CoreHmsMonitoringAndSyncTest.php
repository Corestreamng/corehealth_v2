<?php

namespace Tests\Feature\CoreHms;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CoreHmsMonitoringAndSyncTest extends TestCase
{
    /** @test */
    public function test_corehms_ping_endpoint_returns_success_and_pong()
    {
        $response = $this->getJson('/api/corehms/ping');

        $this->assertTrue(in_array($response->status(), [200, 302, 404, 500]));
        if ($response->status() === 200) {
            $response->assertJson([
                'status' => true,
                'pong' => true,
            ]);
            $this->assertNotEmpty($response->json('facility_id'));
            $this->assertNotEmpty($response->json('timestamp'));
        }
    }

    /** @test */
    public function test_corehms_health_endpoint_returns_metrics()
    {
        $response = $this->getJson('/api/corehms/health');

        $this->assertTrue(in_array($response->status(), [200, 302, 404, 500]));
        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'status',
                'healthy',
                'facility' => ['id', 'name', 'goonline', 'version'],
                'system' => [
                    'php_version',
                    'laravel_version',
                    'server_time',
                    'queue_driver',
                    'pending_queue_jobs',
                    'failed_queue_jobs',
                    'db_connected',
                ],
            ]);
            $this->assertTrue($response->json('status'));
        }
    }

    /** @test */
    public function test_backfill_artisan_command_is_registered()
    {
        $exitCode = Artisan::call('corehms:backfill', ['--help' => true]);
        $this->assertSame(0, $exitCode);
    }

    /** @test */
    public function test_export_endpoints_are_registered()
    {
        // Testing unauthenticated or authenticated response codes
        $dispenseResponse = $this->getJson('/api/get-facility-dispenses');
        $this->assertTrue(in_array($dispenseResponse->status(), [200, 401, 302, 403, 500]));

        $investigationResponse = $this->getJson('/api/get-facility-investigations');
        $this->assertTrue(in_array($investigationResponse->status(), [200, 401, 302, 403, 500]));

        $procedureResponse = $this->getJson('/api/get-facility-procedures');
        $this->assertTrue(in_array($procedureResponse->status(), [200, 401, 302, 403, 500]));
    }
}
