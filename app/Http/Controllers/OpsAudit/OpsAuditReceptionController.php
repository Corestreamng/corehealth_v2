<?php

namespace App\Http\Controllers\OpsAudit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\DoctorQueue;
use App\Models\DoctorAppointment;
use App\Models\SpecialistReferral;
use App\Models\AuditMark;

class OpsAuditReceptionController extends OpsAuditBaseController
{
    /**
     * Show the reception audit page.
     */
    public function index(Request $request)
    {
        $clinics = \App\Models\Clinic::orderBy('name')->pluck('name', 'id');
        $hmos = \App\Models\Hmo::with('scheme')->orderBy('name')->get()->groupBy(fn($hmo) => $hmo->scheme ? $hmo->scheme->name : 'Other Schemes');
        $hmoSchemes = \App\Models\HmoScheme::orderBy('name')->pluck('name', 'id');

        return view('admin.ops_audit.reception', compact('clinics', 'hmos', 'hmoSchemes'));
    }

    /**
     * DataTable AJAX endpoint for a specific tab.
     */
    public function data(Request $request, $tab)
    {
        // Handle bulk stamp actions (POST)
        if ($request->isMethod('post') && in_array($request->action, ['bulk_stamp_preview', 'bulk_stamp'])) {
            return $this->handleBulkStamp($request, $tab);
        }

        switch ($tab) {
            case 'queues':
                return $this->queuesData($request);
            case 'appointments':
                return $this->appointmentsData($request);
            case 'referrals':
                return $this->referralsData($request);
            default:
                return response()->json(['error' => 'Invalid tab'], 400);
        }
    }

    /**
     * Tab 1: Queue Registrations (DoctorQueue)
     */
    protected function queuesData(Request $request)
    {
        $query = DoctorQueue::with([
            'patient.user',
            'patient.hmo.scheme',
            'clinic',
            'doctor.user',
            'receptionist.user',
            'request_entry.payment.user',
        ]);

        // Apply filters
        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'request_entry');

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('clinic_id')) {
            $query->where('clinic_id', $request->clinic_id);
        }
        if ($request->filled('doctor_id')) {
            $query->where('staff_id', $request->doctor_id);
        }
        if ($request->filled('receptionist_id')) {
            $query->where('receptionist_id', $request->receptionist_id);
        }
        if ($request->filled('gender')) {
            $query->whereHas('patient.user', fn($q) => $q->where('gender', $request->gender));
        }
        if ($request->filled('hmo_id')) {
            $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));
        }
        if ($request->filled('hmo_scheme_id')) {
            $query->whereHas('patient.hmo', fn($q) => $q->where('hmo_scheme_id', $request->hmo_scheme_id));
        }

        // Clone for KPIs before pagination
        $kpiQuery = clone $query;

        // Server-side DataTable
        return $this->buildDataTableResponse($query, $request, function ($q) {
            return $q;
        }, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $doctor = $row->doctor ?? $row->staff;

            $statusMap = [1 => ['Waiting', 'warning'], 2 => ['In Progress', 'info'], 5 => ['Completed', 'success']];
            $s = $statusMap[$row->status] ?? [$row->status, 'secondary'];

            $waitMins = $row->consultation_started_at && $row->created_at
                ? Carbon::parse($row->created_at)->diffInMinutes(Carbon::parse($row->consultation_started_at))
                : ($row->status == 5 ? '-' : Carbon::parse($row->created_at)->diffInMinutes(now()));

            return [
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y H:i') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'source' => '<span class="badge bg-' . ($row->source === 'emergency_intake' ? 'danger' : 'light text-dark border') . '">' . ucfirst(str_replace('_', ' ', $row->source)) . '</span>',
                'priority' => '<span class="badge bg-' . ($row->priority === 'emergency' ? 'danger' : ($row->priority === 'urgent' ? 'warning text-dark' : 'light text-dark border')) . ' font-weight-bold">' . ucfirst($row->priority) . '</span>',
                'clinic' => $row->clinic?->name ?? '<span class="text-muted">-</span>',
                'doctor' => $doctor?->user?->firstname ? ($doctor->user->firstname . ' ' . ($doctor->user->surname ?? '')) : '<span class="text-muted">-</span>',
                'receptionist' => $row->receptionist?->user?->firstname ? ($row->receptionist->user->firstname . ' ' . ($row->receptionist->user->surname ?? '')) : '<span class="text-muted">-</span>',
                'status' => '<span class="badge bg-' . $s[1] . ' font-weight-bold">' . $s[0] . '</span>',
                'wait' => is_numeric($waitMins) ? $waitMins . ' min' : $waitMins,
                'vitals' => $row->vitals_taken ? '<i class="mdi mdi-check-circle text-success"></i>' : '<i class="mdi mdi-close-circle text-danger"></i>',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'DoctorQueue'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            $total = $all->count();

            return [
                ['label' => 'Total Registrations', 'value' => number_format($total), 'color' => '#0d6efd'],
                ['label' => 'Emergency', 'value' => number_format($all->where('source', 'emergency_intake')->count()), 'color' => '#dc3545'],
                ['label' => 'Routine', 'value' => number_format($all->where('priority', 'routine')->count()), 'color' => '#198754'],
                ['label' => 'Walk-Ins', 'value' => number_format($all->where('source', 'reception')->count()), 'color' => '#6c757d'],
                ['label' => 'Appointment', 'value' => number_format($all->where('source', 'appointment')->count()), 'color' => '#0dcaf0'],
                ['label' => 'Maternity', 'value' => number_format($all->where('source', 'maternity')->count()), 'color' => '#d63384'],
                ['label' => 'Completed', 'value' => number_format($all->where('status', 5)->count()), 'color' => '#198754'],
                ['label' => 'Avg Wait (min)', 'value' => $all->where('consultation_started_at', '!=', null)->count() > 0
                    ? round($all->where('consultation_started_at', '!=', null)->avg(fn($r) => Carbon::parse($r->created_at)->diffInMinutes(Carbon::parse($r->consultation_started_at))))
                    : '-', 'color' => '#6610f2'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 2: Appointments (DoctorAppointment)
     */
    protected function appointmentsData(Request $request)
    {
        $query = DoctorAppointment::with([
            'patient.user',
            'patient.hmo.scheme',
            'clinic',
            'doctor.user',
            'bookedBy.user',
            'serviceRequest.payment.user',
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'serviceRequest');

        if ($request->filled('appointment_type')) $query->where('appointment_type', $request->appointment_type);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('source')) $query->where('source', $request->source);
        if ($request->filled('clinic_id')) $query->where('clinic_id', $request->clinic_id);
        if ($request->filled('doctor_id')) $query->where('staff_id', $request->doctor_id);
        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));
        if ($request->filled('hmo_scheme_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('hmo_scheme_id', $request->hmo_scheme_id));
        if ($request->filled('gender')) $query->whereHas('patient.user', fn($q) => $q->where('gender', $request->gender));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $sr = $row->serviceRequest;
            $payment = $sr?->payment;

            $statusMap = [0 => ['Scheduled', 'secondary'], 1 => ['Confirmed', 'primary'], 2 => ['Checked-In', 'info'], 3 => ['Completed', 'success'], 4 => ['Cancelled', 'danger'], 5 => ['No-Show', 'warning text-dark'], 6 => ['Rescheduled', 'info'], 7 => ['Expired', 'dark']];
            $s = $statusMap[$row->status] ?? [$row->status, 'secondary'];

            return [
                'date' => $row->appointment_date ? Carbon::parse($row->appointment_date)->format('d M Y') : ($row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-'),
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'type' => '<span class="badge bg-light text-dark border">' . ucfirst(str_replace('_', ' ', $row->appointment_type ?? '-')) . '</span>',
                'clinic' => $row->clinic?->name ?? '-',
                'doctor' => $row->doctor?->user?->firstname ? ($row->doctor->user->firstname . ' ' . ($row->doctor->user->surname ?? '')) : '-',
                'status' => '<span class="badge bg-' . $s[1] . ' font-weight-bold">' . $s[0] . '</span>',
                'booked_by' => $row->bookedBy?->user?->firstname ? ($row->bookedBy->user->firstname . ' ' . ($row->bookedBy->user->surname ?? '')) : '-',
                'cancel_reason' => $row->cancellation_reason ? '<small class="text-danger">' . e(\Illuminate\Support\Str::limit($row->cancellation_reason, 30)) . '</small>' : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'DoctorAppointment'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            return [
                ['label' => 'Total', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
                ['label' => 'Confirmed', 'value' => number_format($all->where('status', 1)->count()), 'color' => '#0d6efd'],
                ['label' => 'Completed', 'value' => number_format($all->where('status', 3)->count()), 'color' => '#198754'],
                ['label' => 'No-Shows', 'value' => number_format($all->where('status', 5)->count()), 'color' => '#ffc107'],
                ['label' => 'Cancellations', 'value' => number_format($all->where('status', 4)->count()), 'color' => '#dc3545'],
                ['label' => 'Prepaid Follow-Ups', 'value' => number_format($all->where('is_prepaid_followup', true)->count()), 'color' => '#6610f2'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 3: Referrals (SpecialistReferral)
     */
    protected function referralsData(Request $request)
    {
        $query = SpecialistReferral::with([
            'patient.user',
            'patient.hmo.scheme',
            'referringDoctor',
            'referringClinic',
            'targetClinic',
            'actionedBy',
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);

        if ($request->filled('referral_type')) $query->where('referral_type', $request->referral_type);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('urgency')) $query->where('urgency', $request->urgency);
        if ($request->filled('referring_doctor_id')) $query->where('referring_doctor_id', $request->referring_doctor_id);
        if ($request->filled('referring_clinic_id')) $query->where('referring_clinic_id', $request->referring_clinic_id);
        if ($request->filled('target_clinic_id')) $query->where('target_clinic_id', $request->target_clinic_id);
        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));
        if ($request->filled('gender')) $query->whereHas('patient.user', fn($q) => $q->where('gender', $request->gender));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;

            $statusColors = ['pending' => 'warning text-dark', 'booked' => 'info', 'referred_out' => 'primary', 'completed' => 'success', 'declined' => 'danger', 'cancelled' => 'secondary'];

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'referring_doctor' => $row->referringDoctor?->firstname ? ($row->referringDoctor->firstname . ' ' . ($row->referringDoctor->surname ?? '')) : '-',
                'type' => '<span class="badge bg-' . ($row->referral_type === 'external' ? 'danger' : 'primary') . '">' . ucfirst($row->referral_type ?? '-') . '</span>',
                'target' => $row->referral_type === 'external'
                    ? '<small class="text-muted">' . e($row->external_facility_name ?? '-') . '</small>'
                    : ($row->targetClinic?->name ?? '-'),
                'urgency' => '<span class="badge bg-' . ($row->urgency === 'emergency' ? 'danger' : ($row->urgency === 'urgent' ? 'warning text-dark' : 'light text-dark border')) . '">' . ucfirst($row->urgency ?? '-') . '</span>',
                'status' => '<span class="badge bg-' . ($statusColors[$row->status] ?? 'secondary') . ' font-weight-bold">' . ucfirst($row->status ?? '-') . '</span>',
                'actioned_by' => $row->actionedBy?->firstname ? ($row->actionedBy->firstname . ' ' . ($row->actionedBy->surname ?? '')) : '-',
                'audit' => $this->renderAuditAction($row, 'SpecialistReferral'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            return [
                ['label' => 'Total', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
                ['label' => 'Internal', 'value' => number_format($all->where('referral_type', 'internal')->count()), 'color' => '#0d6efd'],
                ['label' => 'External', 'value' => number_format($all->where('referral_type', 'external')->count()), 'color' => '#dc3545'],
                ['label' => 'Pending', 'value' => number_format($all->where('status', 'pending')->count()), 'color' => '#ffc107'],
                ['label' => 'Booked', 'value' => number_format($all->where('status', 'booked')->count()), 'color' => '#0dcaf0'],
                ['label' => 'Referred Out', 'value' => number_format($all->where('status', 'referred_out')->count()), 'color' => '#6610f2'],
            ];
        }, $kpiQuery);
    }

    // =========================================================
    // Shared Helpers
    // =========================================================

    protected function handleBulkStamp(Request $request, $tab)
    {
        $modelMap = [
            'queues' => DoctorQueue::class,
            'appointments' => DoctorAppointment::class,
            'referrals' => SpecialistReferral::class,
        ];
        
        $request->merge(['zone_key' => 'ops_audit.reception.' . $tab]);
        return $this->processBulkStamp($request, $tab, $modelMap);
    }
}
