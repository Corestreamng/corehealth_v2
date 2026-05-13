<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\AdmissionRequest;
use App\Services\StoreContextResolver;
use App\Models\StoreContextRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SurgeryWorkbenchController extends Controller
{
    /**
     * Surgery Workbench index page.
     */
    public function index()
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'SURGERY', 'DOCTOR', 'NURSE'])) {
            abort(403, 'You do not have access to the Surgery Workbench.');
        }

        // Store context resolution for consumable dispensing
        $resolver              = app(StoreContextResolver::class);
        $resolvedStore         = $resolver->resolve($user);
        $contextFallbackAction = $resolvedStore ? null : StoreContextRule::fallbackAction();
        $stores                = $resolver->candidateStores($user, 'ward');

        return view('admin.surgery.workbench', compact('stores', 'resolvedStore', 'contextFallbackAction'));
    }

    /**
     * Get queue counts per procedure status (for stat badges).
     */
    public function getQueueCounts()
    {
        $counts = Procedure::selectRaw('procedure_status, count(*) as count')
            ->whereIn('procedure_status', [
                Procedure::STATUS_REQUESTED,
                Procedure::STATUS_SCHEDULED,
                Procedure::STATUS_IN_PROGRESS,
                Procedure::STATUS_COMPLETED,
                Procedure::STATUS_CANCELLED,
            ])
            ->whereDate('created_at', '>=', Carbon::today()->subDays(30))
            ->groupBy('procedure_status')
            ->pluck('count', 'procedure_status')
            ->toArray();

        return response()->json([
            'requested'   => $counts[Procedure::STATUS_REQUESTED]   ?? 0,
            'scheduled'   => $counts[Procedure::STATUS_SCHEDULED]    ?? 0,
            'in_progress' => $counts[Procedure::STATUS_IN_PROGRESS]  ?? 0,
            'completed'   => $counts[Procedure::STATUS_COMPLETED]    ?? 0,
            'cancelled'   => $counts[Procedure::STATUS_CANCELLED]    ?? 0,
        ]);
    }

    /**
     * Get surgery queue (AJAX, DataTable-compatible).
     * Optional ?status= filter.
     */
    public function getQueue(Request $request)
    {
        $status   = $request->get('status');
        $search   = $request->get('search');
        $priority = $request->get('priority');
        $consent  = $request->get('consent');
        $date     = $request->get('date');

        $query = Procedure::with([
            'service',
            'procedureDefinition.procedureCategory',
            'patient.user',
            'patient.hmo',
            'requestedByUser',
            'teamMembers.user',
        ]);

        if ($status && in_array($status, array_keys(Procedure::STATUSES))) {
            $query->where('procedure_status', $status);
        } else {
            // Default: exclude completed/cancelled older than 7 days
            $query->where(function ($q) {
                $q->whereNotIn('procedure_status', [Procedure::STATUS_COMPLETED, Procedure::STATUS_CANCELLED])
                  ->orWhere('updated_at', '>=', Carbon::today()->subDays(7));
            });
        }

        if ($priority && in_array($priority, array_keys(Procedure::PRIORITIES))) {
            $query->where('priority', $priority);
        }

        if ($consent && in_array($consent, array_keys(Procedure::CONSENT_STATUSES))) {
            $query->where('consent_status', $consent);
        }

        if ($date) {
            $query->whereDate('scheduled_date', $date);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('patient.user', function ($u) use ($search) {
                    $u->where('surname', 'like', "%{$search}%")
                      ->orWhere('firstname', 'like', "%{$search}%");
                })->orWhereHas('patient', function ($p) use ($search) {
                    $p->where('file_no', 'like', "%{$search}%");
                })->orWhereHas('service', function ($s) use ($search) {
                    $s->where('service_name', 'like', "%{$search}%");
                });
            });
        }

        $procedures = $query->orderByRaw("FIELD(procedure_status, 'in_progress', 'scheduled', 'requested', 'completed', 'cancelled')")
            ->orderByRaw("FIELD(priority, 'emergency', 'urgent', 'routine')")
            ->orderBy('scheduled_date', 'ASC')
            ->get();

        $results = $procedures->map(function ($proc) {
            $patient = $proc->patient;
            $user    = optional($patient)->user;

            return [
                'id'               => $proc->id,
                'service_name'     => optional($proc->service)->service_name ?? 'Procedure',
                'category'         => optional(optional($proc->procedureDefinition)->procedureCategory)->category_name ?? '',
                'procedure_status' => $proc->procedure_status,
                'priority'         => $proc->priority,
                'scheduled_date'   => $proc->scheduled_date ? $proc->scheduled_date->format('d M Y') : null,
                'scheduled_time'   => $proc->scheduled_time,
                'operating_room'   => $proc->operating_room,
                'consent_status'   => $proc->consent_status,
                'patient_id'       => optional($patient)->id,
                'patient_name'     => $user ? trim("{$user->surname} {$user->firstname}") : 'Unknown',
                'file_no'          => optional($patient)->file_no,
                'hmo'              => optional(optional($patient)->hmo)->hmo_name ?? null,
                'requested_by'     => optional($proc->requestedByUser)->name ?? null,
                'requested_on'     => $proc->requested_on ? $proc->requested_on->format('d M Y') : null,
                'team_count'       => $proc->teamMembers->count(),
                'show_url'         => route('patient-procedures.show', $proc->id),
            ];
        });

        return response()->json(['data' => $results]);
    }

    /**
     * Get a patient's active procedures (for inline drawer).
     */
    public function getPatientProcedures($patientId)
    {
        $procedures = Procedure::with([
            'service',
            'procedureDefinition.procedureCategory',
            'requestedByUser',
            'teamMembers.user',
        ])
            ->where('patient_id', $patientId)
            ->orderByRaw("FIELD(procedure_status, 'in_progress', 'scheduled', 'requested', 'completed', 'cancelled')")
            ->orderBy('created_at', 'DESC')
            ->get();

        $results = $procedures->map(function ($proc) {
            return [
                'id'               => $proc->id,
                'service_name'     => optional($proc->service)->service_name ?? 'Procedure',
                'category'         => optional(optional($proc->procedureDefinition)->procedureCategory)->category_name ?? '',
                'procedure_status' => $proc->procedure_status,
                'priority'         => $proc->priority,
                'scheduled_date'   => $proc->scheduled_date ? $proc->scheduled_date->format('d M Y') : null,
                'consent_status'   => $proc->consent_status,
                'operating_room'   => $proc->operating_room,
                'show_url'         => route('patient-procedures.show', $proc->id),
            ];
        });

        return response()->json(['procedures' => $results]);
    }

    /**
     * Search patients for the workbench.
     */
    public function searchPatients(Request $request)
    {
        $term = $request->get('term', '');

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $patients = Patient::with(['user', 'hmo'])
            ->where(function ($query) use ($term) {
                $query->whereHas('user', function ($u) use ($term) {
                    $u->where('surname', 'like', "%{$term}%")
                      ->orWhere('firstname', 'like', "%{$term}%")
                      ->orWhere('othername', 'like', "%{$term}%");
                })
                ->orWhere('file_no', 'like', "%{$term}%")
                ->orWhere('phone_no', 'like', "%{$term}%");
            })
            ->whereExists(function ($q) {
                $q->from('procedures')->whereColumn('procedures.patient_id', 'patients.id');
            })
            ->limit(15)
            ->get();

        $patientIds = $patients->pluck('id')->toArray();

        // Surgery stats: count procedures per status per patient
        $procedureCounts = Procedure::whereIn('patient_id', $patientIds)
            ->selectRaw('patient_id, procedure_status, COUNT(*) as cnt')
            ->groupBy('patient_id', 'procedure_status')
            ->get()
            ->groupBy('patient_id');

        // Next upcoming scheduled procedure per patient
        $nextScheduled = Procedure::whereIn('patient_id', $patientIds)
            ->whereIn('procedure_status', [Procedure::STATUS_SCHEDULED, Procedure::STATUS_REQUESTED])
            ->where(function ($q) {
                $q->whereDate('scheduled_date', '>=', Carbon::today())
                  ->orWhereNull('scheduled_date');
            })
            ->with('service')
            ->orderBy('scheduled_date', 'ASC')
            ->get()
            ->groupBy('patient_id');

        // Admission status
        $admittedPatientIds = AdmissionRequest::whereIn('patient_id', $patientIds)
            ->where('discharged', 0)
            ->pluck('patient_id')
            ->toArray();

        $results = $patients->map(function ($patient) use ($procedureCounts, $nextScheduled, $admittedPatientIds) {
            $user    = $patient->user;
            $counts  = $procedureCounts->get($patient->id, collect());
            $byStatus = $counts->pluck('cnt', 'procedure_status');

            $nextProc     = optional($nextScheduled->get($patient->id, collect())->first());
            $nextSvc      = optional($nextProc->service)->service_name ?? null;
            $nextDate     = $nextProc->scheduled_date
                ? Carbon::parse($nextProc->scheduled_date)->format('d M Y')
                : null;

            $dob = $patient->dob;
            $age = $dob ? Carbon::parse($dob)->age : null;

            return [
                'id'                   => $patient->id,
                'user_id'              => $patient->user_id,
                'name'                 => $user ? userfullname($patient->user_id) : 'Unknown',
                'file_no'              => $patient->file_no,
                'age'                  => $age,
                'gender'               => $patient->sex ?? 'N/A',
                'phone'                => $patient->phone_no ?? 'N/A',
                'photo'                => $user && $user->photo ? $user->photo : 'avatar.png',
                'hmo'                  => optional($patient->hmo)->hmo_name ?? null,
                'is_admitted'          => in_array($patient->id, $admittedPatientIds),
                // Surgery-specific
                'active_procedures'    => (int) ($byStatus[Procedure::STATUS_IN_PROGRESS] ?? 0),
                'scheduled_procedures' => (int) ($byStatus[Procedure::STATUS_SCHEDULED]   ?? 0),
                'requested_procedures' => (int) ($byStatus[Procedure::STATUS_REQUESTED]   ?? 0),
                'total_procedures'     => $counts->sum('cnt'),
                'next_procedure_name'  => $nextSvc,
                'next_scheduled_date'  => $nextDate,
            ];
        });

        return response()->json($results);
    }
}
