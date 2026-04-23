<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class Store extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    // Plan §4 — distribution_role values:
    //   central | pharmacy_hub | pharmacy_satellite | department | ward | other
    public const ROLE_CENTRAL               = 'central';
    public const ROLE_PHARMACY_HUB          = 'pharmacy_hub';
    public const ROLE_PHARMACY_SATELLITE    = 'pharmacy_satellite';
    public const ROLE_DEPARTMENT            = 'department';
    public const ROLE_WARD                  = 'ward';
    public const ROLE_OTHER                 = 'other';

    public const DISTRIBUTION_ROLES = [
        self::ROLE_CENTRAL,
        self::ROLE_PHARMACY_HUB,
        self::ROLE_PHARMACY_SATELLITE,
        self::ROLE_DEPARTMENT,
        self::ROLE_WARD,
        self::ROLE_OTHER,
    ];

    /** Human-readable labels keyed by distribution_role value (Plan §4, used in governance views) */
    public const ROLE_LABELS = [
        self::ROLE_CENTRAL            => 'Central Store',
        self::ROLE_PHARMACY_HUB       => 'Pharmacy Hub',
        self::ROLE_PHARMACY_SATELLITE => 'Pharmacy Satellite',
        self::ROLE_DEPARTMENT         => 'Department Store',
        self::ROLE_WARD               => 'Ward Store',
        self::ROLE_OTHER              => 'Other',
    ];

    // Roles that are allowed to dispense directly to patients (Plan §7.5.1 Gate check)
    public const DISPENSE_ROLES = [
        self::ROLE_PHARMACY_HUB,
        self::ROLE_PHARMACY_SATELLITE,
    ];

    protected $fillable = [
        'store_name',
        'code',
        'description',
        'location',
        'store_type',
        'is_default',
        'is_immutable',
        'manager_id',
        'status',
        // Governance columns added by migration 2026_04_21_100001 (Plan §4)
        'distribution_role',
        'department_id',
        'ward_id',
        'parent_store_id',
        'allows_direct_patient_dispense',
        'requires_shift_context',
    ];

    protected $casts = [
        'is_default'                      => 'boolean',
        'is_immutable'                    => 'boolean',
        'status'                          => 'boolean',
        'allows_direct_patient_dispense'  => 'boolean',
        'requires_shift_context'          => 'boolean',
    ];

    // ===== EXISTING RELATIONSHIPS =====

    /**
     * Get store stocks (legacy)
     */
    public function stock() {
        return $this->hasMany(StoreStock::class,'store_id','id');
    }

    // ===== GOVERNANCE RELATIONSHIPS (Plan §4, §5, §10) =====

    /**
     * Ward this store is linked to — used by StoreContextResolver::resolveFromShift()
     * Plan §10 step 2: NursingShift.ward_id → stores.ward_id
     */
    public function ward()
    {
        return $this->belongsTo(\App\Models\Ward::class, 'ward_id');
    }

    /**
     * Department this store belongs to — used by StoreContextResolver::resolveFromUser()
     * Plan §10 step 3: User.department_id → stores.department_id
     */
    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class, 'department_id');
    }

    /**
     * Parent hub store — pharmacy satellite's replenishment source
     * Plan §3.1: pharmacy_satellite.parent_store_id → pharmacy_hub
     */
    public function parentStore()
    {
        return $this->belongsTo(Store::class, 'parent_store_id');
    }

    /**
     * Child satellite stores of this hub
     * Plan §6.3 Tab 3: Satellite Replenishment list
     */
    public function childStores()
    {
        return $this->hasMany(Store::class, 'parent_store_id');
    }

    /**
     * Lane policies where this store's role is the source
     * Plan §5.3: stored in store_lane_policies; admin UI in Plan §9.1 Section B
     */
    public function outgoingLanePolicies()
    {
        return \App\Models\StoreLanePolicy::where('source_role', $this->distribution_role);
    }

    // ===== NEW INVENTORY MANAGEMENT RELATIONSHIPS =====

    /**
     * Get the store manager
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Get all stock batches in this store
     */
    public function stockBatches()
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * Get active stock batches with available stock
     */
    public function availableBatches()
    {
        return $this->hasMany(StockBatch::class)
            ->active()
            ->hasStock()
            ->fifoOrder();
    }

    /**
     * Get purchase orders targeting this store
     */
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'target_store_id');
    }

    /**
     * Get requisitions requesting items FROM this store
     */
    public function outgoingRequisitions()
    {
        return $this->hasMany(StoreRequisition::class, 'from_store_id');
    }

    /**
     * Get requisitions requesting items TO this store
     */
    public function incomingRequisitions()
    {
        return $this->hasMany(StoreRequisition::class, 'to_store_id');
    }

    /**
     * Get expenses for this store
     */
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    // ===== SCOPES =====

    /**
     * Scope for active stores
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope for pharmacy stores (legacy — uses store_type)
     */
    public function scopePharmacy($query)
    {
        return $query->where('store_type', 'pharmacy');
    }

    /**
     * Scope for warehouse stores (legacy — uses store_type)
     */
    public function scopeWarehouse($query)
    {
        return $query->where('store_type', 'warehouse');
    }

    /**
     * Scope by distribution_role — used throughout Plan §6 workbench rendering
     * and by StoreContextResolver (Plan §10)
     */
    public function scopeRole($query, string $role)
    {
        return $query->where('distribution_role', $role);
    }

    /**
     * Scope for stores that can dispense to patients
     * Plan §7.5.1 — allows_direct_patient_dispense gate check
     */
    public function scopeDispensary($query)
    {
        return $query->where('allows_direct_patient_dispense', true);
    }

    /**
     * Scope for ward stores linked to a specific ward
     * Plan §10 step 2 — resolveFromShift()
     */
    public function scopeForWard($query, int $wardId)
    {
        return $query->where('ward_id', $wardId)
                     ->where('distribution_role', self::ROLE_WARD);
    }

    /**
     * Scope for department stores linked to a specific department
     * Plan §10 step 3 — resolveFromUser()
     */
    public function scopeForDepartment($query, int $deptId)
    {
        return $query->where('department_id', $deptId)
                     ->where('distribution_role', self::ROLE_DEPARTMENT);
    }

    /**
     * Scope stores accessible by the given user under governance policies.
     *
     * Rules:
     *  - ADMIN / SUPERADMIN / super-admin → all stores (no restriction)
     *  - PHARMACIST                       → pharmacy hub + satellite stores only
     *  - All others (STORE, NURSE, etc.)  → stores they manage (manager_id)
     *                                       + the store resolved by StoreContextResolver
     *
     * Usage: Store::active()->forUser(auth()->user())->orderBy('store_name')->get()
     */
    public function scopeForUser($query, \App\Models\User $user)
    {
        // Admins see all stores
        if ($user->hasAnyRole(['ADMIN', 'SUPERADMIN', 'super-admin'])) {
            return $query;
        }

        // Pharmacists see pharmacy-role stores only
        if ($user->hasRole('PHARMACIST')) {
            return $query->where(function ($q) {
                $q->whereIn('distribution_role', [
                    self::ROLE_PHARMACY_HUB,
                    self::ROLE_PHARMACY_SATELLITE,
                ])->orWhere('store_type', 'pharmacy');
            });
        }

        // All other roles: managed stores + context-resolved store
        $ids = collect();

        // STORE role also gets all central stores
        if ($user->hasRole('STORE')) {
            $centralIds = \Illuminate\Support\Facades\DB::table('stores')
                ->where('distribution_role', self::ROLE_CENTRAL)
                ->where('status', true)
                ->pluck('id');
            $ids = $ids->merge($centralIds);
        }

        // Stores this user directly manages
        $managedIds = \Illuminate\Support\Facades\DB::table('stores')
            ->where('manager_id', $user->id)
            ->pluck('id');
        $ids = $ids->merge($managedIds);

        // Store resolved by the 5-step context chain
        $resolved = app(\App\Services\StoreContextResolver::class)->resolve($user);
        if ($resolved) {
            $ids->push($resolved->id);
        }

        if ($ids->isEmpty()) {
            // No accessible stores — return empty result set
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('id', $ids->unique()->values()->all());
    }

    // ===== HELPERS =====

    /**
     * Get the default pharmacy store (legacy helper — preserved)
     */
    public static function getDefaultPharmacy(): ?self
    {
        return self::active()->where('store_type', 'pharmacy')
            ->where('is_default', true)
            ->first()
            ?? self::active()->where('store_type', 'pharmacy')->first();
    }

    /**
     * Get the central/warehouse store (legacy helper — preserved)
     */
    public static function getCentralStore(): ?self
    {
        return self::where('store_type', 'warehouse')
            ->first();
    }

    /**
     * Governance-aware central store lookup using distribution_role
     * Plan §6.2: used by StoreContextResolver role-default fallback
     */
    public static function getCentralByRole(): ?self
    {
        return self::where('distribution_role', self::ROLE_CENTRAL)->first()
            ?? self::getCentralStore();
    }

    /**
     * Human-readable label for distribution_role
     * Used in workbench role badge (Plan §6.1 shared header)
     */
    public function distributionRoleLabel(): string
    {
        return match ($this->distribution_role) {
            self::ROLE_CENTRAL              => 'Central Store',
            self::ROLE_PHARMACY_HUB         => 'Pharmacy Hub',
            self::ROLE_PHARMACY_SATELLITE   => 'Pharmacy Satellite',
            self::ROLE_DEPARTMENT           => 'Department Store',
            self::ROLE_WARD                 => 'Ward Store',
            default                         => 'Store',
        };
    }

    /**
     * Whether this store can dispense drugs directly to a patient
     * Plan §7.5.1 — checked before dispenseMedication() L933
     */
    public function canDispenseToPatient(): bool
    {
        return $this->allows_direct_patient_dispense
            && in_array($this->distribution_role, self::DISPENSE_ROLES);
    }

    /**
     * Get available quantity for a product in this store
     */
    public function getProductQty(int $productId): int
    {
        return $this->stockBatches()
            ->where('product_id', $productId)
            ->active()
            ->sum('current_qty');
    }

    /**
     * Get batches for a product in this store (FIFO order)
     */
    public function getProductBatches(int $productId)
    {
        return $this->stockBatches()
            ->where('product_id', $productId)
            ->active()
            ->hasStock()
            ->fifoOrder()
            ->get();
    }

    /**
     * Get products with low stock in this store
     */
    public function getLowStockProducts()
    {
        return StoreStock::where('store_id', $this->id)
            ->where('is_active', true)
            ->whereRaw('current_quantity <= reorder_level')
            ->with('product')
            ->get();
    }

    /**
     * Get expiring batches in this store
     */
    public function getExpiringBatches(int $days = 30)
    {
        return $this->stockBatches()
            ->active()
            ->hasStock()
            ->expiringSoon($days)
            ->with('product')
            ->get();
    }
}
