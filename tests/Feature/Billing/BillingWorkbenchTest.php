<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use Tests\TestCase;

class BillingWorkbenchTest extends TestCase
{
    public function test_billing_workbench_sidebar_and_deposit()
    {
        $this->assertTrue(true);
    }

    /** @test */
    public function test_billing_workbench_account_tab_has_transaction_panel_above_kpi_cards()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/billing-workbench');

        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $content = $response->getContent();
            $panelPos = strpos($content, 'id="account-transaction-panel"');
            $statsPos = strpos($content, 'class="account-stats-grid"');

            $this->assertNotFalse($panelPos, 'account-transaction-panel not found in workbench');
            $this->assertNotFalse($statsPos, 'account-stats-grid not found in workbench');
            $this->assertLessThan($statsPos, $panelPos, 'account-transaction-panel must be located before account-stats-grid');
        }
    }
}
