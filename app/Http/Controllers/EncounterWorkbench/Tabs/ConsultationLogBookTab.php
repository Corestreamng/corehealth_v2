<?php

namespace App\Http\Controllers\EncounterWorkbench\Tabs;

use App\Http\Controllers\EncounterWorkbench\EncounterWorkbenchBaseController;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Tab: Consultation Log Book
 *
 * Executive high-performance log book:
 * "This doctor saw this number of patients for this clinic, with drill-down
 * for the patients and how much each patient paid for the consult and how much is claims."
 *
 * CRITICAL RULE: Multiple notes written on a single consultation payment/queue entry
 * are shown, but their funds are counted ONLY ONCE and indicated intuitively.
 *
 * Optimized with two-tier database-level aggregation to effortlessly handle hundreds
 * of thousands of clinical encounters in sub-second time.
 */
class ConsultationLogBookTab extends EncounterWorkbenchBaseController
{
    /**
     * Get aggregated Doctor x Clinic consultation summary rows.
     */
    public function getData(Request $request): JsonResponse
    {
        $start = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        $isDoctor = $this->userIsDoctor();
        $canViewAll = $this->canViewAnalytics() || $this->userIsFinance();

        // ── Tier 1: Subquery grouped by unique billing/queue visit ─────────
        $sub = DB::table('encounters as e')
            ->leftJoin('doctor_queues as dq', 'e.queue_id', '=', 'dq.id')
            ->leftJoin('product_or_service_requests as posr', 'posr.id', '=', DB::raw('COALESCE(e.service_request_id, dq.request_entry_id)'))
            ->whereBetween('e.created_at', [$start, $end])
            ->whereNull('e.deleted_at');

        // Apply shift filter if present
        $this->applyShiftFilter($sub, $request, 'e.created_at');

        // Doctor self-scope
        if ($isDoctor && !$canViewAll) {
            $sub->where('e.doctor_id', Auth::id());
        } elseif ($request->filled('doctor_id')) {
            $sub->where('e.doctor_id', $request->doctor_id);
        }

        // Clinic filter
        if ($request->filled('clinic_id')) {
            $sub->where('dq.clinic_id', $request->clinic_id);
        }

        // HMO filter
        if ($request->filled('hmo_id')) {
            $sub->whereExists(function ($q) use ($request) {
                $q->select(DB::raw(1))
                    ->from('patients')
                    ->whereColumn('patients.id', 'e.patient_id')
                    ->where('patients.hmo_id', $request->hmo_id);
            });
        }

        $sub->selectRaw('
            COALESCE(e.doctor_id, 0) as doctor_id,
            COALESCE(dq.clinic_id, 0) as clinic_id,
            e.patient_id,
            COALESCE(posr.id, CONCAT("enc_", e.id)) as billing_ref,
            COALESCE(posr.payable_amount, 0) as payable_amount,
            COALESCE(posr.claims_amount, 0) as claims_amount,
            COUNT(e.id) as notes_count
        ')
        ->groupBy(
            'e.doctor_id',
            DB::raw('COALESCE(dq.clinic_id, 0)'),
            'e.patient_id',
            DB::raw('COALESCE(posr.id, CONCAT("enc_", e.id))'),
            'posr.payable_amount',
            'posr.claims_amount'
        );

        // ── Tier 2: Rollup by Doctor x Clinic ─────────────────────────────
        $summary = DB::query()->fromSub($sub, 'v')
            ->leftJoin('users as u', 'v.doctor_id', '=', 'u.id')
            ->leftJoin('clinics as cl', 'v.clinic_id', '=', 'cl.id')
            ->selectRaw('
                v.doctor_id,
                v.clinic_id,
                CONCAT(COALESCE(u.surname,""), " ", COALESCE(u.firstname,""), " ", COALESCE(u.othername,"")) as doctor_name,
                COALESCE(cl.name, "General Consultation") as clinic_name,
                COUNT(DISTINCT v.billing_ref) as visits_count,
                SUM(v.notes_count) as notes_count,
                COUNT(DISTINCT v.patient_id) as unique_patients,
                SUM(v.payable_amount) as total_paid,
                SUM(v.claims_amount) as total_claims,
                SUM(v.payable_amount + v.claims_amount) as total_funds
            ')
            ->groupBy('v.doctor_id', 'v.clinic_id', 'u.surname', 'u.firstname', 'u.othername', 'cl.name')
            ->orderByDesc('total_funds')
            ->get();

        $tableData = [];
        $grandVisits = 0;
        $grandNotes = 0;
        $grandPaid = 0.0;
        $grandClaims = 0.0;
        $doctorsSeen = collect();

        foreach ($summary as $row) {
            $docId = (int) $row->doctor_id;
            $clinicId = (int) $row->clinic_id;
            $docName = trim($row->doctor_name) ?: 'Unassigned Clinician';
            $clinicName = $row->clinic_name;

            $visits = (int) $row->visits_count;
            $notes = (int) $row->notes_count;
            $patients = (int) $row->unique_patients;
            $paid = (float) $row->total_paid;
            $claims = (float) $row->total_claims;
            $totalFunds = (float) $row->total_funds;

            $grandVisits += $visits;
            $grandNotes += $notes;
            $grandPaid += $paid;
            $grandClaims += $claims;
            if ($docId > 0) {
                $doctorsSeen->push($docId);
            }

            $initial = mb_strtoupper(mb_substr($docName, 0, 1));
            $avatarHtml = '<div class="ewb-doctor-avatar d-inline-flex align-items-center justify-content-center me-2">' . ($initial ?: 'D') . '</div>';

            $notesBadge = '';
            if ($notes > $visits) {
                $diff = $notes - $visits;
                $notesBadge = '<span class="badge bg-light text-muted border ms-1" title="' . $diff . ' additional follow-up/review notes on same visit">' . $notes . ' notes</span>';
            }

            $tableData[] = [
                'doctor_id' => $docId,
                'clinic_id' => $clinicId,
                'clinician' => '<div class="d-flex align-items-center">' . $avatarHtml . '<div><strong>' . e($docName) . '</strong><br><small class="text-muted"><i class="mdi mdi-hospital-building me-1"></i>' . e($clinicName) . '</small></div></div>',
                'activity' => '<div><strong class="text-dark">' . number_format($visits) . ' Consultations</strong><br><small class="text-muted">' . number_format($patients) . ' unique patients' . ($notes > $visits ? ' · <span class="badge bg-light text-muted border">' . $notes . ' notes</span>' : '') . '</small></div>',
                'financials' => '<div class="text-end"><strong class="text-primary" style="font-size:0.95rem;">₦' . number_format($totalFunds, 2) . '</strong><br><small class="text-muted"><span class="text-success font-weight-bold">Paid: ₦' . number_format($paid, 2) . '</span> · <span class="text-info font-weight-bold">Claims: ₦' . number_format($claims, 2) . '</span></small></div>',
                'doctor_raw' => $docName,
                'clinic_raw' => $clinicName,
                'visits_num' => $visits,
                'total_num' => $totalFunds,
                'action' => '<button type="button" class="btn btn-xs btn-primary ewb-drill-logbook" '
                    . 'data-doctor-id="' . $docId . '" '
                    . 'data-clinic-id="' . $clinicId . '" '
                    . 'data-doctor-name="' . e($docName) . '" '
                    . 'data-clinic-name="' . e($clinicName) . '" '
                    . 'title="Drill down to view individual patient consultations and billing">'
                    . '<i class="mdi mdi-account-details"></i> View Patients</button>',
            ];
        }

        $kpis = [
            $this->kpiCard('Consultations', number_format($grandVisits), '#0d6efd', 'mdi-stethoscope', $grandNotes . ' clinical notes'),
            $this->kpiCard('Consult Paid', '₦' . number_format($grandPaid, 2), '#198754', 'mdi-cash-check', 'Patient Cash / Copay'),
            $this->kpiCard('HMO Claims', '₦' . number_format($grandClaims, 2), '#0dcaf0', 'mdi-shield-check', 'Insurance Claims'),
            $this->kpiCard('Total Consult Value', '₦' . number_format($grandPaid + $grandClaims, 2), '#6610f2', 'mdi-currency-ngn', 'Combined Revenue'),
            $this->kpiCard('Active Clinicians', number_format($doctorsSeen->unique()->count()), '#fd7e14', 'mdi-doctor', count($tableData) . ' clinic rotations'),
        ];

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => count($tableData),
            'recordsFiltered' => count($tableData),
            'data' => $tableData,
            'kpis' => $kpis,
            'period' => [
                'start' => $start->format('d M Y'),
                'end' => $end->format('d M Y'),
            ],
        ]);
    }

    /**
     * Drill-down: List every patient consultation for a specific doctor & clinic.
     * Uses SQL window function ROW_NUMBER() to identify multiple notes on the same visit,
     * attributing funds strictly once to the primary intake and intuitively badging follow-up notes.
     */
    public function getDrilldown(Request $request): JsonResponse
    {
        $doctorId = (int) $request->input('doctor_id');
        $clinicId = (int) $request->input('clinic_id');

        $isDoctor = $this->userIsDoctor();
        $canViewAll = $this->canViewAnalytics() || $this->userIsFinance();

        // Security: Scoped to logged-in doctor if not admin/finance
        if ($isDoctor && !$canViewAll && $doctorId !== Auth::id()) {
            abort(403, 'Unauthorized access to other clinician logbooks.');
        }

        $start = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        $query = DB::table('encounters as e')
            ->leftJoin('doctor_queues as dq', 'e.queue_id', '=', 'dq.id')
            ->leftJoin('product_or_service_requests as posr', 'posr.id', '=', DB::raw('COALESCE(e.service_request_id, dq.request_entry_id)'))
            ->leftJoin('clinics as cl', 'dq.clinic_id', '=', 'cl.id')
            ->leftJoin('patients as p', 'e.patient_id', '=', 'p.id')
            ->leftJoin('users as pu', 'p.user_id', '=', 'pu.id')
            ->leftJoin('hmos as h', 'p.hmo_id', '=', 'h.id')
            ->leftJoin('payments as pay', 'posr.payment_id', '=', 'pay.id')
            ->whereBetween('e.created_at', [$start, $end])
            ->whereNull('e.deleted_at')
            ->where('e.doctor_id', $doctorId);

        if ($clinicId > 0) {
            $query->where('dq.clinic_id', $clinicId);
        }

        $this->applyShiftFilter($query, $request, 'e.created_at');

        if ($request->filled('hmo_id')) {
            $query->where('p.hmo_id', $request->hmo_id);
        }

        $rows = $query->selectRaw('
            e.id as encounter_id,
            e.created_at as encounter_time,
            e.completed,
            p.id as patient_id,
            p.file_no,
            pu.surname as patient_surname,
            pu.firstname as patient_firstname,
            pu.othername as patient_othername,
            COALESCE(h.name, "Private / Self-pay") as hmo_name,
            cl.name as clinic_name,
            COALESCE(posr.id, CONCAT("enc_", e.id)) as billing_ref,
            posr.id as posr_id,
            COALESCE(posr.payable_amount, 0) as payable_amount,
            COALESCE(posr.claims_amount, 0) as claims_amount,
            posr.payment_id,
            pay.payment_method,
            pay.reference_no as payment_ref,
            ROW_NUMBER() OVER (PARTITION BY COALESCE(posr.id, CONCAT("enc_", e.id)) ORDER BY e.id ASC) as note_rank
        ')
        ->orderByDesc('e.created_at')
        ->get();

        $totalPaid = 0.0;
        $totalClaims = 0.0;
        $uniqueVisits = 0;
        $patientsSeen = collect();
        $patientRows = [];

        foreach ($rows as $row) {
            $isPrimaryNote = ((int) $row->note_rank === 1);
            $paidAmount = (float) $row->payable_amount;
            $claimsAmount = (float) $row->claims_amount;

            if ($isPrimaryNote) {
                $effectivePaid = $paidAmount;
                $effectiveClaims = $claimsAmount;
                $totalPaid += $effectivePaid;
                $totalClaims += $effectiveClaims;
                $uniqueVisits++;
            } else {
                // Secondary note on same visit/queue entry: Funds counted strictly once!
                $effectivePaid = 0.0;
                $effectiveClaims = 0.0;
            }

            if ($row->patient_id) {
                $patientsSeen->push($row->patient_id);
            }

            // Name with othername according to standard
            $patientName = trim(($row->patient_surname ?? '') . ' ' . ($row->patient_firstname ?? '') . ' ' . ($row->patient_othername ?? '')) ?: 'Unknown Patient';
            $fileNo = $row->file_no ? 'File: ' . e($row->file_no) : 'No File No';

            // Payment status badge
            if ($row->payment_id) {
                $payStatus = '<span class="badge bg-success text-white" title="Payment ref: ' . e($row->payment_ref ?? '') . '"><i class="mdi mdi-check-circle"></i> Paid (' . e($row->payment_method ?? 'CASH') . ')</span>';
            } elseif ($claimsAmount > 0) {
                $payStatus = '<span class="badge bg-info text-white"><i class="mdi mdi-shield"></i> HMO Claim</span>';
            } elseif ($paidAmount > 0) {
                $payStatus = '<span class="badge bg-warning text-dark"><i class="mdi mdi-clock-outline"></i> Pending Cashier</span>';
            } else {
                $payStatus = '<span class="badge bg-secondary text-white">Unbilled / Review</span>';
            }

            // Note attribution indicator
            if ($isPrimaryNote) {
                $noteBadge = '<span class="badge bg-primary text-white"><i class="mdi mdi-file-document-outline"></i> Primary Intake</span>';
                $fundsNote = '';
            } else {
                $noteBadge = '<span class="badge bg-warning text-dark" title="Additional clinical note on the same consultation entry"><i class="mdi mdi-link-variant"></i> Follow-up Note</span>';
                $fundsNote = '<br><small class="text-muted fst-italic">(Fund counted in visit #' . e($row->billing_ref) . ')</small>';
            }

            $actions = '<div class="d-flex gap-1">';
            $actions .= '<button type="button" class="btn btn-xs btn-outline-primary ewb-view-encounter" data-id="' . $row->encounter_id . '" data-url="' . route('encounter.workbench.details', $row->encounter_id) . '" title="View Encounter Notes"><i class="mdi mdi-eye"></i> View</button>';
            if ($row->patient_id) {
                $actions .= '<a href="' . route('patient.show', $row->patient_id) . '" target="_blank" class="btn btn-xs btn-outline-secondary" title="Open Patient Chart"><i class="mdi mdi-account-box-outline"></i> Chart</a>';
            }
            $actions .= '</div>';

            $patientRows[] = [
                'encounter_id' => $row->encounter_id,
                'patient_info' => '<div><span class="small text-muted"><i class="mdi mdi-clock-outline me-1"></i>' . Carbon::parse($row->encounter_time)->format('d M Y, h:i A') . '</span><br><strong>' . e($patientName) . '</strong> <small class="text-muted">(' . $fileNo . ')</small></div>',
                'scheme_note' => '<div><span class="badge bg-light text-dark border mb-1">' . e($row->hmo_name) . '</span><br>' . $noteBadge . '</div>',
                'financials' => '<div class="text-end"><strong class="text-primary">₦' . number_format($effectivePaid + $effectiveClaims, 2) . '</strong><br><small><span class="text-success font-weight-bold">Paid: ₦' . number_format($effectivePaid, 2) . '</span> · <span class="text-info font-weight-bold">Claims: ₦' . number_format($effectiveClaims, 2) . '</span></small>' . $fundsNote . '<div class="mt-1">' . $payStatus . '</div></div>',
                'actions' => $actions,
                'paid_num' => $effectivePaid,
                'claims_num' => $effectiveClaims,
                'total_num' => $effectivePaid + $effectiveClaims,
            ];
        }

        $doctorUser = DB::table('users')->where('id', $doctorId)->first(['surname', 'firstname', 'othername']);
        $doctorFull = $doctorUser ? trim($doctorUser->surname . ' ' . $doctorUser->firstname . ' ' . ($doctorUser->othername ?? '')) : 'Doctor';

        $clinicObj = $clinicId > 0 ? DB::table('clinics')->where('id', $clinicId)->first(['name']) : null;
        $clinicFull = $clinicObj ? $clinicObj->name : 'All Clinics';

        return response()->json([
            'meta' => [
                'doctor_name' => $doctorFull,
                'clinic_name' => $clinicFull,
                'period' => $start->format('d M Y') . ' — ' . $end->format('d M Y'),
                'total_notes' => count($rows),
                'unique_visits' => $uniqueVisits,
                'unique_patients' => $patientsSeen->unique()->count(),
                'total_paid' => $totalPaid,
                'total_claims' => $totalClaims,
                'total_funds' => $totalPaid + $totalClaims,
                'total_paid_formatted' => '₦' . number_format($totalPaid, 2),
                'total_claims_formatted' => '₦' . number_format($totalClaims, 2),
                'total_funds_formatted' => '₦' . number_format($totalPaid + $totalClaims, 2),
            ],
            'data' => $patientRows,
        ]);
    }
}
