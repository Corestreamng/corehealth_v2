<?php

namespace App\Observers;

use App\Models\Department;
use App\Models\Store;
use Illuminate\Support\Str;

/**
 * DepartmentObserver — auto-creates a department stock store whenever a new
 * Department is created.
 *
 * Purpose: any staff member linked to a department can have their store
 * context automatically resolved (StoreContextResolver Step 3) without manual
 * configuration on the context-rules page.
 *
 * Store properties set:
 *   - store_name        → "{$dept->name} Store"
 *   - code              → derived from department code
 *   - store_type        → 'other'  (neutral; admin can change to 'pharmacy', etc.)
 *   - distribution_role → Store::ROLE_DEPARTMENT
 *   - department_id     → $dept->id
 *   - status            → 1 (active)
 *   - is_default        → false
 *   - is_immutable      → false
 *
 * NOTE: Only creates the store if no ROLE_DEPARTMENT store already exists for
 * this department (idempotent — safe to trigger multiple times).
 */
class DepartmentObserver
{
    public function created(Department $department): void
    {
        // Skip if a department store already exists (idempotent)
        $exists = Store::where('department_id', $department->id)
            ->where('distribution_role', Store::ROLE_DEPARTMENT)
            ->exists();

        if ($exists) {
            return;
        }

        $basePrefix = $department->code
            ? Str::upper(substr(preg_replace('/[^A-Za-z0-9]/', '', $department->code), 0, 7))
            : 'D' . substr((string) $department->id, 0, 6);
        $code = Str::substr($basePrefix . '_DS', 0, 10);

        if (Store::where('code', $code)->exists()) {
            $code = Str::substr('DS_' . $department->id . '_' . Str::random(2), 0, 10);
        }

        Store::create([
            'store_name' => $department->name . ' Store',
            'code' => $code,
            'description' => "Auto-created department store for {$department->name}",
            'location' => $department->name,
            'store_type' => 'other',
            'distribution_role' => Store::ROLE_DEPARTMENT,
            'department_id' => $department->id,
            'requires_shift_context' => false,
            'allows_direct_patient_dispense' => false,
            'status' => 1,
            'is_default' => false,
            'is_immutable' => false,
        ]);
    }
}
