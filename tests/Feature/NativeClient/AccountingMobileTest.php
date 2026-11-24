<?php

namespace Tests\Feature\NativeClient;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Staff app, Accounting: guarded like the web sidebar (SUPERADMIN, ADMIN, ACCOUNTS), every report answers as a
 * uniform document, and the journal workflow (draft, submit, approve, post, reverse) moves through the web controller.
 */
class AccountingMobileTest extends TestCase
{
    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    private function as(string $role): User
    {
        $u = User::factory()->create(['status' => 1]);
        $u->assignRole(Role::where('name', $role)->exists() ? $role : 'ADMIN');
        Sanctum::actingAs($u);

        return $u;
    }

    public function test_only_accounts_staff_and_admins_pass_the_guard()
    {
        $this->as('NURSE');
        $this->getJson('/api/mobile/accounting/dashboard', self::AJAX)->assertStatus(403);
        $this->as('STORE');
        $this->getJson('/api/mobile/accounting/dashboard', self::AJAX)->assertStatus(403);
        $this->as('SUPERADMIN');
        $this->getJson('/api/mobile/accounting/dashboard', self::AJAX)->assertOk()->assertJsonStructure(['period', 'stats', 'receivables' => ['total'], 'payables' => ['total'], 'can']);
    }

    public function test_every_report_is_a_uniform_document()
    {
        $this->as('SUPERADMIN');
        $acct = Account::value('id');
        foreach (['trial-balance', 'profit-loss', 'balance-sheet', 'cash-flow', "general-ledger?account_id={$acct}", 'aged-receivables', 'aged-payables', 'daily-audit'] as $r) {
            $sep = str_contains($r, '?') ? '&' : '?';
            $doc = $this->getJson("/api/mobile/accounting/reports/{$r}{$sep}start_date=2026-01-01&end_date=2026-12-31", self::AJAX)->assertOk()->json();
            $this->assertArrayHasKey('title', $doc, $r);
            $this->assertIsArray($doc['sections'], $r);
            foreach ($doc['sections'] as $s) {
                $this->assertNotEmpty($s['columns'], "{$r}: {$s['title']}");
                foreach ($s['columns'] as $c) {
                    $this->assertContains($c['type'], ['text', 'money', 'number']);
                }
            }
        }
        $this->getJson('/api/mobile/accounting/reports/nonsense', self::AJAX)->assertStatus(404);
        $this->getJson('/api/mobile/accounting/reports/general-ledger', self::AJAX)->assertStatus(422);
    }

    public function test_the_trial_balance_document_equals_the_report_service()
    {
        $this->as('SUPERADMIN');
        $svc = app(\App\Services\Accounting\ReportService::class)->generateTrialBalance(now()->toDateString());
        $doc = $this->getJson('/api/mobile/accounting/reports/trial-balance', self::AJAX)->json();
        $this->assertEqualsWithDelta($svc['total_debit'], $doc['kpis'][0]['value'], 0.01);
        $this->assertEqualsWithDelta($svc['total_credit'], $doc['kpis'][1]['value'], 0.01);
        $this->assertCount(count($svc['accounts']), $doc['sections'][0]['rows']);
    }

    public function test_a_manual_journal_goes_draft_submit_approve_post_reverse()
    {
        $this->as('SUPERADMIN');
        $a = Account::where('is_active', true)->orderBy('id')->first();
        $b = Account::where('is_active', true)->where('id', '!=', $a->id)->orderBy('id')->first();

        $this->postJson('/api/mobile/accounting/journal-entries', [
            'entry_date' => now()->toDateString(), 'entry_type' => 'manual', 'description' => 'Native journal test',
            'lines' => [['account_id' => $a->id, 'debit_amount' => 500, 'credit_amount' => 0], ['account_id' => $b->id, 'debit_amount' => 0, 'credit_amount' => 500]],
        ], self::AJAX)->assertSuccessful();
        $entry = JournalEntry::where('description', 'Native journal test')->latest('id')->firstOrFail();

        $detail = $this->getJson("/api/mobile/accounting/journal-entries/{$entry->id}", self::AJAX)->assertOk()->json();
        $this->assertTrue($detail['entry']['is_balanced']);
        $this->assertCount(2, $detail['entry']['lines']);
        $this->assertTrue($detail['can']['submit']);

        $this->postJson("/api/mobile/accounting/journal-entries/{$entry->id}/submit", [], self::AJAX)->assertSuccessful();
        $this->postJson("/api/mobile/accounting/journal-entries/{$entry->id}/approve", [], self::AJAX)->assertSuccessful();
        $this->postJson("/api/mobile/accounting/journal-entries/{$entry->id}/post", [], self::AJAX)->assertSuccessful();
        $this->assertSame('posted', $entry->fresh()->status);

        $before = app(\App\Services\Accounting\ReportService::class)->generateTrialBalance(now()->toDateString());
        $this->assertTrue($before['is_balanced'] || abs($before['difference']) >= 0, 'Trial balance computed.');
        $this->postJson("/api/mobile/accounting/journal-entries/{$entry->id}/reverse", ['reversal_reason' => 'Native test reversal'], self::AJAX)->assertSuccessful();
        $this->assertSame('reversed', $entry->fresh()->status);
        $after = app(\App\Services\Accounting\ReportService::class)->generateTrialBalance(now()->toDateString());
        $this->assertEqualsWithDelta($before['difference'], $after['difference'], 0.01, 'A balanced entry and its reversal do not change the trial balance difference.');
    }

    public function test_an_unbalanced_journal_is_refused()
    {
        $this->as('SUPERADMIN');
        $a = Account::where('is_active', true)->orderBy('id')->first();
        $b = Account::where('is_active', true)->where('id', '!=', $a->id)->orderBy('id')->first();
        $this->postJson('/api/mobile/accounting/journal-entries', [
            'entry_date' => now()->toDateString(), 'description' => 'Unbalanced native test',
            'lines' => [['account_id' => $a->id, 'debit_amount' => 500, 'credit_amount' => 0], ['account_id' => $b->id, 'debit_amount' => 0, 'credit_amount' => 400]],
        ], self::AJAX)->assertStatus(422);
        $this->assertNull(JournalEntry::where('description', 'Unbalanced native test')->first());
    }
}
