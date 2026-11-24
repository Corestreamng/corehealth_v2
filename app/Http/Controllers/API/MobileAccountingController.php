<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Accounting\AccountingController;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Staff app, Accounting. Adapters only: the web answers these with Blade pages, so this returns the same figures as
 * JSON from the same services (ReportService, the models), in one uniform "report document" shape the app renders
 * for every report. Everything that creates, approves, posts or reverses goes to the web controllers.
 */
class MobileAccountingController extends Controller
{
    public function __construct(protected ReportService $reports)
    {
    }

    private function can(string $permission): bool
    {
        return (bool) auth()->user()?->can($permission);
    }

    /** Period, headline figures, what waits for approval, and the receivable / payable position. */
    public function dashboard(Request $request)
    {
        $period = app(\App\Services\Accounting\AccountingService::class)->getCurrentPeriod();
        $stats = app(AccountingController::class)->getDashboardStats($period);
        $recent = JournalEntry::with('createdBy')->orderByDesc('created_at')->limit(8)->get()->map(fn ($e) => $this->entryRow($e));
        $pending = JournalEntry::with('createdBy')->where('status', JournalEntry::STATUS_PENDING)->orderBy('created_at')->limit(8)->get()->map(fn ($e) => $this->entryRow($e));
        $today = Carbon::today()->toDateString();
        $ar = $this->reports->getAgedReceivables($today);
        $ap = $this->reports->getAgedPayables($today);

        return response()->json([
            'success' => true,
            'period' => $period ? ['id' => $period->id, 'name' => $period->name ?? null, 'start' => $period->start_date?->toDateString(), 'end' => $period->end_date?->toDateString(), 'status' => $period->status] : null,
            'stats' => $stats,
            'recent_entries' => $recent,
            'pending_entries' => $pending,
            'receivables' => ['total' => (float) $ar['total'], 'buckets' => $ar['totals'], 'by_category' => $ar['summary']],
            'payables' => ['total' => (float) $ap['total'], 'buckets' => $ap['totals'], 'by_category' => $ap['summary']],
            'can' => [
                'journal_create' => $this->can('accounting.journal.create'),
                'journal_approve' => $this->can('accounting.journal.approve'),
                'journal_post' => $this->can('accounting.journal.post'),
                'reports' => $this->can('reports.view'),
            ],
        ]);
    }

    private function entryRow(JournalEntry $e): array
    {
        return [
            'id' => $e->id,
            'entry_number' => $e->entry_number,
            'entry_date' => optional($e->entry_date)->toDateString(),
            'description' => $e->description,
            'status' => $e->status,
            'total_debit' => (float) ($e->total_debit ?? 0),
            'total_credit' => (float) ($e->total_credit ?? 0),
            'created_by' => $e->createdBy ? trim($e->createdBy->surname . ' ' . $e->createdBy->firstname) : null,
        ];
    }

    /** A journal entry with its lines, who did what, and what this user may do next (the web show page). */
    public function journalEntry(Request $request, int $id)
    {
        $entry = JournalEntry::with(['lines.account', 'createdBy', 'approvedBy', 'postedBy', 'reversedBy', 'reversalEntry', 'originalEntry', 'edits.requester', 'edits.approver'])->findOrFail($id);
        $name = fn ($u) => $u ? trim($u->surname . ' ' . $u->firstname) : null;

        return response()->json([
            'success' => true,
            'entry' => [
                'id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'entry_date' => optional($entry->entry_date)->toDateString(),
                'entry_type' => $entry->entry_type,
                'status' => $entry->status,
                'reference' => $entry->reference,
                'description' => $entry->description,
                'memo' => $entry->memo,
                'source' => $entry->source_type ?? $entry->source ?? null,
                'rejection_reason' => $entry->rejection_reason ?? null,
                'reversal_reason' => $entry->reversal_reason ?? null,
                'created_by' => $name($entry->createdBy),
                'created_at' => optional($entry->created_at)->toIso8601String(),
                'approved_by' => $name($entry->approvedBy),
                'approved_at' => optional($entry->approved_at)->toIso8601String(),
                'posted_by' => $name($entry->postedBy),
                'posted_at' => optional($entry->posted_at)->toIso8601String(),
                'reversed_by' => $name($entry->reversedBy),
                'reversal_entry' => $entry->reversalEntry ? ['id' => $entry->reversalEntry->id, 'entry_number' => $entry->reversalEntry->entry_number] : null,
                'original_entry' => $entry->originalEntry ? ['id' => $entry->originalEntry->id, 'entry_number' => $entry->originalEntry->entry_number] : null,
                'lines' => $entry->lines->map(fn ($l) => [
                    'account_id' => $l->account_id,
                    'account_code' => $l->account->code ?? null,
                    'account_name' => $l->account->name ?? null,
                    'description' => $l->description,
                    'debit' => (float) $l->debit,
                    'credit' => (float) $l->credit,
                ])->values(),
                'total_debit' => (float) $entry->lines->sum('debit'),
                'total_credit' => (float) $entry->lines->sum('credit'),
                'is_balanced' => $entry->isBalanced(),
                'edits' => $entry->edits->map(fn ($e) => [
                    'id' => $e->id,
                    'status' => $e->status,
                    'reason' => $e->reason ?? null,
                    'requested_by' => $name($e->requester),
                    'requested_at' => optional($e->created_at)->toIso8601String(),
                    'approved_by' => $name($e->approver),
                ])->values(),
            ],
            'can' => [
                'edit' => $entry->canEdit() && $this->can('accounting.journal.edit'),
                'submit' => $entry->canSubmit() && $this->can('accounting.journal.submit'),
                'approve' => $entry->canApprove() && $this->can('accounting.journal.approve'),
                'reject' => $entry->canApprove() && $this->can('accounting.journal.reject'),
                'post' => $entry->canPost() && $this->can('accounting.journal.post'),
                'reverse' => $entry->canReverse() && $this->can('accounting.journal.reverse'),
                'delete' => $entry->status === JournalEntry::STATUS_DRAFT && $this->can('accounting.journal.delete'),
            ],
        ]);
    }

    /** Postable accounts for entry forms (code, name, class). */
    public function accounts(Request $request)
    {
        $q = Account::where('is_active', true)->with('accountGroup.accountClass');
        if ($term = trim((string) $request->input('q', $request->input('term', '')))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"));
        }

        return response()->json(['success' => true, 'accounts' => $q->orderBy('code')->limit(40)->get()->map(fn ($a) => [
            'id' => $a->id,
            'code' => $a->code,
            'name' => $a->name,
            'class' => $a->accountGroup?->accountClass?->name,
            'group' => $a->accountGroup?->name,
            'normal_balance' => $a->normal_balance ?? null,
        ])]);
    }

    /** Fiscal years and their periods, and which one is current (the web "Fiscal Periods" page). */
    public function periods()
    {
        $years = FiscalYear::with(['periods' => fn ($q) => $q->orderBy('start_date')])->orderByDesc('start_date')->get();

        return response()->json([
            'success' => true,
            'years' => $years->map(fn ($y) => [
                'id' => $y->id,
                'name' => $y->name,
                'start' => optional($y->start_date)->toDateString(),
                'end' => optional($y->end_date)->toDateString(),
                'status' => $y->status,
                'periods' => $y->periods->map(fn ($p) => ['id' => $p->id, 'name' => $p->name ?? $p->start_date?->format('M Y'), 'start' => $p->start_date?->toDateString(), 'end' => $p->end_date?->toDateString(), 'status' => $p->status])->values(),
            ])->values(),
        ]);
    }

    // ═══════════════════════════ Reports ═══════════════════════════

    /**
     * Any report as one uniform document: {title, subtitle, kpis[], sections[{title, columns[], rows[], totals?}], checks[]}.
     * Columns have a type (text, money, number) so the app formats them; rows are plain objects.
     */
    public function report(Request $request, string $name)
    {
        $today = Carbon::today();
        $from = $request->filled('start_date') ? Carbon::parse($request->start_date)->toDateString() : $today->copy()->startOfMonth()->toDateString();
        $to = $request->filled('end_date') ? Carbon::parse($request->end_date)->toDateString() : $today->toDateString();
        $asOf = $request->filled('as_of_date') ? Carbon::parse($request->as_of_date)->toDateString() : $today->toDateString();

        $doc = match ($name) {
            'trial-balance' => $this->trialBalance($asOf, filter_var($request->input('show_zero'), FILTER_VALIDATE_BOOLEAN)),
            'profit-loss' => $this->profitLoss($from, $to),
            'balance-sheet' => $this->balanceSheet($asOf),
            'cash-flow' => $this->cashFlow($from, $to),
            'general-ledger' => $this->generalLedger((int) $request->account_id, $from, $to),
            'aged-receivables' => $this->agedReceivables($asOf),
            'aged-payables' => $this->agedPayables($asOf),
            'daily-audit' => $this->dailyAudit($request->filled('date') ? Carbon::parse($request->date)->toDateString() : $today->toDateString()),
            default => abort(404, 'Unknown report.'),
        };

        return response()->json(['success' => true, 'report' => $name] + $doc);
    }

    private function money(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'money'];
    }

    private function trialBalance(string $asOf, bool $zero): array
    {
        $r = $this->reports->generateTrialBalance($asOf, $zero);

        return [
            'title' => 'Trial balance',
            'subtitle' => 'As at ' . $asOf,
            'kpis' => [['label' => 'Total debit', 'value' => (float) $r['total_debit'], 'type' => 'money'], ['label' => 'Total credit', 'value' => (float) $r['total_credit'], 'type' => 'money']],
            'checks' => [['label' => $r['is_balanced'] ? 'Debits equal credits' : 'Out of balance by ' . number_format((float) $r['difference'], 2), 'ok' => (bool) $r['is_balanced']]],
            'sections' => [[
                'title' => 'Accounts',
                'columns' => [['key' => 'account_code', 'label' => 'Code', 'type' => 'text'], ['key' => 'account_name', 'label' => 'Account', 'type' => 'text', 'primary' => true], ['key' => 'group_name', 'label' => 'Group', 'type' => 'text'], $this->money('debit', 'Debit'), $this->money('credit', 'Credit')],
                'rows' => $r['accounts'],
                'totals' => ['account_name' => 'Total', 'debit' => (float) $r['total_debit'], 'credit' => (float) $r['total_credit']],
            ]],
        ];
    }

    private function classSections(array $block): array
    {
        $out = [];
        foreach (($block['groups'] ?? []) as $g) {
            $out[] = [
                'title' => ($block['class_name'] ?? '') . ' · ' . $g['name'],
                'columns' => [['key' => 'code', 'label' => 'Code', 'type' => 'text'], ['key' => 'name', 'label' => 'Account', 'type' => 'text', 'primary' => true], $this->money('balance', 'Amount')],
                'rows' => $g['accounts'],
                'totals' => ['name' => 'Total ' . $g['name'], 'balance' => (float) $g['total']],
            ];
        }

        return $out;
    }

    private function profitLoss(string $from, string $to): array
    {
        $r = $this->reports->generateProfitAndLoss($from, $to);

        return [
            'title' => 'Profit and loss',
            'subtitle' => "{$from} to {$to}",
            'kpis' => [['label' => 'Income', 'value' => (float) $r['total_income'], 'type' => 'money', 'tone' => 'success'], ['label' => 'Expenses', 'value' => (float) $r['total_expenses'], 'type' => 'money', 'tone' => 'danger'], ['label' => $r['is_profit'] ? 'Net profit' : 'Net loss', 'value' => (float) $r['net_income'], 'type' => 'money', 'tone' => $r['is_profit'] ? 'success' : 'danger']],
            'sections' => array_merge($this->classSections($r['income']), $this->classSections($r['expenses'])),
        ];
    }

    private function balanceSheet(string $asOf): array
    {
        $r = $this->reports->generateBalanceSheet($asOf);

        return [
            'title' => 'Balance sheet',
            'subtitle' => 'As at ' . $asOf,
            'kpis' => [['label' => 'Total assets', 'value' => (float) $r['total_assets'], 'type' => 'money'], ['label' => 'Total liabilities', 'value' => (float) $r['total_liabilities'], 'type' => 'money'], ['label' => 'Equity (with this year\'s result)', 'value' => (float) $r['total_equity_with_retained'], 'type' => 'money']],
            'checks' => [['label' => $r['is_balanced'] ? 'Assets equal liabilities plus equity' : 'The balance sheet does not balance', 'ok' => (bool) $r['is_balanced']]],
            'sections' => array_merge($this->classSections($r['assets']), $this->classSections($r['liabilities']), $this->classSections($r['equity'])),
        ];
    }

    private function cashFlow(string $from, string $to): array
    {
        $r = $this->reports->generateCashFlowStatement($from, $to);
        $cols = [['key' => 'account_name', 'label' => 'Account', 'type' => 'text', 'primary' => true], ['key' => 'category', 'label' => 'Category', 'type' => 'text'], $this->money('amount', 'Amount')];
        $sec = fn (string $t, array $rows, $net) => ['title' => $t, 'columns' => $cols, 'rows' => $rows, 'totals' => ['account_name' => 'Net', 'amount' => (float) $net]];

        return [
            'title' => 'Cash flow',
            'subtitle' => "{$from} to {$to}",
            'kpis' => [['label' => 'Opening cash', 'value' => (float) $r['beginning_cash'], 'type' => 'money'], ['label' => 'Net change', 'value' => (float) $r['net_change_in_cash'], 'type' => 'money'], ['label' => 'Closing cash', 'value' => (float) $r['ending_cash'], 'type' => 'money']],
            'sections' => [$sec('Operating activities', $r['operating_activities'], $r['net_operating']), $sec('Investing activities', $r['investing_activities'], $r['net_investing']), $sec('Financing activities', $r['financing_activities'], $r['net_financing'])],
        ];
    }

    private function generalLedger(int $accountId, string $from, string $to): array
    {
        abort_unless($accountId > 0, 422, 'Choose an account.');
        $r = $this->reports->generateGeneralLedger($accountId, $from, $to);

        return [
            'title' => 'General ledger · ' . ($r['account']['code'] ?? '') . ' ' . ($r['account']['name'] ?? ''),
            'subtitle' => "{$from} to {$to}",
            'kpis' => [['label' => 'Opening', 'value' => (float) $r['opening_balance'], 'type' => 'money'], ['label' => 'Debits', 'value' => (float) $r['total_debit'], 'type' => 'money'], ['label' => 'Credits', 'value' => (float) $r['total_credit'], 'type' => 'money'], ['label' => 'Closing', 'value' => (float) $r['closing_balance'], 'type' => 'money']],
            'sections' => [[
                'title' => 'Transactions',
                'columns' => [['key' => 'date', 'label' => 'Date', 'type' => 'text'], ['key' => 'entry_number', 'label' => 'Entry', 'type' => 'text', 'primary' => true], ['key' => 'description', 'label' => 'Description', 'type' => 'text'], $this->money('debit', 'Debit'), $this->money('credit', 'Credit'), $this->money('running_balance', 'Balance')],
                'rows' => $r['transactions'],
            ]],
        ];
    }

    /** One detail row of an aged report with the same keys whatever category it came from. */
    private function agedRow(array $d): array
    {
        return [
            'name' => $d['patient_name'] ?? $d['supplier_name'] ?? $d['name'] ?? $d['hmo_name'] ?? $d['account_name'] ?? '—',
            'reference' => $d['patient_file_no'] ?? $d['reference'] ?? ($d['account_code'] ?? ''),
            'detail' => $d['hmo_name'] ?? $d['type'] ?? '',
            'amount' => (float) ($d['outstanding_amount'] ?? $d['amount'] ?? $d['credit_amount'] ?? $d['balance'] ?? 0),
            'bucket' => $d['aging_bucket'] ?? 'current',
        ];
    }

    private function agedDoc(string $title, string $asOf, array $r, array $labels): array
    {
        $cols = [['key' => 'name', 'label' => 'Name', 'type' => 'text', 'primary' => true], ['key' => 'reference', 'label' => 'Reference', 'type' => 'text'], ['key' => 'detail', 'label' => 'Detail', 'type' => 'text'], $this->money('amount', 'Amount'), ['key' => 'bucket', 'label' => 'Age', 'type' => 'text']];
        $sections = [];
        foreach ($r['categories'] as $key => $cat) {
            $rows = collect($cat['details'] ?? [])->map(fn ($d) => $this->agedRow($d))->sortByDesc('amount')->values()->all();
            $sections[] = ['title' => ($labels[$key] ?? ($cat['label'] ?? $key)) . ' (' . count($rows) . ')', 'note' => $cat['description'] ?? null, 'columns' => $cols, 'rows' => $rows, 'totals' => ['name' => 'Total', 'amount' => (float) ($cat['total'] ?? collect($rows)->sum('amount'))]];
        }
        $b = $r['totals'];

        return [
            'title' => $title,
            'subtitle' => 'As at ' . $asOf,
            'kpis' => [['label' => 'Total', 'value' => (float) $r['total'], 'type' => 'money'], ['label' => 'Current', 'value' => (float) $b['current'], 'type' => 'money'], ['label' => '1-30 days', 'value' => (float) $b['1_30'], 'type' => 'money'], ['label' => '31-60 days', 'value' => (float) $b['31_60'], 'type' => 'money'], ['label' => '61-90 days', 'value' => (float) $b['61_90'], 'type' => 'money', 'tone' => 'warning'], ['label' => 'Over 90 days', 'value' => (float) $b['over_90'], 'type' => 'money', 'tone' => 'danger']],
            'sections' => array_values(array_filter($sections, fn ($s) => $s['rows'])),
            'priorities' => $r['priorities'] ?? null,
        ];
    }

    private function agedReceivables(string $asOf): array
    {
        return $this->agedDoc('Aged receivables', $asOf, $this->reports->getAgedReceivables($asOf), ['patient_overdrafts' => 'Patients who owe', 'hmo_claims' => 'HMO claims not yet paid', 'gl_receivables' => 'Ledger receivable accounts', 'staff_receivables' => 'Staff bills']);
    }

    private function agedPayables(string $asOf): array
    {
        $r = $this->reports->getAgedPayables($asOf);
        $doc = $this->agedDoc('Aged payables', $asOf, $r, ['supplier_payables' => 'Suppliers (POs received, unpaid)', 'patient_deposits' => 'Patient deposits held', 'supplier_credits' => 'Supplier credit balances', 'gl_payables' => 'Ledger payable accounts']);
        if (!empty($r['priorities'])) {
            $doc['sections'][] = [
                'title' => 'Pay first (oldest and largest)',
                'columns' => [['key' => 'vendor_name', 'label' => 'Supplier', 'type' => 'text', 'primary' => true], ['key' => 'reference', 'label' => 'PO', 'type' => 'text'], ['key' => 'date', 'label' => 'Date', 'type' => 'text'], ['key' => 'days_overdue', 'label' => 'Days', 'type' => 'number'], $this->money('amount', 'Amount')],
                'rows' => $r['priorities'],
            ];
        }
        unset($doc['priorities']);

        return $doc;
    }

    private function dailyAudit(string $date): array
    {
        $r = $this->reports->getDailyJournalAudit($date);

        return [
            'title' => 'Daily journal audit',
            'subtitle' => $date,
            'kpis' => [['label' => 'Entries', 'value' => (int) $r['total_entries'], 'type' => 'number'], ['label' => 'Debits', 'value' => (float) $r['total_debit'], 'type' => 'money'], ['label' => 'Credits', 'value' => (float) $r['total_credit'], 'type' => 'money']],
            'checks' => [['label' => abs((float) $r['total_debit'] - (float) $r['total_credit']) < 0.01 ? 'Debits equal credits for the day' : 'The day does not balance', 'ok' => abs((float) $r['total_debit'] - (float) $r['total_credit']) < 0.01]],
            'sections' => [[
                'title' => 'Entries',
                'columns' => [['key' => 'entry_number', 'label' => 'Entry', 'type' => 'text', 'primary' => true], ['key' => 'description', 'label' => 'Description', 'type' => 'text'], ['key' => 'status', 'label' => 'Status', 'type' => 'text'], ['key' => 'source', 'label' => 'Source', 'type' => 'text'], $this->money('debit', 'Debit'), $this->money('credit', 'Credit'), ['key' => 'created_by', 'label' => 'By', 'type' => 'text']],
                'rows' => $r['entries'],
            ]],
        ];
    }

    /** Account groups (with their class) for the new-account form. */
    public function accountGroups()
    {
        return response()->json(['success' => true, 'groups' => \App\Models\Accounting\AccountGroup::with('accountClass')->orderBy('code')->get()->map(fn ($g) => [
            'id' => $g->id,
            'code' => $g->code,
            'name' => $g->name,
            'class' => $g->accountClass?->name,
            'normal_balance' => $g->accountClass?->normal_balance,
        ])]);
    }

    /** Staff who can be a petty cash custodian (name search). */
    public function staffSearch(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        $q = \App\Models\User::where('status', 1);
        if ($term !== '') {
            $q->where(fn ($w) => $w->where('surname', 'like', "%{$term}%")->orWhere('firstname', 'like', "%{$term}%")->orWhereRaw("CONCAT_WS(' ', surname, firstname, othername) LIKE ?", ["%{$term}%"]));
        }

        return response()->json(['success' => true, 'users' => $q->orderBy('surname')->limit(25)->get(['id', 'surname', 'firstname', 'othername'])->map(fn ($u) => ['id' => $u->id, 'name' => trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : ''))])]);
    }

    /** The reports page's saved filters: the user's own and the shared ones (the web page's list, as JSON). */
    public function savedFilters(Request $request)
    {
        $rows = \App\Models\Accounting\SavedReportFilter::where(fn ($q) => $q->where('created_by', auth()->id())->orWhere('is_shared', true))
            ->orderBy('name')->limit(100)->get(['id', 'name', 'report_type', 'filters', 'is_shared', 'created_by']);

        return response()->json(['filters' => $rows->map(fn ($f) => $f->toArray() + ['mine' => (int) $f->created_by === (int) auth()->id()])]);
    }
}
