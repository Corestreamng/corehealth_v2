<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * StoreLanePolicy — governs which store distribution_role may supply which other role.
 *
 * Plan Reference: docs/STORE_GOVERNANCE_AND_CONTEXTUAL_WORKBENCH_PLAN.md
 *   § 4   (Data Model — store_lane_policies table)
 *   § 5   (Policy Lanes — default matrix)
 *   § 5.2 (Enforcement points: StoreRequisitionController::store() L180, ::approve() L242)
 *   § 5.3 (Lane policy storage + caching)
 *   § 9.1 (Admin UI — Lane Policy Matrix, Section B)
 *   § 11  (Permissions — requisition-lane-allowed Gate)
 *   § 17  (Rule R17 — every config change is audited)
 *
 * Rows in this table are read by StoreLanePolicyGate::check() which is called
 * before RequisitionService::create() — the service itself is NOT modified.
 *
 * Default rows are seeded by StoreGovernanceSeeder (Plan §12 Phase A step 2).
 *
 * @property int    $id
 * @property string $source_role           e.g. 'central', 'pharmacy_hub'
 * @property string $destination_role      e.g. 'ward', 'pharmacy_satellite'
 * @property bool   $allowed
 * @property string $requires_approval_level  'none' | 'manager' | 'admin'
 * @property string|null $notes
 * @property int|null    $updated_by
 */
class StoreLanePolicy extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'source_role',
        'destination_role',
        'allowed',
        'requires_approval_level',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'allowed' => 'boolean',
    ];

    // ===== RELATIONSHIPS =====

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ===== STATIC HELPERS =====

    /**
     * Check if a source→destination lane is permitted.
     * Called by Gate 'requisition-lane-allowed' (Plan §5.2).
     *
     * Returns the policy row (caller checks ->allowed) or a synthetic "deny" object
     * when no row exists (default-deny behaviour — Plan §5.1).
     *
     * Uses a simple in-request cache to avoid N+1 on bulk requisition validation.
     */
    public static function check(string $sourceRole, string $destinationRole): self
    {
        static $cache = [];
        $key = "{$sourceRole}→{$destinationRole}";

        if (! isset($cache[$key])) {
            $cache[$key] = self::where('source_role', $sourceRole)
                               ->where('destination_role', $destinationRole)
                               ->first()
                        ?? new self([
                               'source_role'              => $sourceRole,
                               'destination_role'         => $destinationRole,
                               'allowed'                  => false,
                               'requires_approval_level'  => 'none',
                               'notes'                    => 'No policy row — denied by default.',
                           ]);
        }

        return $cache[$key];
    }

    /**
     * Human-readable denial reason for the Gate 403 response (Plan §7.1 UI feedback).
     */
    public function denyReason(): string
    {
        $src  = ucwords(str_replace('_', ' ', $this->source_role));
        $dest = ucwords(str_replace('_', ' ', $this->destination_role));

        if (! $this->exists) {
            return "Route blocked: {$src} → {$dest} has no policy configured. Contact your administrator.";
        }

        return "Route blocked: {$src} → {$dest} is not allowed by current lane policy. "
             . ($this->notes ? "({$this->notes})" : 'Contact your administrator to enable this route.');
    }

    /**
     * Default lane matrix rows for StoreGovernanceSeeder (Plan §5.1, §12 Phase A)
     * Keys: [source_role, destination_role, allowed, requires_approval_level, notes]
     */
    public static function defaultMatrix(): array
    {
        return [
            ['central',         'pharmacy_hub',         true,  'none',    'Central → Pharmacy Hub (standard replenishment)'],
            ['central',         'pharmacy_satellite',   true,  'none',    'Central → Pharmacy Satellite (direct replenishment)'],
            ['central',         'department',           true,  'none',    'Central → Department Store (standard replenishment)'],
            ['central',         'ward',                 true,  'none',    'Central → Ward Store (standard replenishment, no approval required)'],
            ['pharmacy_hub',    'pharmacy_satellite',   true,  'none',    'Hub → Satellite (standard satellite replenishment)'],
            ['pharmacy_hub',    'ward',                 false, 'none',    'Hub → Ward (disabled by default — enable if needed)'],
            // All other pairs are denied by default (no row = denied)
        ];
    }
}
