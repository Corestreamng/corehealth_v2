<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreContextRule;
use App\Models\NursingShift;
use App\Models\User;
use Illuminate\Support\Facades\Session;

/**
 * StoreContextResolver — resolves the correct Store for a given user.
 *
 * Plan Reference: docs/STORE_GOVERNANCE_AND_CONTEXTUAL_WORKBENCH_PLAN.md §10
 *
 * 5-Step resolution chain (in order of priority):
 *   Step 1 — Session explicit override (store-context.change-manual permission required)
 *   Step 2 — Active NursingShift → Store with matching ward_id (ROLE_WARD)
 *   Step 3 — User department → Store with matching department_id (ROLE_DEPARTMENT),
 *             checked against StoreContextRule::storeForDepartment()
 *   Step 4 — User default_store_id (direct column on users table, if exists)
 *   Step 5 — StoreContextRule::storeForRole(primary Spatie role)
 *
 * If all 5 steps return null:
 *   - Returns null
 *   - Caller consults StoreContextRule::fallbackAction() and responds accordingly
 *   - Workbench controllers show a "Resolve Store Context" banner (Plan §6.1)
 *
 * Each step appends to $this->resolutionTrace[] for the Admin Test Panel (Plan §9.3 §F).
 *
 * NOTE: This service never modifies StockService, RequisitionService, or any existing service.
 *       It only resolves which Store the current user should be operating from.
 *
 * Binding: Registered as a singleton in AppServiceProvider.
 * Usage:   app(StoreContextResolver::class)->resolve($user)
 */
class StoreContextResolver
{
    public const SESSION_KEY = 'store_governance.explicit_store_id';

    /** @var string[] Trace log for each resolution attempt — used by test panel */
    private array $resolutionTrace = [];

    /**
     * Resolve the store for a user using the 5-step chain (Plan §10).
     *
     * @param  User  $user
     * @return Store|null
     */
    public function resolve(User $user): ?Store
    {
        $this->resolutionTrace = [];

        // Step 1 — Session explicit override (Plan §10 Step 1)
        $store = $this->resolveFromSession($user);
        if ($store) return $store;

        // Step 2 — Active nursing shift → ward store (Plan §10 Step 2)
        $activeShift = NursingShift::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('started_at')
            ->first();

        $store = $this->resolveFromShift($activeShift);
        if ($store) return $store;

        // Step 3 — User department → department store (Plan §10 Step 3)
        $store = $this->resolveFromDepartment($user);
        if ($store) return $store;

        // Step 4 — User default_store_id column (Plan §10 Step 4)
        $store = $this->resolveFromUserDefault($user);
        if ($store) return $store;

        // Step 5 — StoreContextRule role default (Plan §10 Step 5)
        $store = $this->resolveFromRoleRule($user);
        if ($store) return $store;

        // Step 6 — Role-based hard-coded fallbacks for clinical roles that have
        // no role_default rule configured yet (common on fresh installs).
        // Admins and clinical roles get a sensible default so the workbench
        // is usable out-of-the-box. Staff can always switch via the store-picker.

        // PHARMACIST → pharmacy hub (is_default + pharmacy type), then any pharmacy store
        if ($user->hasRole('PHARMACIST')) {
            $store = Store::active()
                    ->where('distribution_role', Store::ROLE_PHARMACY_HUB)
                    ->where('is_default', true)
                    ->first()
                ?? Store::active()
                    ->where('distribution_role', Store::ROLE_PHARMACY_HUB)
                    ->orderBy('id')
                    ->first()
                ?? Store::active()
                    ->whereIn('distribution_role', [Store::ROLE_PHARMACY_HUB, Store::ROLE_PHARMACY_SATELLITE])
                    ->orderBy('id')
                    ->first();
            if ($store) {
                $this->resolutionTrace[] = "Step 6 (pharmacist fallback): resolved to [{$store->store_name}] — pharmacy hub default.";
                return $store;
            }
        }

        // STORE role → central store
        if ($user->hasRole('STORE')) {
            $store = Store::active()
                    ->where('distribution_role', Store::ROLE_CENTRAL)
                    ->orderBy('id')
                    ->first();
            if ($store) {
                $this->resolutionTrace[] = "Step 6 (store-keeper fallback): resolved to [{$store->store_name}] — central store default.";
                return $store;
            }
        }

        // ADMIN / SUPERADMIN / super-admin → system default store (is_default = true), then first active
        if ($user->hasAnyRole(['ADMIN', 'SUPERADMIN', 'super-admin'])) {
            $store = Store::active()->where('is_default', true)->first()
                ?? Store::active()->orderBy('id')->first();
            if ($store) {
                $this->resolutionTrace[] = "Step 6 (admin fallback): resolved to [{$store->store_name}] — admin default store.";
                return $store;
            }
        }

        $this->resolutionTrace[] = 'Step 6: no fallback matched → returning null, fallback-action applies.';
        return null;
    }

    /**
     * Variant used by the Admin Test Panel (Plan §9.3 §F).
     * Allows injecting a mock NursingShift for Step 2 without a DB row.
     *
     * @param  User              $user
     * @param  NursingShift|null $mockShift
     * @return Store|null
     */
    public function resolveWithMockShift(User $user, ?NursingShift $mockShift = null): ?Store
    {
        $this->resolutionTrace = [];

        // Step 1 — Session (skipped in test panel — no session context)
        $this->resolutionTrace[] = 'Step 1 (session): skipped in test panel mode.';

        // Step 2 — Mock shift
        $store = $this->resolveFromShift($mockShift);
        if ($store) return $store;

        // Step 3 — Department
        $store = $this->resolveFromDepartment($user);
        if ($store) return $store;

        // Step 4 — User default
        $store = $this->resolveFromUserDefault($user);
        if ($store) return $store;

        // Step 5 — Role rule
        $store = $this->resolveFromRoleRule($user);
        if ($store) return $store;

        $this->resolutionTrace[] = 'Step 5 (role rule): no match → null.';
        return null;
    }

    /**
     * Returns the resolution trace from the last call.
     * Used by Admin Test Resolution panel (Plan §9.3 §F).
     *
     * @return string[]
     */
    public function lastResolutionTrace(): array
    {
        return $this->resolutionTrace;
    }

    /**
     * Set an explicit store in the session (Plan §10 Step 1, Plan §6.1 context-change button).
     * Requires the 'store-context.change-manual' permission (checked by the caller / middleware).
     *
     * @param  int  $storeId
     * @return void
     */
    public function setSessionStore(int $storeId): void
    {
        Session::put(self::SESSION_KEY, $storeId);
    }

    /**
     * Clear the session store override.
     *
     * @return void
     */
    public function clearSessionStore(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private step helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** Step 1 — Session explicit override (Plan §10 Step 1) */
    private function resolveFromSession(User $user): ?Store
    {
        $storeId = Session::get(self::SESSION_KEY);

        if (! $storeId) {
            $this->resolutionTrace[] = 'Step 1 (session): no explicit session store.';
            return null;
        }

        // Requires the permission to change context manually (Plan §11)
        if (! $user->hasPermissionTo('store-context.change-manual')) {
            $this->resolutionTrace[] = 'Step 1 (session): session override present but user lacks store-context.change-manual permission; skipped.';
            return null;
        }

        $store = Store::find($storeId);

        if ($store) {
            $this->resolutionTrace[] = "Step 1 (session): resolved to [{$store->store_name}] (ID {$store->id}) — explicit session override.";
        } else {
            $this->resolutionTrace[] = "Step 1 (session): stale session store ID {$storeId} not found; cleared.";
            $this->clearSessionStore();
        }

        return $store;
    }

    /** Step 2 — Active NursingShift ward store (Plan §10 Step 2) */
    private function resolveFromShift(?NursingShift $shift): ?Store
    {
        if (! $shift || $shift->status !== 'active' || ! $shift->ward_id) {
            $this->resolutionTrace[] = 'Step 2 (shift): no active shift or no ward_id on shift.';
            return null;
        }

        $store = Store::where('ward_id', $shift->ward_id)
            ->where('distribution_role', Store::ROLE_WARD)
            ->active()
            ->first();

        if ($store) {
            $this->resolutionTrace[] = "Step 2 (shift): resolved to [{$store->store_name}] via ward_id={$shift->ward_id}.";
        } else {
            // Try any store linked to the ward, regardless of role
            $store = Store::where('ward_id', $shift->ward_id)->active()->first();
            if ($store) {
                $this->resolutionTrace[] = "Step 2 (shift): no ROLE_WARD store — fell back to [{$store->store_name}] (role: {$store->distribution_role}) for ward_id={$shift->ward_id}.";
            } else {
                $this->resolutionTrace[] = "Step 2 (shift): active shift found but no store is linked to ward_id={$shift->ward_id}.";
            }
        }

        return $store;
    }

    /** Step 3 — User department → department store (Plan §10 Step 3) */
    private function resolveFromDepartment(User $user): ?Store
    {
        // department_id lives on the staff record, not directly on users
        $departmentId = $user->staff_profile?->department_id;

        if (! $departmentId) {
            $this->resolutionTrace[] = 'Step 3 (department): user has no department (no staff record or department_id not set).';
            return null;
        }

        // First check StoreContextRule for a department override
        $store = StoreContextRule::storeForDepartment($departmentId);

        if ($store) {
            $this->resolutionTrace[] = "Step 3 (department): resolved to [{$store->store_name}] via StoreContextRule department_override for dept_id={$departmentId}.";
            return $store;
        }

        // Fallback: find the ROLE_DEPARTMENT store directly linked to this department
        $store = Store::where('department_id', $departmentId)
            ->where('distribution_role', Store::ROLE_DEPARTMENT)
            ->active()
            ->first();

        if ($store) {
            $this->resolutionTrace[] = "Step 3 (department): no rule — resolved to [{$store->store_name}] via stores.department_id for dept_id={$departmentId}.";
        } else {
            $this->resolutionTrace[] = "Step 3 (department): no department rule and no ROLE_DEPARTMENT store for dept_id={$departmentId}.";
        }

        return $store;
    }

    /** Step 4 — User default_store_id column (Plan §10 Step 4) */
    private function resolveFromUserDefault(User $user): ?Store
    {
        // The users table may not have a default_store_id column in all environments.
        // Guard with isset / column existence rather than relying on $fillable.
        $defaultStoreId = data_get($user, 'default_store_id');

        if (! $defaultStoreId) {
            $this->resolutionTrace[] = 'Step 4 (user default): no default_store_id on user.';
            return null;
        }

        $store = Store::find($defaultStoreId);

        if ($store) {
            $this->resolutionTrace[] = "Step 4 (user default): resolved to [{$store->store_name}] via users.default_store_id={$defaultStoreId}.";
        } else {
            $this->resolutionTrace[] = "Step 4 (user default): users.default_store_id={$defaultStoreId} references a non-existent store.";
        }

        return $store;
    }

    /** Step 5 — StoreContextRule role default (Plan §10 Step 5) */
    private function resolveFromRoleRule(User $user): ?Store
    {
        // Use the first Spatie role name as the primary role
        $primaryRole = $user->roles->first()?->name;

        if (! $primaryRole) {
            $this->resolutionTrace[] = 'Step 5 (role rule): user has no Spatie role.';
            return null;
        }

        $store = StoreContextRule::storeForRole($primaryRole);

        if ($store) {
            $this->resolutionTrace[] = "Step 5 (role rule): resolved to [{$store->store_name}] via role_default rule for role '{$primaryRole}'.";
        } else {
            $this->resolutionTrace[] = "Step 5 (role rule): no role_default rule configured for role '{$primaryRole}'.";
        }

        return $store;
    }

    /**
     * Returns all stores that a user legitimately could work from — used to
     * populate the "Change store" dropdown in workbench UIs.
     *
     * Collects (deduped, active only):
     *   A) The ward store for the user's current active shift (if any)
     *   B) The ROLE_DEPARTMENT store for the user's department (if any)
     *   C) The store configured via StoreContextRule::storeForRole() (if any)
     *   D) The store configured via StoreContextRule::storeForDepartment() (if any)
     *   E) DB-driven type_bucket rules (Option B) — expand set by role+type
     *   F) Legacy $typeFilter parameter (workbench controllers pass 'pharmacy'/'ward')
     *
     * Option A shortcut: if the user has 'stores.candidate-all' permission the
     * entire rule chain is bypassed and every active store is returned (still
     * scoped to $typeFilter so workbench UIs remain type-specific).
     *
     * @param  User  $user
     * @param  string|null  $typeFilter  'pharmacy' | 'ward' | null (null = no extra filter)
     * @return \Illuminate\Database\Eloquent\Collection<Store>
     */
    public function candidateStores(User $user, ?string $typeFilter = null): \Illuminate\Database\Eloquent\Collection
    {
        // ── Option A: permission bypass ───────────────────────────────────────
        // 'stores.candidate-all' → return all active stores, filtered by type
        // if the calling workbench requests a type scope.
        if ($user->hasPermissionTo('stores.candidate-all')) {
            $q = Store::active()->orderBy('store_name');
            if ($typeFilter === 'pharmacy') {
                $q->whereIn('distribution_role', [Store::ROLE_PHARMACY_HUB, Store::ROLE_PHARMACY_SATELLITE]);
            } elseif ($typeFilter === 'ward') {
                $q->where('distribution_role', Store::ROLE_WARD);
            }
            return $q->get(['id', 'store_name', 'distribution_role', 'ward_id', 'department_id']);
        }

        $ids = collect();

        // A) Shift ward store
        $activeShift = NursingShift::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('started_at')
            ->first();

        if ($activeShift?->ward_id) {
            $wardStore = Store::where('ward_id', $activeShift->ward_id)->active()->first();
            if ($wardStore) $ids->push($wardStore->id);
        }

        // B) Department store (ROLE_DEPARTMENT)
        $departmentId = $user->staff_profile?->department_id;
        if ($departmentId) {
            $deptStore = Store::where('department_id', $departmentId)
                ->where('distribution_role', Store::ROLE_DEPARTMENT)
                ->active()
                ->first();
            if ($deptStore) $ids->push($deptStore->id);
        }

        // C) StoreContextRule::storeForRole
        $primaryRole = $user->roles->first()?->name;
        if ($primaryRole) {
            $ruleStore = StoreContextRule::storeForRole($primaryRole);
            if ($ruleStore) $ids->push($ruleStore->id);
        }

        // D) StoreContextRule::storeForDepartment
        if ($departmentId) {
            $ruleDeptStore = StoreContextRule::storeForDepartment($departmentId);
            if ($ruleDeptStore) $ids->push($ruleDeptStore->id);
        }

        // E) Option B: DB-driven type_bucket rules — each role can have rules
        //    that expand the candidate set to all stores of a given type.
        $allGranted = false;
        foreach ($user->roles->pluck('name') as $roleName) {
            if ($allGranted) break;
            foreach (StoreContextRule::typeBucketRulesForRole($roleName) as $br) {
                if ($br->type_filter === 'all') {
                    $ids = $ids->merge(Store::active()->pluck('id'));
                    $allGranted = true;
                    break;
                } elseif ($br->type_filter === 'pharmacy') {
                    $ids = $ids->merge(
                        Store::active()->whereIn('distribution_role', [Store::ROLE_PHARMACY_HUB, Store::ROLE_PHARMACY_SATELLITE])->pluck('id')
                    );
                } elseif ($br->type_filter === 'ward') {
                    $ids = $ids->merge(Store::active()->where('distribution_role', Store::ROLE_WARD)->pluck('id'));
                } elseif ($br->type_filter === 'department') {
                    $ids = $ids->merge(Store::active()->where('distribution_role', Store::ROLE_DEPARTMENT)->pluck('id'));
                }
            }
        }

        // F) Legacy $typeFilter parameter — workbench controllers pass 'pharmacy' or 'ward'
        //    to supplement the candidate set with all stores of that type bucket.
        if ($typeFilter && ! $allGranted) {
            $legacyQ = Store::active();
            if ($typeFilter === 'pharmacy') {
                $legacyQ->whereIn('distribution_role', [Store::ROLE_PHARMACY_HUB, Store::ROLE_PHARMACY_SATELLITE]);
            } elseif ($typeFilter === 'ward') {
                $legacyQ->where('distribution_role', Store::ROLE_WARD);
            } else {
                $legacyQ->whereKey([]); // unknown typeFilter — no-op
            }
            $ids = $ids->merge($legacyQ->pluck('id'));
        }

        // Return unique active stores ordered by name
        return Store::active()
            ->whereIn('id', $ids->unique()->values())
            ->orderBy('store_name')
            ->get(['id', 'store_name', 'distribution_role', 'ward_id', 'department_id']);
    }
}
