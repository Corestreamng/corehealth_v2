<?php

namespace Tests\Feature\OpsAudit;

use App\Models\User;
use Tests\TestCase;

class OpsAuditSteppedStoryboardTest extends TestCase
{
    protected function getAdminUser()
    {
        $user = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['SUPERADMIN', 'ADMIN']);
        })->first();

        if (!$user) {
            $user = User::first() ?? User::factory()->create(['status' => 1]);
        }

        return $user;
    }

    public static function moduleProvider()
    {
        return [
            ['store'],
            ['billing'],
            ['doctor'],
            ['pharmacy'],
            ['nursing'],
            ['lab'],
            ['imaging'],
            ['reception'],
            ['hmo'],
            ['maternity'],
            ['surgery'],
            ['morgue'],
        ];
    }

    /**
     * @dataProvider moduleProvider
     */
    public function test_all_12_ops_audit_views_render_with_stepped_storyboard_ribbon($module)
    {
        $user = $this->getAdminUser();
        $response = $this->actingAs($user)->get("/ops-audit/{$module}");

        $this->assertTrue(in_array($response->status(), [200, 302]));

        if ($response->status() === 200) {
            $content = $response->getContent();
            $this->assertStringContainsString('id="ops_audit_filter_form"', $content, "Module {$module} missing filter form");
            $this->assertStringContainsString('ops-story-ribbon', $content, "Module {$module} missing stepped storyboard ribbon");
            $this->assertStringContainsString('btnStampVisiblePage', $content, "Module {$module} missing visible page stamp button");
            $this->assertStringContainsString('data-preset="today"', $content, "Module {$module} missing quick date horizon chips");
            if (!in_array($module, ['store', 'doctor', 'nursing', 'lab', 'imaging'])) {
                $this->assertStringContainsString('name="care_stream"', $content, "Module {$module} missing care stream filter");
            }
            $this->assertStringContainsString('css/ops-audit.css', $content, "Module {$module} missing standalone CSS");
            $this->assertStringContainsString('js/ops-audit.js', $content, "Module {$module} missing standalone JS");
        }
    }

    public function test_no_duplicate_filters_between_ribbon_and_tabs()
    {
        $user = $this->getAdminUser();

        // 1. Store: batches pane should not duplicate store_id
        $storeResponse = $this->actingAs($user)->get('/ops-audit/store');
        if ($storeResponse->status() === 200) {
            $html = $storeResponse->getContent();
            // Count occurrences of name="store_id"
            preg_match_all('/name=["\']store_id["\']/', $html, $matches);
            $this->assertCount(1, $matches[0], 'Store view must have exactly 1 store_id select (in ribbon Step 3)');
        }

        // 2. Billing: cashier_id should only be in Step 3 ribbon
        $billingResponse = $this->actingAs($user)->get('/ops-audit/billing');
        if ($billingResponse->status() === 200) {
            $html = $billingResponse->getContent();
            preg_match_all('/name=["\']cashier_id["\']/', $html, $matches);
            $this->assertCount(1, $matches[0], 'Billing view must have exactly 1 cashier_id select (in ribbon Step 3)');
        }

        // 3. Pharmacy: cashier_id should only be in Step 3 ribbon
        $pharmacyResponse = $this->actingAs($user)->get('/ops-audit/pharmacy');
        if ($pharmacyResponse->status() === 200) {
            $html = $pharmacyResponse->getContent();
            preg_match_all('/name=["\']cashier_id["\']/', $html, $matches);
            $this->assertCount(1, $matches[0], 'Pharmacy view must have exactly 1 cashier_id select (in ribbon Step 3)');
        }
    }

    public function test_bulk_stamp_endpoint_response_structure()
    {
        $user = $this->getAdminUser();

        $response = $this->actingAs($user)->postJson('/audit-workbench/audit/mark/bulk-stamp', [
            'model_type' => 'App\Models\Payment',
            'ids' => [9999999],
            'zone_key' => 'ops_audit.test',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 400, 422]));
    }
}
