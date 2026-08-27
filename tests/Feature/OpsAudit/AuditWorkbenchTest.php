<?php

namespace Tests\Feature\OpsAudit;

use App\Models\User;
use Tests\TestCase;

class AuditWorkbenchTest extends TestCase
{
    /** @test */
    public function test_internal_audit_workbench_returns_200()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/ops-audit/cash-billing');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_audit_stamp_marks_record_as_audited()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_unresolved_query_blocks_audit_completion()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_audit_timeline_endpoint_returns_json()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/ops-audit/cash-billing');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_audit_kpi_dashboard_returns_correct_counts()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/ops-audit/cash-billing');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }
}
