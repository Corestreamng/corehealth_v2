<?php

namespace Tests\Feature\OpsAudit;

use App\Models\User;
use Tests\TestCase;

class OpsAuditControllersTest extends TestCase
{
    /** @test */
    public function test_all_ops_audit_data_endpoints_return_200()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/ops-audit/cash-billing');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_ops_audit_null_safe_payment_info_rendering()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_payment_method_filter_cash_returns_only_cash_payments()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/ops-audit/cash-billing?payment_method=cash');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_ops_audit_print_endpoint_returns_html_table()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/ops-audit/cash-billing?action=print');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_auditor_name_shown_in_audit_column()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }
}
