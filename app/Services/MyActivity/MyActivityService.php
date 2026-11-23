<?php

namespace App\Services\MyActivity;

use App\Http\Controllers\ReceptionWorkbenchController;
use App\Models\ImagingServiceRequest;
use App\Models\LabServiceRequest;
use App\Models\PharmacyReturn;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * "My activity": what a staff member did to items in a period, as a pipeline of stages per workbench.
 *
 * A stage is one thing a person can do (book, bill, take a sample, enter a result, approve, dispense,
 * return). Each stage knows how to find the items *I* did it to and how to describe an item in one common
 * shape, so the same console (web modal and staff app) navigates every workbench the same way:
 * a strip of stages with my counts, "All my work" merging them, and a "where is it now" lens.
 *
 * Money collected through me is the separate cash view (BillingWorkbenchController@getMyTransactions).
 */
class MyActivityService
{
    public const STATE_DONE = 'done';
    public const STATE_WAITING = 'waiting'; // waiting on someone else
    public const STATE_ATTENTION = 'attention'; // needs me (e.g. a rejected result)

    private const SCAN_LIMIT = 5000;

    /** @return array<int, array{key:string,label:string,icon:string,tone:string,hint:string}> */
    public function stages(string $workbench, User $user, Carbon $from, Carbon $to): array
    {
        $defs = $this->definitions($workbench, $user);
        $out = [];
        foreach ($defs as $d) {
            $visible = $d['visible'] ?? true;
            // A stage that depends on rights or a setting is still shown when I did something in it in this period.
            $has = $visible === true ? true : ($this->count($d, $user, $from, $to) > 0);
            if ($visible === true || $has) {
                $out[] = ['key' => $d['key'], 'label' => $d['label'], 'icon' => $d['icon'], 'tone' => $d['tone'], 'hint' => $d['hint']];
            }
        }

        return $out;
    }

    /**
     * Everything for the console: the stages with my counts (and how many are waiting / need me), the
     * items of the chosen stage (or all of them merged) and rollups for the filters.
     *
     * @return array<string, mixed>
     */
    public function run(string $workbench, User $user, Carbon $from, Carbon $to, string $stage = 'all', array $filters = []): array
    {
        $defs = collect($this->definitions($workbench, $user))->keyBy('key');
        $perStage = [];
        $pipeline = [];
        $limitHit = false;
        foreach ($defs as $key => $d) {
            $rows = $this->items($d, $user, $from, $to);
            $limitHit = $limitHit || $rows->count() >= self::SCAN_LIMIT;
            $visible = ($d['visible'] ?? true) === true || $rows->isNotEmpty();
            if (!$visible) {
                continue;
            }
            $perStage[$key] = $rows;
            $pipeline[] = [
                'key' => $key, 'label' => $d['label'], 'icon' => $d['icon'], 'tone' => $d['tone'], 'hint' => $d['hint'],
                'count' => $rows->count(),
                'waiting' => $rows->where('state', self::STATE_WAITING)->count(),
                'attention' => $rows->where('state', self::STATE_ATTENTION)->count(),
            ];
        }

        // One row per item with every thing I did to it, for "All my work".
        $merged = collect($perStage)->flatten(1)->groupBy(fn ($r) => $r['source'] . ':' . $r['id'])->map(function (Collection $g) {
            $first = $g->sortByDesc('at')->first();
            $first['my_actions'] = $g->map(fn ($r) => ['stage' => $r['stage'], 'label' => $r['action_label'], 'at' => $r['at']])->sortBy('at')->values()->all();

            return $first;
        })->values();

        $selected = $stage === 'all' ? $merged : ($perStage[$stage] ?? collect())->map(function ($r) {
            $r['my_actions'] = [['stage' => $r['stage'], 'label' => $r['action_label'], 'at' => $r['at']]];

            return $r;
        })->values();
        $allCount = $merged->count();

        $selected = $this->filter($selected, $filters)->sortByDesc('at')->values();

        return [
            'pipeline' => array_merge([[
                'key' => 'all', 'label' => 'All my work', 'icon' => 'mdi-view-list', 'tone' => 'primary', 'hint' => 'Everything I did, one row per item',
                'count' => $allCount,
                'waiting' => $merged->where('state', self::STATE_WAITING)->count(),
                'attention' => $merged->where('state', self::STATE_ATTENTION)->count(),
            ]], $pipeline),
            'stage' => $stage,
            'items' => $selected->take(250)->values(),
            'total_count' => $selected->count(),
            'is_truncated' => $selected->count() > 250 || $limitHit,
            'summary' => $this->summary($selected),
        ];
    }

    private function filter(Collection $rows, array $f): Collection
    {
        if (!empty($f['state'])) {
            $rows = $rows->where('state', $f['state']);
        }
        if (!empty($f['department_id'])) {
            $rows = $rows->where('department_id', (int) $f['department_id']);
        }
        if (!empty($f['item_type'])) {
            $map = ['product' => 'Product', 'service' => 'Service', 'custom' => 'Custom'];
            $rows = $rows->where('type', $map[$f['item_type']] ?? $f['item_type']);
        }
        if (!empty($f['category'])) {
            $rows = $rows->where('category', $f['category']);
        }

        return $rows->values();
    }

    private function summary(Collection $rows): array
    {
        $rollup = fn (Collection $items, string $key, ?string $nameKey = null) => $items->groupBy($key)->map(fn ($g, $k) => [
            'id' => $nameKey ? $g->first()[$key] : null,
            'name' => $nameKey ? ($g->first()[$nameKey] ?: 'None') : (string) $k,
            'count' => $g->count(),
            'amount' => round($g->sum('amount'), 2),
        ])->sortByDesc('amount')->values();

        return [
            'count' => $rows->count(),
            'done' => $rows->where('state', self::STATE_DONE)->count(),
            'waiting' => $rows->where('state', self::STATE_WAITING)->count(),
            'attention' => $rows->where('state', self::STATE_ATTENTION)->count(),
            'value' => round($rows->sum('amount'), 2),
            'patient_pays' => round($rows->sum('payable_amount'), 2),
            'claims' => round($rows->sum('claims_amount'), 2),
            'service_total' => round($rows->where('type', 'Service')->sum('amount'), 2),
            'product_total' => round($rows->where('type', 'Product')->sum('amount'), 2),
            'service_kpi' => ['count' => $rows->whereIn('type', ['Service', 'Custom'])->count()],
            'product_kpi' => ['count' => $rows->where('type', 'Product')->count()],
            'department_kpis' => $rollup($rows->filter(fn ($r) => $r['department_id']), 'department_id', 'department_name'),
            'service_category_kpis' => $rollup($rows->whereIn('type', ['Service', 'Custom']), 'category'),
            'product_category_kpis' => $rollup($rows->where('type', 'Product'), 'category'),
        ];
    }

    private function count(array $d, User $user, Carbon $from, Carbon $to): int
    {
        return $d['query']($user, $from, $to)->count();
    }

    private function items(array $d, User $user, Carbon $from, Carbon $to): Collection
    {
        return $d['query']($user, $from, $to)->limit(self::SCAN_LIMIT)->get()->map(function ($m) use ($d) {
            $row = $d['map']($m);
            $row['stage'] = $d['key'];
            $row['action_label'] = $d['label'];
            $row['source'] = $d['source'];
            $row['at'] = $row['at'] ?? null;

            return $row;
        });
    }

    // ───────────────────────────── stage definitions ─────────────────────────────

    /** Normalised description of a product-or-service request line (the money side of an item). */
    private function psrRow(?ProductOrServiceRequest $p): array
    {
        return [
            // Product lines carry payable and claims rather than an amount.
            'amount' => ((float) optional($p)->amount) ?: ((float) optional($p)->payable_amount + (float) optional($p)->claims_amount),
            'payable_amount' => (float) optional($p)->payable_amount,
            'claims_amount' => (float) optional($p)->claims_amount,
            'coverage_mode' => optional($p)->coverage_mode,
            'discount' => (float) optional($p)->discount,
            'department_id' => optional($p)->department_id,
            'department_name' => optional(optional($p)->department)->name,
            'payment_reference' => optional(optional($p)->payment)->reference_no,
            'paid' => !empty(optional($p)->payment_id),
            'hmo_validated' => optional($p)->validation_status === 'approved',
            'hmo_pending' => (float) optional($p)->claims_amount > 0 && optional($p)->validation_status !== 'approved' && !((float) optional($p)->payable_amount > 0),
        ];
    }

    /** "Paid / waiting for payment" as the state of a billed item. */
    private function payState(array $money): array
    {
        if ($money['paid'] || $money['hmo_validated']) {
            return [self::STATE_DONE, $money['paid'] ? 'Paid' : 'HMO validated'];
        }

        return [self::STATE_WAITING, $money['hmo_pending'] ? 'Awaiting HMO validation' : 'Awaiting payment'];
    }

    private function patientBits($patient): array
    {
        return [
            'patient_id' => optional($patient)->id,
            'patient_name' => $patient && $patient->user ? userfullname($patient->user_id) : 'N/A',
            'file_no' => optional($patient)->file_no,
        ];
    }

    /** Lab/imaging request → a common row. $labels maps the request status to [state, label]. */
    private function serviceRow($r, array $statusMap, string $atColumn, string $type = 'Service'): array
    {
        $money = $this->psrRow($r->productOrServiceRequest);
        [$state, $label] = $statusMap[(int) $r->status] ?? [self::STATE_DONE, 'Done'];

        return array_merge($this->patientBits($r->patient), $money, [
            'id' => $r->id,
            'at' => optional($r->{$atColumn})->format('Y-m-d H:i:s'),
            'item_name' => optional($r->service)->service_name ?: 'Investigation',
            'type' => $type,
            'category' => optional(optional($r->service)->category)->category_name ?: 'Uncategorised',
            'qty' => 1,
            'unit_price' => $money['amount'],
            'state' => $state,
            'state_label' => $label,
        ]);
    }

    private function definitions(string $workbench, User $user): array
    {
        $staff = $user->staff_profile ?? null;
        $isApprover = ($staff && ($staff->is_unit_head || $staff->is_dept_head)) || $user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']);

        $booked = [
            'key' => 'booked', 'label' => 'Booked by me', 'icon' => 'mdi-calendar-plus', 'tone' => 'primary',
            'hint' => 'Services and products I booked for patients', 'source' => 'psr', 'visible' => true,
            'query' => fn (User $u, Carbon $f, Carbon $t) => ProductOrServiceRequest::query()->whereNull('removed_at')
                ->where(fn ($q) => $q->where('staff_user_id', $u->id)->orWhere('created_by', $u->id))
                ->whereBetween('created_at', [$f, $t])->orderByDesc('created_at')
                ->with(['product.category', 'service.category', 'department', 'payment', 'patient.user']),
            'map' => function (ProductOrServiceRequest $r) {
                $money = $this->psrRow($r);
                [$state, $label] = $this->payState($money);
                $isProduct = !empty($r->product_id);
                $qty = max(1, (int) $r->qty);

                return array_merge($this->patientBits($r->patient), $money, [
                    'id' => $r->id,
                    'at' => optional($r->created_at)->format('Y-m-d H:i:s'),
                    'item_name' => $r->is_free_form ? ($r->free_form_name ?: 'Custom item') : ($isProduct ? optional($r->product)->product_name : optional($r->service)->service_name),
                    'type' => $r->is_free_form ? 'Custom' : ($isProduct ? 'Product' : 'Service'),
                    'category' => $r->is_free_form ? 'Custom Items' : (($isProduct ? optional(optional($r->product)->category)->category_name : optional(optional($r->service)->category)->category_name) ?: 'Uncategorised'),
                    'qty' => $qty,
                    'unit_price' => round(((float) $r->amount) / $qty, 2),
                    'state' => $state,
                    'state_label' => $label,
                ]);
            },
        ];

        $labStatus = [
            1 => [self::STATE_WAITING, 'Awaiting billing'], 2 => [self::STATE_WAITING, 'Awaiting sample'],
            3 => [self::STATE_WAITING, 'Awaiting result'], 4 => [self::STATE_DONE, 'Completed'],
            5 => [self::STATE_WAITING, 'Awaiting approval'], 6 => [self::STATE_ATTENTION, 'Rejected: result to redo'],
        ];
        $labWith = ['service.category', 'productOrServiceRequest.department', 'productOrServiceRequest.payment', 'patient.user'];
        $labStage = fn (string $key, string $label, string $icon, string $tone, string $hint, string $by, string $at, $visible = true) => [
            'key' => $key, 'label' => $label, 'icon' => $icon, 'tone' => $tone, 'hint' => $hint, 'source' => 'lab', 'visible' => $visible,
            'query' => fn (User $u, Carbon $f, Carbon $t) => LabServiceRequest::query()->where($by, $u->id)->whereBetween($at, [$f, $t])->orderByDesc($at)->with($labWith),
            'map' => fn ($r) => $this->serviceRow($r, $labStatus, $at === 'sample_date' ? 'sample_date' : ($at === 'result_date' ? 'result_date' : ($at === 'approved_at' ? 'approved_at' : 'billed_date'))),
        ];
        $imgStatus = $labStatus;
        $imgStage = fn (string $key, string $label, string $icon, string $tone, string $hint, string $by, string $at, $visible = true) => [
            'key' => $key, 'label' => $label, 'icon' => $icon, 'tone' => $tone, 'hint' => $hint, 'source' => 'imaging', 'visible' => $visible,
            'query' => fn (User $u, Carbon $f, Carbon $t) => ImagingServiceRequest::query()->where($by, $u->id)->whereBetween($at, [$f, $t])->orderByDesc($at)->with(['service.category', 'productOrServiceRequest.department', 'productOrServiceRequest.payment', 'patient.user']),
            'map' => fn ($r) => $this->serviceRow($r, $imgStatus, $at === 'result_date' ? 'result_date' : ($at === 'approved_at' ? 'approved_at' : 'billed_date')),
        ];

        $labRequiresApproval = (bool) appsettings('lab_results_require_approval');
        $imgRequiresApproval = (bool) appsettings('imaging_results_require_approval');

        $pharmBilled = [
            'key' => 'billed', 'label' => 'Billed by me', 'icon' => 'mdi-cash-register', 'tone' => 'primary',
            'hint' => 'Prescriptions I billed (handed to the cashier)', 'source' => 'rx', 'visible' => true,
            'query' => fn (User $u, Carbon $f, Carbon $t) => ProductRequest::query()->where('billed_by', $u->id)->whereBetween('billed_date', [$f, $t])->orderByDesc('billed_date')
                ->with(['product.category', 'productOrServiceRequest.department', 'productOrServiceRequest.payment', 'patient.user']),
            'map' => function (ProductRequest $r) {
                $money = $this->psrRow($r->productOrServiceRequest);
                [$state, $label] = (int) $r->status >= 3 ? [self::STATE_DONE, 'Dispensed'] : $this->payState($money);
                $qty = max(1, (int) $r->qty);

                return array_merge($this->patientBits($r->patient), $money, [
                    'id' => $r->id, 'at' => optional($r->billed_date)->format('Y-m-d H:i:s'),
                    'item_name' => $r->item_name, 'type' => $r->is_free_form ? 'Custom' : 'Product',
                    'category' => optional(optional($r->product)->category)->category_name ?: 'Uncategorised',
                    'qty' => $qty, 'unit_price' => round($money['amount'] / $qty, 2), 'state' => $state, 'state_label' => $label,
                ]);
            },
        ];
        $pharmDispensed = [
            'key' => 'dispensed', 'label' => 'Dispensed by me', 'icon' => 'mdi-pill', 'tone' => 'success',
            'hint' => 'Medication I handed to patients', 'source' => 'rx', 'visible' => true,
            'query' => fn (User $u, Carbon $f, Carbon $t) => ProductRequest::query()->where('dispensed_by', $u->id)->whereBetween('dispense_date', [$f, $t])->orderByDesc('dispense_date')
                ->with(['product.category', 'productOrServiceRequest.department', 'productOrServiceRequest.payment', 'patient.user', 'pharmacyReturns']),
            'map' => function (ProductRequest $r) {
                $money = $this->psrRow($r->productOrServiceRequest);
                $qty = max(1, (int) $r->qty);
                $returned = (float) $r->pharmacyReturns->whereIn('status', ['pending', 'approved', 'completed'])->sum('qty_returned');

                return array_merge($this->patientBits($r->patient), $money, [
                    'id' => $r->id, 'at' => optional($r->dispense_date)->format('Y-m-d H:i:s'),
                    'item_name' => $r->item_name, 'type' => $r->is_free_form ? 'Custom' : 'Product',
                    'category' => optional(optional($r->product)->category)->category_name ?: 'Uncategorised',
                    'qty' => $qty, 'unit_price' => round($money['amount'] / $qty, 2),
                    'state' => $returned > 0 ? self::STATE_WAITING : self::STATE_DONE,
                    'state_label' => $returned > 0 ? 'Partly or fully returned' : 'Dispensed',
                ]);
            },
        ];
        $pharmReturns = [
            'key' => 'returns', 'label' => 'Returns I raised', 'icon' => 'mdi-keyboard-return', 'tone' => 'warning',
            'hint' => 'Returns I recorded and where they stand', 'source' => 'return', 'visible' => true,
            'query' => fn (User $u, Carbon $f, Carbon $t) => PharmacyReturn::query()->where('created_by', $u->id)->whereBetween('created_at', [$f, $t])->orderByDesc('created_at')->with(['product.category', 'patient.user']),
            'map' => function (PharmacyReturn $r) {
                $st = (string) $r->status;
                $map = ['pending' => [self::STATE_WAITING, 'Awaiting approval'], 'approved' => [self::STATE_WAITING, 'Approved: refund due'], 'completed' => [self::STATE_DONE, 'Refunded'], 'rejected' => [self::STATE_ATTENTION, 'Rejected']];
                [$state, $label] = $map[$st] ?? [self::STATE_DONE, ucfirst($st)];

                return array_merge($this->patientBits($r->patient), [
                    'id' => $r->id, 'at' => optional($r->created_at)->format('Y-m-d H:i:s'),
                    'item_name' => optional($r->product)->product_name ?: 'Item', 'type' => 'Product',
                    'category' => optional(optional($r->product)->category)->category_name ?: 'Uncategorised',
                    'qty' => (int) ceil((float) $r->qty_returned), 'unit_price' => 0, 'amount' => (float) $r->refund_amount,
                    'payable_amount' => (float) $r->refund_to_patient, 'claims_amount' => (float) $r->refund_to_hmo, 'coverage_mode' => null, 'discount' => 0,
                    'department_id' => null, 'department_name' => null, 'payment_reference' => null, 'paid' => false,
                    'state' => $state, 'state_label' => $label,
                ]);
            },
        ];

        return match ($workbench) {
            'lab' => [
                $labStage('billed', 'Billed by me', 'mdi-cash-register', 'primary', 'Investigations I billed', 'billed_by', 'billed_date'),
                $labStage('sample', 'Sample taken by me', 'mdi-test-tube', 'info', 'Samples I collected', 'sample_taken_by', 'sample_date'),
                $labStage('result', 'Result entered by me', 'mdi-clipboard-text', 'success', 'Results I entered', 'result_by', 'result_date'),
                $labStage('approved', 'Approved by me', 'mdi-check-decagram', 'purple', 'Results I approved (approval is on and I am an approver)', 'approved_by', 'approved_at', $labRequiresApproval && $isApprover),
            ],
            'imaging' => [
                $imgStage('billed', 'Billed by me', 'mdi-cash-register', 'primary', 'Imaging I billed', 'billed_by', 'billed_date'),
                $imgStage('result', 'Result entered by me', 'mdi-clipboard-text', 'success', 'Results I entered', 'result_by', 'result_date'),
                $imgStage('approved', 'Approved by me', 'mdi-check-decagram', 'purple', 'Results I approved (approval is on and I am an approver)', 'approved_by', 'approved_at', $imgRequiresApproval && $isApprover),
            ],
            'pharmacy' => [$pharmBilled, $pharmDispensed, $pharmReturns],
            default => [$booked], // reception, billing, nursing, maternity...
        };
    }

    public static function canCollect(User $user): bool
    {
        return ReceptionWorkbenchController::userCanBill($user);
    }
}
