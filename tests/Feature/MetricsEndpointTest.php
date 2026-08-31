<?php

namespace Tests\Feature;

use Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    /**
     * Test metrics endpoint returns 200 with Prometheus text format.
     *
     * @return void
     */
    public function test_metrics_endpoint_returns_prometheus_format(): void
    {
        $response = $this->get('/metrics');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
        $response->assertSee('app_up 1');
        $response->assertSee('app_db_up');
        $response->assertSee('app_memory_usage_bytes');
    }
}
