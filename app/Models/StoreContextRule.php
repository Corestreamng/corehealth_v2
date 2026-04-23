<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * StoreContextRule — configuration rows for StoreContextResolver's resolution chain.
 *
 * Plan Reference: docs/STORE_GOVERNANCE_AND_CONTEXTUAL_WORKBENCH_PLAN.md
 *   § 9.3  (Admin Module: Context Resolution Rules)
 *   § 10   (Context Resolution Logic — steps 4 & 5 use this table)
 *   § 12 Phase B (StoreContextResolver::resolve() service)
 *   § R18  (Rule R18 — every config change audited)
 *
 * Three rule_type values (matching admin UI sections in Plan §9.3):
 *   'role_default'        — §9.3 Section A: Spatie role → default store
 *   'department_override' — §9.3 Section B: department → store override
 *   'fallback_behavior'   — §9.3 Section E: what to do when all 5 steps return null
 *                           fallback_action: 'block' | 'allow_manual' | 'use_role_default'
 *
 * @property int         $id
 * @property string      $rule_type
 * @property string|null $user_role          (for role_default rows)
 * @property int|null    $department_id      (for department_override rows)
 * @property int|null    $store_id
 * @property string|null $fallback_action    (for fallback_behavior row)
 * @property string|null $notes
 * @property int|null    $updated_by
 */
class StoreContextRule extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'rule_type',
        'user_role',
        'department_id',
        'store_id',
        'type_filter',
        'fallback_action',
        'notes',
        'updated_by',
    ];

    // ===== RELATIONSHIPS =====

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class, 'department_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ===== STATIC HELPERS =====

    /**
     * Resolve the default store for a Spatie role name.
     * Used by StoreContextResolver::resolve() step 5 (Plan §10).
     *
     * @param string $roleName  Spatie role name (e.g. 'pharmacist', 'nurse')
     */
    public static function storeForRole(string $roleName): ?Store
    {
        $rule = self::where('rule_type', 'role_default')
                    ->where('user_role', $roleName)
                    ->whereHas('store', fn ($q) => $q->active())
                    ->with('store')
                    ->first();

        return $rule?->store;
    }

    /**
     * Resolve the store override for a department.
     * Used by StoreContextResolver::resolve() step 3 fallback (Plan §10).
     *
     * @param int $departmentId
     */
    public static function storeForDepartment(int $departmentId): ?Store
    {
        $rule = self::where('rule_type', 'department_override')
                    ->where('department_id', $departmentId)
                    ->whereHas('store', fn ($q) => $q->active())
                    ->with('store')
                    ->first();

        return $rule?->store;
    }

    /**
     * Return all type_bucket rules for a given Spatie role name.
     * Used by StoreContextResolver::candidateStores() step E (Option B).
     *
     * @param string $roleName
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function typeBucketRulesForRole(string $roleName): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('rule_type', 'type_bucket')
                   ->where('user_role', $roleName)
                   ->get();
    }

    /**
     * Get the configured fallback action when context cannot be resolved.
     * Plan §9.3 Section E, §10 (if unresolved block).
     * Returns 'block' | 'allow_manual' | 'use_role_default'
     */
    public static function fallbackAction(): string
    {
        return self::where('rule_type', 'fallback_behavior')
                   ->value('fallback_action')
            ?? 'block'; // Default: block all stock actions until context is resolved
    }
}
