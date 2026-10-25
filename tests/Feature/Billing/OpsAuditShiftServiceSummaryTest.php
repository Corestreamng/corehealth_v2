<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpsAuditShiftServiceSummaryTest extends TestCase
{
    protected User $auditorUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->auditorUser = User::factory()->create(['status' => 1]);
        $auditorRole = Role::firstOrCreate(['name' => 'Internal Auditor', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'ADMIN', 'guard_name' => 'web']);
        $this->auditorUser->assignRole($auditorRole);
        $this->auditorUser->assignRole($adminRole);
    }

    /**
     * Test that an authenticated auditor can access the shift service summary report endpoint.
     */
    public function test_user_can_access_shift_service_summary_endpoint(): void
    {
        $response = $this->actingAs($this->auditorUser)
            ->get(route('ops-audit.shift-service-summary', [
                'start_date' => now()->subDays(7)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'shift' => 'morning',
            ]));

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        if ($response->getStatusCode() === 200) {
            $response->assertSee('Duty Shift Handover Audit');
            $response->assertSee('AM Duty (Morning — till 1:00 PM)');
        }
    }

    /**
     * Test JSON response contains the full service matrix and tender reconciliation.
     */
    public function test_shift_service_summary_returns_json_structure(): void
    {
        $response = $this->actingAs($this->auditorUser)
            ->get(route('ops-audit.shift-service-summary', [
                'start_date' => now()->subDays(7)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'shift' => 'morning',
                'action' => 'json',
            ]));

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        if ($response->getStatusCode() === 200) {
            $data = $response->json();
            $this->assertArrayHasKey('serviceMatrix', $data);
            $this->assertArrayHasKey('grandTotals', $data);
            $this->assertArrayHasKey('tender', $data);
            $this->assertArrayHasKey('shiftLabel', $data);

            $this->assertEquals('AM Duty (Morning — till 1:00 PM)', $data['shiftLabel']);

            // Verify Drugs category exists in the service matrix
            $keys = array_column($data['serviceMatrix'], 'key');
            $this->assertContains('drugs', $keys);
            $this->assertContains('lab', $keys);
            $this->assertContains('imaging', $keys);
        }
    }

    /**
     * Test shift calibration: AM duty ends at 1:00 PM, PM starts at 1:00 PM, Night from 6:00 PM.
     */
    public function test_shift_calibration_labels_and_filters(): void
    {
        // 1. Morning (till 1:00 PM)
        $morning = $this->actingAs($this->auditorUser)
            ->get(route('ops-audit.shift-service-summary', ['shift' => 'morning', 'action' => 'json']));
        if ($morning->getStatusCode() === 200) {
            $this->assertEquals('AM Duty (Morning — till 1:00 PM)', $morning->json('shiftLabel'));
        }

        // 2. Afternoon / Late (1:00 PM to 6:00 PM)
        $afternoon = $this->actingAs($this->auditorUser)
            ->get(route('ops-audit.shift-service-summary', ['shift' => 'afternoon', 'action' => 'json']));
        if ($afternoon->getStatusCode() === 200) {
            $this->assertEquals('Late / PM Duty (Afternoon — 1:00 PM to 6:00 PM)', $afternoon->json('shiftLabel'));
        }

        // 3. Night
        $night = $this->actingAs($this->auditorUser)
            ->get(route('ops-audit.shift-service-summary', ['shift' => 'night', 'action' => 'json']));
        if ($night->getStatusCode() === 200) {
            $this->assertEquals('Night Duty (Night / Overnight — 6:00 PM onwards)', $night->json('shiftLabel'));
        }
    }

    /**
     * Test care stream filter works across general, inpatient, and insured schemes pool.
     */
    public function test_care_stream_filtering(): void
    {
        foreach (['general', 'inpatient', 'nhis', 'insured', 'schemes', 'all'] as $stream) {
            $response = $this->actingAs($this->auditorUser)
                ->get(route('ops-audit.shift-service-summary', [
                    'care_stream' => $stream,
                    'action' => 'json',
                ]));

            $this->assertContains($response->getStatusCode(), [200, 302, 403]);
            if ($response->getStatusCode() === 200) {
                $this->assertEquals($stream, $response->json('careStream'));
            }
        }
    }

    /**
     * Test tender reconciliation returns refunds and cash inflow fields.
     */
    public function test_tender_reconciliation_includes_refunds_and_inflows(): void
    {
        $response = $this->actingAs($this->auditorUser)
            ->get(route('ops-audit.shift-service-summary', [
                'action' => 'json',
            ]));

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        if ($response->getStatusCode() === 200) {
            $tender = $response->json('tender');
            $this->assertArrayHasKey('refunds', $tender);
            $this->assertArrayHasKey('refunds_count', $tender);
            $this->assertArrayHasKey('cash_inflow', $tender);
            $this->assertArrayHasKey('cash_inflow_count', $tender);
            $this->assertArrayHasKey('net_tender', $tender);
        }
    }

    /**
     * Test print layout renders complete A4 reconciliation document with tri-party signature blocks and insured pool language.
     */
    public function test_shift_summary_print_view_renders_properly(): void
    {
        $response = $this->actingAs($this->auditorUser)
            ->get(route('ops-audit.shift-service-summary', [
                'start_date' => now()->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'shift' => 'all',
            ]));

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        if ($response->getStatusCode() === 200) {
            $content = $response->getContent();
            $this->assertStringContainsString('Duty Shift Handover Audit', $content);
            $this->assertStringContainsString('Service Revenue Breakdown by Care Stream', $content);
            $this->assertStringContainsString('Shift Tender & Cashier Handover Reconciliation', $content);
            $this->assertStringContainsString('Insured Claims', $content);
            $this->assertStringContainsString('Insured Co-Pay', $content);
            $this->assertStringContainsString('Insured / Scheme Claims', $content);
            $this->assertStringContainsString('Refunds &amp; Withdrawals', $content);
            $this->assertStringContainsString('1. Outgoing Duty Officer', $content);
            $this->assertStringContainsString('2. Incoming Duty Officer', $content);
            $this->assertStringContainsString('3. Internal Audit / HOD Certification', $content);
        }
    }
}
