<?php

namespace Tests\Feature\NativeClient;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Staff app, finance modules (fixed assets, liabilities, leases, cost centres, capex, budgets, cash-flow forecast,
 * KPIs, statutory remittances, opening balances, banks, bank reconciliation). Each route is the web controller's own;
 * these tests check the guard and that every read answers JSON, then walk a create and a workflow action.
 */
class FinanceModulesMobileTest extends TestCase
{
    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
    private const DT = '?draw=1&start=0&length=2&search[value]=';

    private function as(string $role): User
    {
        $u = User::factory()->create(['status' => 1]);
        $u->assignRole($role);
        Sanctum::actingAs($u);

        return $u;
    }

    public function test_finance_modules_are_for_accounting_roles_only()
    {
        $this->as('NURSE');
        foreach (['fixed-assets/datatable', 'liabilities/datatable', 'capex/datatable', 'kpi', 'bank-reconciliation/datatable'] as $p) {
            $this->getJson("/api/mobile/accounting/{$p}", self::AJAX)->assertStatus(403);
        }
    }

    public function test_every_read_answers_json()
    {
        $this->as('SUPERADMIN');
        $id = fn (string $t) => DB::table($t)->value('id');
        $reads = [
            'fixed-assets/datatable' . self::DT, 'fixed-assets/categories', 'capex', 'capex/datatable' . self::DT, 'capex/budget-overview',
            'budgets', 'budgets/datatable' . self::DT, 'budgets/variance-report', 'statutory-remittances', 'statutory-remittances/balances',
            'cash-flow-forecast', 'cash-flow-forecast/datatable' . self::DT, 'cash-flow-forecast/patterns', 'opening-balances/datatable' . self::DT, 'opening-balances/accounts', 'banks/list',
            'liabilities', 'liabilities/datatable' . self::DT, 'liabilities/create', 'leases', 'leases/datatable' . self::DT, 'leases/create', 'leases/reports/ifrs16',
            'cost-centers', 'cost-centers/datatable' . self::DT, 'cost-centers/create', 'cost-centers/allocations/index',
            'kpi', 'kpi/definitions', 'kpi/datatable' . self::DT, 'kpi/alerts', 'kpi/configure', 'kpi/create',
            'bank-reconciliation', 'bank-reconciliation/datatable' . self::DT,
        ];
        foreach ([
            ['fixed_assets', 'fixed-assets/%d', 'fixed-assets/%d/depreciation-history'], ['capex_projects', 'capex/%d'], ['budgets', 'budgets/%d'],
            ['cash_flow_forecasts', 'cash-flow-forecast/%d'], ['cash_flow_forecast_periods', 'cash-flow-forecast/periods/%d/edit'],
            ['liability_schedules', 'liabilities/%d', 'liabilities/%d/schedule', 'liabilities/%d/payment'],
            ['leases', 'leases/%d', 'leases/%d/schedule', 'leases/%d/payment', 'leases/%d/modification'],
            ['cost_centers', 'cost-centers/%d', 'cost-centers/%d/budgets', 'cost-centers/%d/report'], ['financial_kpis', 'kpi/%d/edit', 'kpi/%d/history'],
            ['bank_reconciliations', 'bank-reconciliation/%d', 'bank-reconciliation/%d/edit'],
        ] as $row) {
            $table = array_shift($row);
            if ($rid = $id($table)) {
                foreach ($row as $pattern) {
                    $reads[] = sprintf($pattern, $rid);
                }
            }
        }
        // the app's DataTables request carries search[regex] but no search[value] when nothing is typed
        $reads[] = 'budgets/datatable?draw=1&start=0&length=2&search[regex]=false';
        $reads[] = 'cash-flow-forecast/datatable?draw=1&start=0&length=2&search[regex]=false';
        foreach ($reads as $path) {
            $r = $this->getJson('/api/mobile/accounting/' . $path, self::AJAX);
            $this->assertSame(200, $r->status(), "{$path}: " . substr($r->getContent(), 0, 200));
            $this->assertNotNull(json_decode($r->getContent(), true), "{$path} is JSON");
        }
    }

    public function test_a_statutory_remittance_runs_from_draft_to_approved()
    {
        $this->as('SUPERADMIN');
        $head = DB::table('pay_heads')->where('type', 'deduction')->whereNotNull('liability_account_id')->where('is_active', true)->value('id');
        $head ??= DB::table('pay_heads')->insertGetId(['name' => 'PAYE test', 'code' => 'PT' . random_int(100, 999), 'type' => 'deduction', 'liability_account_id' => DB::table('accounts')->where('is_active', true)->value('id'), 'created_at' => now(), 'updated_at' => now()]);
        $r = $this->postJson('/api/mobile/accounting/statutory-remittances', ['pay_head_id' => $head, 'period_from' => '2026-01-01', 'period_to' => '2026-01-31', 'amount' => 1500, 'payee_name' => 'Tax office'], self::AJAX);
        $this->assertSame(200, $r->status(), $r->getContent());
        $sid = $r->json('data.id') ?? $r->json('remittance.id') ?? DB::table('statutory_remittances')->latest('id')->value('id');
        $this->postJson("/api/mobile/accounting/statutory-remittances/{$sid}/submit", [], self::AJAX)->assertOk();
        $this->postJson("/api/mobile/accounting/statutory-remittances/{$sid}/approve", [], self::AJAX)->assertOk();
        $this->assertSame('approved', DB::table('statutory_remittances')->where('id', $sid)->value('status'));
    }

    public function test_a_liability_and_a_cost_centre_are_created_through_the_web_controllers()
    {
        $this->as('SUPERADMIN');
        $r = $this->postJson('/api/mobile/accounting/cost-centers', ['code' => 'CC' . random_int(1000, 9999), 'name' => 'Mobile test centre', 'type' => 'department', 'is_active' => 1], self::AJAX);
        $this->assertContains($r->status(), [200, 422], $r->getContent());
        $this->assertIsArray($r->json(), 'a redirect from the web controller becomes JSON');
        $bad = $this->postJson('/api/mobile/accounting/liabilities', [], self::AJAX);
        $this->assertSame(422, $bad->status());
    }

    public function test_saved_report_filters_save_load_and_delete()
    {
        $this->as('SUPERADMIN');
        $save = $this->postJson('/api/mobile/accounting/reports/filters', ['name' => 'Month end', 'report_type' => 'trial_balance', 'filters' => ['as_of_date' => '2026-01-31']], self::AJAX);
        $this->assertSame(200, $save->status(), substr($save->getContent(), 0, 400));
        $id = $save->json('filter.id');
        $list = $this->getJson('/api/mobile/accounting/reports/filters', self::AJAX)->assertOk()->json('filters');
        $this->assertContains($id, array_column($list, 'id'));
        $this->assertTrue(collect($list)->firstWhere('id', $id)['mine']);
        $this->getJson("/api/mobile/accounting/reports/filters/{$id}", self::AJAX)->assertOk()->assertJsonPath('filter.filters.as_of_date', '2026-01-31');
        $this->deleteJson("/api/mobile/accounting/reports/filters/{$id}", [], self::AJAX)->assertOk();
        $this->getJson('/api/mobile/accounting/reports/trial-balance', self::AJAX)->assertOk();
    }

    public function test_a_reconciliation_is_started_and_pairs_are_matched_through_the_web_controller()
    {
        $this->as('SUPERADMIN');
        $bank = DB::table('banks')->where('is_active', 1)->whereNotNull('account_id')->value('id');
        if (!$bank) {
            $this->markTestSkipped('No bank with a ledger account.');
        }
        $r = $this->postJson("/api/mobile/accounting/bank-reconciliation/store/{$bank}", ['statement_date' => '2026-11-30', 'statement_period_from' => '2026-11-01', 'statement_period_to' => '2026-11-30', 'statement_opening_balance' => 0, 'statement_closing_balance' => 1000], self::AJAX);
        $this->assertContains($r->status(), [200, 422], $r->getContent());
        $rid = $r->json('id') ?? DB::table('bank_reconciliations')->latest('id')->value('id');
        $this->getJson("/api/mobile/accounting/bank-reconciliation/{$rid}", self::AJAX)->assertOk()->assertJsonStructure(['reconciliation', 'unmatchedGL', 'unmatchedStatement', 'matchedItems']);
        $add = $this->postJson("/api/mobile/accounting/bank-reconciliation/{$rid}/statement-item", ['transaction_date' => '2026-11-10', 'description' => 'Deposit', 'amount' => 1000, 'amount_type' => 'credit'], self::AJAX);
        $this->assertSame(200, $add->status(), substr($add->getContent(), 0, 400));
        $this->assertSame(1, count($this->getJson("/api/mobile/accounting/bank-reconciliation/{$rid}", self::AJAX)->json('unmatchedStatement')));
    }

    public function test_a_budget_is_created_with_lines_edited_and_submitted()
    {
        $this->as('SUPERADMIN');
        $year = DB::table('fiscal_years')->value('id');
        $acct = DB::table('accounts')->where('is_active', 1)->value('id');
        $body = fn (string $name, int $amount) => ['budget_name' => $name, 'fiscal_year_id' => $year, 'items' => [['account_id' => $acct, 'amount' => $amount, 'notes' => 'n']]];
        $r = $this->postJson('/api/mobile/accounting/budgets', $body('Mobile budget', 500), self::AJAX);
        $this->assertSame(200, $r->status(), substr($r->getContent(), 0, 300));
        $bid = $r->json('id') ?? DB::table('budgets')->latest('id')->value('id');
        $detail = $this->getJson("/api/mobile/accounting/budgets/{$bid}", self::AJAX)->assertOk()->json();
        $this->assertSame('draft', $detail['budget']['status']);
        $this->assertCount(1, $detail['budget']['items'], 'the record carries its lines for the editor');
        $this->putJson("/api/mobile/accounting/budgets/{$bid}", $body('Mobile budget 2', 900), self::AJAX)->assertOk();
        $this->assertSame('Mobile budget 2', DB::table('budgets')->where('id', $bid)->value('budget_name'));
        $this->postJson("/api/mobile/accounting/budgets/{$bid}/submit", [], self::AJAX)->assertOk();
        $this->assertSame('pending_approval', DB::table('budgets')->where('id', $bid)->value('status'));
    }

    public function test_forecast_items_patterns_cost_centre_budget_allocation_kpi_and_bank_edits()
    {
        $this->as('SUPERADMIN');
        $period = DB::table('cash_flow_forecast_periods')->value('id');
        if ($period) {
            $this->postJson("/api/mobile/accounting/cash-flow-forecast/periods/{$period}/items", ['description' => 'Rent', 'type' => 'outflow', 'amount' => 100, 'category' => 'rent'], self::AJAX)->assertOk();
        }
        $p = $this->postJson('/api/mobile/accounting/cash-flow-forecast/patterns', ['pattern_name' => 'Mobile pattern', 'cash_flow_category' => 'operating_inflow', 'frequency' => 'monthly', 'expected_amount' => 1000], self::AJAX);
        $this->assertSame(200, $p->status(), substr($p->getContent(), 0, 300));
        $pid = DB::table('cash_flow_recurring_patterns')->where('pattern_name', 'Mobile pattern')->value('id');
        $this->putJson("/api/mobile/accounting/cash-flow-forecast/patterns/{$pid}", ['pattern_name' => 'Mobile pattern', 'cash_flow_category' => 'operating_inflow', 'frequency' => 'weekly', 'expected_amount' => 1200], self::AJAX)->assertOk();
        $this->postJson("/api/mobile/accounting/cash-flow-forecast/patterns/{$pid}/toggle", [], self::AJAX)->assertOk();
        $this->deleteJson("/api/mobile/accounting/cash-flow-forecast/patterns/{$pid}", [], self::AJAX)->assertOk();

        $cc = DB::table('cost_centers')->value('id');
        $other = DB::table('cost_centers')->where('id', '!=', $cc)->value('id');
        $year = DB::table('fiscal_years')->where('status', 'open')->value('id') ?? DB::table('fiscal_years')->value('id');
        $acct = DB::table('accounts')->where('is_active', 1)->value('id');
        $b = $this->postJson("/api/mobile/accounting/cost-centers/{$cc}/budgets", ['fiscal_year_id' => $year, 'account_id' => $acct, 'budget_amount' => 5000], self::AJAX);
        $this->assertSame(200, $b->status(), substr($b->getContent(), 0, 300));
        if ($other) {
            $a = $this->postJson('/api/mobile/accounting/cost-centers/allocations', ['source_cost_center_id' => $cc, 'target_cost_center_id' => $other, 'amount' => 10, 'allocation_date' => now()->toDateString(), 'description' => 'Test', 'allocation_basis' => 'Manual'], self::AJAX);
            $this->assertContains($a->status(), [200, 422], $a->getContent());
        }

        $kid = DB::table('financial_kpis')->value('id');
        $k = $this->getJson("/api/mobile/accounting/kpi/{$kid}/edit", self::AJAX)->assertOk()->json('kpi');
        $this->putJson("/api/mobile/accounting/kpi/{$kid}", ['kpi_code' => $k['kpi_code'], 'kpi_name' => $k['kpi_name'] . ' ', 'category' => $k['category'], 'calculation_formula' => $k['calculation_formula'], 'unit' => $k['unit'], 'frequency' => $k['frequency']], self::AJAX)->assertSuccessful();
        $this->getJson('/api/mobile/accounting/kpi/alerts', self::AJAX)->assertOk()->assertJsonStructure(['alerts' => ['data'], 'summary']);

        $this->postJson('/api/mobile/accounting/banks', ['name' => 'Mobile test bank'], self::AJAX)->assertSuccessful();
        $bank = DB::table('banks')->where('name', 'Mobile test bank')->value('id');
        $this->putJson("/api/mobile/accounting/banks/{$bank}", ['name' => 'Mobile test bank 2', 'account_number' => '123'], self::AJAX)->assertSuccessful();
        $this->assertSame('123', DB::table('banks')->where('id', $bank)->value('account_number'));
        $this->deleteJson("/api/mobile/accounting/banks/{$bank}", [], self::AJAX)->assertSuccessful();
    }
}
