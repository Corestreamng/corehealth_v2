<?php

namespace App\Http\Controllers\EncounterWorkbench;

use App\Http\Controllers\OpsAudit\OpsAuditBaseController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * EncounterWorkbenchBaseController
 *
 * Abstract base for all Encounter Workbench tab controllers.
 * Extends OpsAuditBaseController to inherit:
 *   - applyDateFilter()
 *   - applyShiftFilter()
 *   - buildDataTableResponse()
 *   - renderPatient()
 *   - renderHmo()
 *   - renderPaymentInfo()
 *   - renderAuditAction()
 */
abstract class EncounterWorkbenchBaseController extends OpsAuditBaseController
{
    // ─── Role Capability Helpers ──────────────────────────────────────────────

    /**
     * Roles that can view deep analytics (clinic, doctor productivity, revenue).
     */
    protected static array $adminRoles = ['SUPERADMIN', 'ADMIN', 'super-admin'];

    /**
     * Roles that can view financial / revenue analytics.
     */
    protected static array $financeRoles = ['SUPERADMIN', 'ADMIN', 'super-admin', 'ACCOUNTS', 'BILLER'];

    /**
     * Roles that can access the workbench at all.
     * Doctors see own-scoped data; reception/nurse see basic list.
     */
    protected static array $workbenchRoles = [
        'SUPERADMIN', 'ADMIN', 'super-admin',
        'RECEPTIONIST', 'NURSE', 'DOCTOR',
        'ACCOUNTS', 'BILLER', 'HMO Executive',
        'MATERNITY', 'SURGERY', 'LAB SCIENTIST', 'RADIOLOGIST',
    ];

    protected function userIsAdmin(): bool
    {
        return Auth::user()?->hasAnyRole(static::$adminRoles) ?? false;
    }

    protected function userIsFinance(): bool
    {
        return Auth::user()?->hasAnyRole(static::$financeRoles) ?? false;
    }

    protected function userIsDoctor(): bool
    {
        return Auth::user()?->hasRole('DOCTOR') ?? false;
    }

    /**
     * Can the current user see deep analytics (clinic analytics, doctor productivity)?
     */
    protected function canViewAnalytics(): bool
    {
        return $this->userIsAdmin() || $this->userIsFinance();
    }

    /**
     * Can the current user see the revenue tab?
     * True for admin/finance roles OR doctors (own-scoped).
     */
    protected function canViewRevenue(): bool
    {
        return $this->userIsFinance() || $this->userIsDoctor();
    }

    // ─── Shared Filters ───────────────────────────────────────────────────────

    /**
     * Apply all encounter-specific filters to the query.
     * Builds on top of applyDateFilter/applyShiftFilter from base.
     */
    protected function applyEncounterFilters(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
    {
        $this->applyDateFilter($query, $request, 'encounters.created_at');
        $this->applyShiftFilter($query, $request);

        // Clinic filter (via queue.clinic_id)
        if ($request->filled('clinic_id')) {
            $clinicId = $request->clinic_id;
            $query->whereHas('queue', fn ($q) => $q->where('clinic_id', $clinicId));
        }

        // Doctor filter
        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }

        // HMO filter
        if ($request->filled('hmo_id')) {
            $hmoId = $request->hmo_id;
            $query->whereHas('patient', fn ($q) => $q->where('hmo_id', $hmoId));
        }

        // Status filter
        if ($request->filled('status')) {
            if ($request->status === 'completed') {
                $query->where('completed', true);
            } elseif ($request->status === 'active') {
                $query->where('completed', false);
            }
        }

        // Tag filters — has lab, imaging, prescription, referral, admission
        if ($request->boolean('has_lab')) {
            $query->whereHas('labRequests');
        }
        if ($request->boolean('has_imaging')) {
            $query->whereHas('imagingRequests');
        }
        if ($request->boolean('has_prescription')) {
            $query->whereHas('productRequests');
        }
        if ($request->boolean('has_referral')) {
            $query->whereHas('referrals');
        }
        if ($request->boolean('has_admission')) {
            $query->whereHas('admissionRequests');
        }

        // New patient filter — encounters where patient has only 1 encounter total
        if ($request->boolean('is_new_patient')) {
            $query->whereHas('patient', function ($q) {
                $q->whereHas('encounters', null, '=', 1);
            });
        }
    }

    /**
     * Scope doctor queries to own encounters only (if not admin/finance).
     */
    protected function applyRoleScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        if (!$this->userIsAdmin() && !$this->userIsFinance() && $this->userIsDoctor()) {
            $query->where('doctor_id', Auth::id());
        }
    }

    // ─── KPI Helper ───────────────────────────────────────────────────────────

    protected function kpiCard(string $label, $value, string $color, string $icon, ?string $sub = null): array
    {
        return compact('label', 'value', 'color', 'icon', 'sub');
    }

    // ─── Render Helpers ───────────────────────────────────────────────────────

    protected function renderEncounterTags(object $encounter): string
    {
        $tags = '';

        if ($encounter->relationLoaded('labRequests') && $encounter->labRequests->isNotEmpty()) {
            $tags .= '<span class="badge badge-sm ewb-tag-lab" title="Lab Requests"><i class="mdi mdi-flask-outline"></i> Lab</span> ';
        }
        if ($encounter->relationLoaded('imagingRequests') && $encounter->imagingRequests->isNotEmpty()) {
            $tags .= '<span class="badge badge-sm ewb-tag-imaging" title="Imaging Requests"><i class="mdi mdi-radiobox-marked"></i> Imaging</span> ';
        }
        if ($encounter->relationLoaded('productRequests') && $encounter->productRequests->isNotEmpty()) {
            $tags .= '<span class="badge badge-sm ewb-tag-rx" title="Prescriptions"><i class="mdi mdi-pill"></i> Rx</span> ';
        }
        if ($encounter->relationLoaded('referrals') && $encounter->referrals->isNotEmpty()) {
            $tags .= '<span class="badge badge-sm ewb-tag-referral" title="Referral Issued"><i class="mdi mdi-share-variant"></i> Referral</span> ';
        }
        if ($encounter->relationLoaded('admissionRequests') && $encounter->admissionRequests->isNotEmpty()) {
            $tags .= '<span class="badge badge-sm ewb-tag-admission" title="Admission Request"><i class="mdi mdi-bed"></i> Admitted</span> ';
        }
        if ($encounter->relationLoaded('procedures') && $encounter->procedures->isNotEmpty()) {
            $tags .= '<span class="badge badge-sm ewb-tag-procedure" title="Procedure Performed"><i class="mdi mdi-medical-bag"></i> Procedure</span> ';
        }

        return $tags ?: '<span class="text-muted small">—</span>';
    }

    protected function renderDuration(?Carbon $start, ?Carbon $end): string
    {
        if (!$start || !$end) {
            return '—';
        }

        $mins = $start->diffInMinutes($end);

        if ($mins < 60) {
            return "{$mins}m";
        }

        $h = intdiv($mins, 60);
        $m = $mins % 60;

        return "{$h}h {$m}m";
    }

    protected function renderStatusBadge(bool $completed): string
    {
        return $completed
            ? '<span class="badge bg-success text-white">Completed</span>'
            : '<span class="badge bg-teal text-white">Active</span>';
    }
}
