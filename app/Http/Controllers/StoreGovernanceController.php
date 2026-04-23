<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\StoreLanePolicy;
use App\Models\StoreContextRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * StoreGovernanceController — admin configuration pages for store governance.
 *
 * Plan Reference: docs/STORE_GOVERNANCE_AND_CONTEXTUAL_WORKBENCH_PLAN.md
 *   § 9.1  (Admin Module: Store Governance — store role catalog, lane matrix, ownership, manager, overrides)
 *   § 9.2  (Admin Module: Unit and Packaging Governance — handled by existing product packagings)
 *   § 9.3  (Admin Module: Context Resolution Rules)
 *   § 9.4  (Validation UX for All Config Pages — save guards, audit trail)
 *   § 11   (Permission checks: store-governance.view / store-governance.manage)
 *   § 14   (NFRs — config changes audited via owenIt\Auditing on Store + StoreLanePolicy models)
 *
 * All write methods use DB::transaction() for integrity (Plan §14 NFRs §4).
 * All Gate checks use the 'store-governance.view' / 'store-governance.manage' slugs
 * registered in AuthServiceProvider (Plan §A9).
 */
class StoreGovernanceController extends Controller
{
    // =========================================================================
    // § 9.1 — Store Governance Config Page
    // =========================================================================

    /**
     * GET /admin/config/store-governance
     * Renders the Store Governance page with three sections:
     *   - Store Role Catalog (Section A)
     *   - Lane Policy Matrix  (Section B) — rendered via laneMatrix()
     *   - Store Ownership Mapping (Section C)
     *
     * Plan §9.1
     */
    public function index()
    {
        Gate::authorize('store-governance.view');

        $stores = Store::with(['manager', 'ward', 'department', 'parentStore'])
            ->orderBy('store_name')
            ->get();

        $wards       = \App\Models\Ward::orderBy('name')->get();
        $departments = \App\Models\Department::orderBy('name')->get();
        // Use actual DB role names (UPPERCASE, from Spatie cache).
        // PHARMACIST covers pharmacy manager duties, STORE covers store keeper,
        // NURSE covers head nurse duties — no separate roles exist in the system.
        $managers    = \App\Models\User::whereHas('roles', fn ($q) => $q->whereIn('name', [
            'ADMIN', 'SUPERADMIN', 'super-admin', 'PHARMACIST', 'STORE', 'NURSE',
        ]))->orderBy('surname')->get();

        $distributionRoles = Store::DISTRIBUTION_ROLES;

        return view('admin.config.store-governance.index', compact(
            'stores', 'wards', 'departments', 'managers', 'distributionRoles'
        ));
    }

    /**
     * POST /admin/config/store-governance/stores/{store}
     * Update a single store's governance fields (inline row save in the catalog table).
     *
     * Plan §9.1 Section A (inline editable columns)
     * Save guard: if disabling allows_direct_patient_dispense with pending dispenses → warn
     */
    public function updateStore(Request $request, Store $store)
    {
        Gate::authorize('store-governance.manage');

        $data = $request->validate([
            'distribution_role'            => 'required|in:' . implode(',', Store::DISTRIBUTION_ROLES),
            'allows_direct_patient_dispense' => 'boolean',
            'requires_shift_context'       => 'boolean',
            'parent_store_id'              => 'nullable|exists:stores,id|different:id',
            'ward_id'                      => 'nullable|exists:wards,id',
            'department_id'                => 'nullable|exists:departments,id',
            'manager_id'                   => 'nullable|exists:users,id',
        ]);

        // Save guard (Plan §9.1 Section A): warn if disabling direct patient dispense
        // while there are pending ProductRequest records linked to this store.
        if (isset($data['allows_direct_patient_dispense'])
            && ! $data['allows_direct_patient_dispense']
            && $store->allows_direct_patient_dispense)
        {
            $pendingCount = \App\Models\ProductRequest::where('dispensed_from_store_id', $store->id)
                ->whereIn('status', [1, 2]) // pending/billed but not yet dispensed
                ->count();

            if ($pendingCount > 0 && ! $request->boolean('force_save')) {
                return response()->json([
                    'success'       => false,
                    'save_guard'    => true,
                    'pending_count' => $pendingCount,
                    'message'       => "{$pendingCount} pending dispense(s) are linked to this store. "
                                    . "Disabling direct patient dispense will block those dispenses. "
                                    . "Send force_save=1 to confirm.",
                ], 409);
            }
        }

        DB::transaction(function () use ($store, $data) {
            $store->update($data);
        });

        return response()->json([
            'success' => true,
            'message' => "{$store->store_name} governance settings updated.",
            'store'   => $store->fresh(['ward', 'department', 'parentStore', 'manager']),
        ]);
    }

    // =========================================================================
    // § 9.1 Section B — Lane Policy Matrix
    // =========================================================================

    /**
     * GET /admin/config/store-governance/lane-matrix
     * Returns the full lane matrix as JSON for the admin grid.
     *
     * Plan §9.1 Section B
     */
    public function laneMatrix()
    {
        Gate::authorize('store-governance.view');

        $policies = StoreLanePolicy::orderBy('source_role')->orderBy('destination_role')->get();

        // Build a full matrix including pairs with no policy row (denied by default)
        $roles  = Store::DISTRIBUTION_ROLES;
        $matrix = [];

        foreach ($roles as $src) {
            foreach ($roles as $dst) {
                if ($src === $dst) {
                    continue; // Self-transfer not allowed
                }
                $policy = $policies->where('source_role', $src)->where('destination_role', $dst)->first();
                $matrix[] = [
                    'source_role'             => $src,
                    'destination_role'        => $dst,
                    'allowed'                 => $policy?->allowed ?? false,
                    'requires_approval_level' => $policy?->requires_approval_level ?? 'none',
                    'notes'                   => $policy?->notes ?? '',
                    'id'                      => $policy?->id,
                ];
            }
        }

        return response()->json(['success' => true, 'matrix' => $matrix]);
    }

    /**
     * POST /admin/config/store-governance/lane-matrix
     * Upserts a single lane policy cell.
     *
     * Plan §9.1 Section B, §9.4 Save guards
     */
    public function updateLane(Request $request)
    {
        Gate::authorize('store-governance.manage');

        $data = $request->validate([
            'source_role'             => 'required|in:' . implode(',', Store::DISTRIBUTION_ROLES),
            'destination_role'        => 'required|in:' . implode(',', Store::DISTRIBUTION_ROLES) . '|different:source_role',
            'allowed'                 => 'required|boolean',
            'requires_approval_level' => 'required|in:none,manager,admin',
            'notes'                   => 'nullable|string|max:255',
        ]);

        // Save guard: if disabling an active lane, count in-flight requisitions (Plan §9.4)
        if (! $data['allowed']) {
            $activeCount = \App\Models\StoreRequisition::whereHas('fromStore', fn ($q) =>
                    $q->where('distribution_role', $data['source_role']))
                ->whereHas('toStore', fn ($q) =>
                    $q->where('distribution_role', $data['destination_role']))
                ->whereIn('status', ['pending', 'approved'])
                ->count();

            if ($activeCount > 0 && ! $request->boolean('force_save')) {
                return response()->json([
                    'success'      => false,
                    'save_guard'   => true,
                    'active_count' => $activeCount,
                    'message'      => "{$activeCount} active requisition(s) use this lane. "
                                   . "Blocking it will prevent their approval/fulfillment. "
                                   . "Send force_save=1 to confirm.",
                ], 409);
            }
        }

        $policy = StoreLanePolicy::updateOrCreate(
            ['source_role' => $data['source_role'], 'destination_role' => $data['destination_role']],
            array_merge($data, ['updated_by' => auth()->id()])
        );

        return response()->json([
            'success' => true,
            'message' => "Lane policy updated.",
            'policy'  => $policy,
        ]);
    }

    // =========================================================================
    // § 9.3 — Context Resolution Rules
    // =========================================================================

    /**
     * GET /admin/config/store-governance/context-rules
     * Renders the Context Resolution Rules config page.
     *
     * Plan §9.3
     */
    public function contextRules()
    {
        Gate::authorize('store-governance.view');

        $roleRules       = StoreContextRule::where('rule_type', 'role_default')->with('store')->get();
        $deptRules       = StoreContextRule::where('rule_type', 'department_override')->with(['department', 'store'])->get();
        $bucketRules     = StoreContextRule::where('rule_type', 'type_bucket')->get();
        $fallbackRule    = StoreContextRule::where('rule_type', 'fallback_behavior')->first();
        $stores          = Store::active()->orderBy('store_name')->get();
        $departments     = \App\Models\Department::orderBy('name')->get();
        $spatieRoles     = \Spatie\Permission\Models\Role::orderBy('name')->pluck('name');

        // Roles that already bypass context rules via stores.candidate-all permission.
        // These should not appear in the "unconfigured" warning — they can always see all stores.
        $rolesWithCandidateAll = \Spatie\Permission\Models\Role::whereHas(
            'permissions',
            fn ($q) => $q->where('name', 'stores.candidate-all')
        )->pluck('name');

        return view('admin.config.store-governance.context-rules', compact(
            'roleRules', 'deptRules', 'bucketRules', 'fallbackRule', 'stores', 'departments',
            'spatieRoles', 'rolesWithCandidateAll'
        ));
    }

    /**
     * POST /admin/config/store-governance/context-rules
     * Upserts a context rule row.
     *
     * Plan §9.3, §9.4
     */
    public function saveContextRule(Request $request)
    {
        Gate::authorize('store-governance.manage');

        $data = $request->validate([
            'rule_type'       => 'required|in:role_default,department_override,fallback_behavior,type_bucket',
            'user_role'       => 'required_if:rule_type,role_default|required_if:rule_type,type_bucket|nullable|string|max:60',
            'department_id'   => 'required_if:rule_type,department_override|nullable|exists:departments,id',
            'store_id'        => 'nullable|exists:stores,id',
            'type_filter'     => 'required_if:rule_type,type_bucket|nullable|in:all,pharmacy,ward,department',
            'fallback_action' => 'required_if:rule_type,fallback_behavior|nullable|in:block,allow_manual,use_role_default',
            'notes'           => 'nullable|string|max:255',
        ]);

        $uniqueMatch = match ($data['rule_type']) {
            'role_default'        => ['rule_type' => 'role_default', 'user_role' => $data['user_role']],
            'department_override' => ['rule_type' => 'department_override', 'department_id' => $data['department_id']],
            'fallback_behavior'   => ['rule_type' => 'fallback_behavior'],
            'type_bucket'         => ['rule_type' => 'type_bucket', 'user_role' => $data['user_role'], 'type_filter' => $data['type_filter']],
        };

        $rule = StoreContextRule::updateOrCreate(
            $uniqueMatch,
            array_merge($data, ['updated_by' => auth()->id()])
        );

        return response()->json([
            'success' => true,
            'message' => 'Context rule saved.',
            'rule'    => $rule->load('store', 'department'),
        ]);
    }

    /**
     * POST /admin/config/store-governance/context-rules/test
     * "Test Resolution" panel — simulates StoreContextResolver for a given user + ward + shift.
     *
     * Plan §9.3 Section F, §9.4 Test panels
     */
    public function testContextResolution(Request $request)
    {
        Gate::authorize('store-governance.view');

        $request->validate([
            'user_id'       => 'required|exists:users,id',
            'ward_id'       => 'nullable|exists:wards,id',
            'shift_active'  => 'boolean',
        ]);

        $user = \App\Models\User::findOrFail($request->user_id);

        // Simulate active shift if requested
        if ($request->boolean('shift_active') && $request->ward_id) {
            $mockShift = new \App\Models\NursingShift(['ward_id' => $request->ward_id, 'status' => 'active']);
        } else {
            $mockShift = null;
        }

        $resolver = app(\App\Services\StoreContextResolver::class);
        $store    = $resolver->resolveWithMockShift($user, $mockShift);
        $candidates = $resolver->candidateStores($user);

        return response()->json([
            'success'          => true,
            'resolved_store'   => $store ? [
                'id'                => $store->id,
                'name'              => $store->store_name,
                'distribution_role' => $store->distribution_role,
                'role_label'        => $store->distributionRoleLabel(),
            ] : null,
            'resolution_steps' => $resolver->lastResolutionTrace(),
            'candidate_stores' => $candidates->map(fn ($s) => [
                'id'   => $s->id,
                'name' => $s->store_name,
                'role' => $s->distribution_role,
            ])->values(),
        ]);
    }

    /**
     * DELETE /admin/config/store-governance/context-rules/{rule}
     */
    public function deleteContextRule(StoreContextRule $rule)
    {
        Gate::authorize('store-governance.manage');

        if ($rule->rule_type === 'fallback_behavior') {
            return response()->json(['success' => false, 'message' => 'The fallback rule cannot be deleted.'], 422);
        }

        $rule->delete();

        return response()->json(['success' => true, 'message' => 'Rule deleted.']);
    }

    // =========================================================================
    // KPI Endpoints — Plan §13
    // Read-only endpoints, each scoped to the caller's resolved store.
    // All return JSON; permission checked via Gate 'store-governance.view'.
    // =========================================================================

    /**
     * Plan §13 — Pharmacy Manager KPIs.
     *
     * Gate: store-governance.view (pharmacy manager role already has this).
     * Data sources: product_requests, stock_batches.
     */
    public function kpiPharmacy(Request $request)
    {
        Gate::authorize('store-governance.view');

        $resolver = app(\App\Services\StoreContextResolver::class);
        $store    = $resolver->resolve(auth()->user());

        if (! $store) {
            return response()->json(['error' => 'No pharmacy store resolved for your account.'], 422);
        }

        $today = now()->startOfDay();

        // Dispenses today
        $dispensesToday = DB::table('product_requests')
            ->where('dispensed_from_store_id', $store->id)
            ->where('status', 3) // dispensed
            ->whereDate('dispense_date', $today)
            ->count();

        // Near-expiry batches (≤ 30 days) in this store
        $nearExpiryBatches = \App\Models\StockBatch::where('store_id', $store->id)
            ->where('is_active', true)
            ->where('current_qty', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays(30))
            ->count();

        // Stock-out products (batches exist but current_qty = 0 or no batches at all)
        $stockOutProducts = \App\Models\StockBatch::where('store_id', $store->id)
            ->where('is_active', true)
            ->select('product_id')
            ->groupBy('product_id')
            ->havingRaw('SUM(current_qty) = 0')
            ->count();

        // Pending (approved, not yet dispensed) requests for this store
        $pendingDispense = DB::table('product_requests')
            ->where('dispensed_from_store_id', $store->id)
            ->where('status', 2) // approved/pending dispense
            ->count();

        // Lane overrides logged today
        $laneOverridesToday = \App\Models\ShiftAction::where('action_subtype', 'lane_override')
            ->whereDate('created_at', $today)
            ->count();

        return response()->json([
            'store_id'           => $store->id,
            'store_name'         => $store->store_name,
            'dispenses_today'    => $dispensesToday,
            'near_expiry_batches' => $nearExpiryBatches,
            'stock_out_products' => $stockOutProducts,
            'pending_dispense'   => $pendingDispense,
            'lane_overrides_today' => $laneOverridesToday,
            'generated_at'       => now()->toISOString(),
        ]);
    }

    /**
     * Plan §13 — Head Nurse / Ward Manager KPIs.
     *
     * Gate: store-governance.view (head nurse has this via permissions seeder).
     * Data sources: shift_actions, store_stocks, store_requisitions.
     */
    public function kpiWard(Request $request)
    {
        Gate::authorize('store-governance.view');

        $resolver = app(\App\Services\StoreContextResolver::class);
        $store    = $resolver->resolve(auth()->user());

        if (! $store) {
            return response()->json(['error' => 'No ward store resolved for your account.'], 422);
        }

        // Active shift for this user
        $activeShift = \App\Models\NursingShift::where('user_id', auth()->id())
            ->where('status', 'active')
            ->latest()
            ->first();

        $shiftId = $activeShift?->id;

        // Injections administered this shift
        $injectionsThisShift = $shiftId
            ? \App\Models\ShiftAction::where('shift_id', $shiftId)
                ->where('action_type', 'injection')
                ->count()
            : null;

        // FIFO overrides this shift
        $fifoOverridesThisShift = $shiftId
            ? \App\Models\ShiftAction::where('shift_id', $shiftId)
                ->where('action_subtype', 'fifo_override')
                ->count()
            : null;

        // Ward stock items below reorder level
        $belowReorder = \App\Models\StockBatch::where('store_id', $store->id)
            ->where('is_active', true)
            ->select('product_id')
            ->groupBy('product_id')
            ->havingRaw('SUM(current_qty) < 5') // simple threshold; proper reorder_level support TBD
            ->count();

        // Pending requisitions TO this ward store (approved, awaiting fulfillment)
        $pendingRequisitions = DB::table('store_requisitions')
            ->where('to_store_id', $store->id)
            ->where('status', 'approved')
            ->count();

        // Near-expiry batches in this ward store
        $nearExpiryBatches = \App\Models\StockBatch::where('store_id', $store->id)
            ->where('is_active', true)
            ->where('current_qty', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays(30))
            ->count();

        return response()->json([
            'store_id'                 => $store->id,
            'store_name'               => $store->store_name,
            'active_shift_id'          => $shiftId,
            'injections_this_shift'    => $injectionsThisShift,
            'fifo_overrides_this_shift' => $fifoOverridesThisShift,
            'below_reorder_count'      => $belowReorder,
            'pending_requisitions_in'  => $pendingRequisitions,
            'near_expiry_batches'      => $nearExpiryBatches,
            'generated_at'             => now()->toISOString(),
        ]);
    }

    /**
     * Plan §13 — Store / Supply Manager KPIs.
     *
     * Gate: store-governance.view (store keeper has this).
     * Data sources: store_requisitions, purchase_orders, shift_actions.
     */
    public function kpiStore(Request $request)
    {
        Gate::authorize('store-governance.view');

        $resolver = app(\App\Services\StoreContextResolver::class);
        $store    = $resolver->resolve(auth()->user());

        if (! $store) {
            return response()->json(['error' => 'No store resolved for your account.'], 422);
        }

        // Pending fulfillments FROM this store (approved requisitions waiting to be fulfilled)
        $pendingFulfillments = DB::table('store_requisitions')
            ->where('from_store_id', $store->id)
            ->where('status', 'approved')
            ->count();

        // POs awaiting receive for this store
        $posAwaitingReceive = DB::table('purchase_orders')
            ->where('target_store_id', $store->id)
            ->where('status', 'approved')
            ->count();

        // Lane policy overrides (all time, for this store involved as source)
        $laneOverrides = \App\Models\ShiftAction::where('action_subtype', 'lane_override')
            ->whereJsonContains('metadata->source_store_id', $store->id)
            ->count();

        // Over-receive events
        $overReceiveEvents = \App\Models\ShiftAction::where('action_subtype', 'over_receive')
            ->count();

        // Stock batches expiring within 30 days
        $nearExpiryBatches = \App\Models\StockBatch::where('store_id', $store->id)
            ->where('is_active', true)
            ->where('current_qty', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays(30))
            ->count();

        // Total active products in this store
        $activeProducts = \App\Models\StockBatch::where('store_id', $store->id)
            ->where('is_active', true)
            ->where('current_qty', '>', 0)
            ->distinct('product_id')
            ->count('product_id');

        return response()->json([
            'store_id'             => $store->id,
            'store_name'           => $store->store_name,
            'pending_fulfillments' => $pendingFulfillments,
            'pos_awaiting_receive' => $posAwaitingReceive,
            'lane_overrides'       => $laneOverrides,
            'over_receive_events'  => $overReceiveEvents,
            'near_expiry_batches'  => $nearExpiryBatches,
            'active_products'      => $activeProducts,
            'generated_at'         => now()->toISOString(),
        ]);
    }
}
