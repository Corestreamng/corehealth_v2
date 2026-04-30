<?php

namespace App\Http\Controllers;

use App\Models\AdmissionRequest;
use App\Models\AncVisit;
use App\Models\DeathRecord;
use App\Models\DeliveryRecord;
use App\Models\ImmunizationRecord;
use App\Models\Encounter;
use App\Models\Hmo;
use App\Models\ImagingServiceRequest;
use App\Models\LabServiceRequest;
use App\Models\MaternityBaby;
use App\Models\MaternityEnrollment;
use App\Models\Patient;
use App\Models\PatientImmunizationSchedule;
use App\Models\PostnatalVisit;
use App\Models\ProcedureDefinition;
use App\Models\ProductRequest;
use App\Models\SpecialistReferral;
use App\Models\Ward;
use App\Models\Bed;
use App\Models\Procedure;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClinicalReportsController extends Controller
{
    /**
     * Get Clinical Statistics for Reception Workbench
     */
    public function getClinicalStats(Request $request)
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : now()->startOfMonth()->startOfDay();
        $to   = $request->filled('date_to')   ? Carbon::parse($request->get('date_to'))->endOfDay()     : now()->endOfDay();

        $totalEncounters = Encounter::whereBetween('created_at', [$from, $to])->count();
        $uniquePatients  = Encounter::whereBetween('created_at', [$from, $to])->distinct('patient_id')->count('patient_id');
        $totalAdmissions = AdmissionRequest::whereBetween('created_at', [$from, $to])->count();
        $totalSurgeries  = Procedure::where('procedure_status', 'completed')
                            ->whereBetween('actual_end_time', [$from, $to])
                            ->whereHas('procedureDefinition', fn($q) => $q->where('is_surgical', 1))
                            ->count();
        $totalDeaths  = DeathRecord::whereBetween('date_of_death', [$from, $to])->count();
        $totalBirths  = (int) DeliveryRecord::whereBetween('delivery_date', [$from, $to])->sum('number_of_babies');
        $totalLab     = LabServiceRequest::whereBetween('created_at', [$from, $to])->count();
        $totalImaging = ImagingServiceRequest::whereBetween('created_at', [$from, $to])->count();

        $encountersByDay = Encounter::whereBetween('created_at', [$from, $to])
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get();

        $admissionsByDay = AdmissionRequest::whereBetween('created_at', [$from, $to])
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get();

        return response()->json([
            'total_encounters'  => $totalEncounters,
            'unique_patients'   => $uniquePatients,
            'total_admissions'  => $totalAdmissions,
            'total_surgeries'   => $totalSurgeries,
            'total_deaths'      => $totalDeaths,
            'total_births'      => $totalBirths,
            'total_lab'         => $totalLab,
            'total_imaging'     => $totalImaging,
            'encounters_by_day' => $encountersByDay,
            'admissions_by_day' => $admissionsByDay,
        ]);
    }

    /**
     * Search Diagnosis with Keyword
     */
    public function searchDiagnosis(Request $request)
    {
        $request->validate([
            'keyword'   => 'required|string|min:2',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $keyword = $request->keyword;
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $dateTo   = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : now()->endOfDay();

        // Query encounters with reasons_for_encounter matching keyword
        // Since it's JSON, we use LIKE
        $encounters = Encounter::with(['patient.user', 'doctor'])
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('reasons_for_encounter', 'like', "%{$keyword}%")
            ->get();

        $grouped = [];
        foreach ($encounters as $e) {
            $rawReasons = json_decode($e->reasons_for_encounter, true);
            if (!is_array($rawReasons)) continue;

            foreach ($rawReasons as $item) {
                // Handle both simple strings and new JSON object format
                $name = is_array($item) ? ($item['name'] ?? ($item['value'] ?? 'Unknown')) : $item;
                $query = is_array($item) ? ($item['comment_1'] ?? 'N/A') : ($e->reasons_for_encounter_comment_1 ?? 'N/A');
                $status = is_array($item) ? ($item['comment_2'] ?? 'N/A') : ($e->reasons_for_encounter_comment_2 ?? 'N/A');

                if (stripos($name, $keyword) !== false) {
                    if (!isset($grouped[$name])) {
                        $grouped[$name] = [
                            'diagnosis' => $name,
                            'icd_code'  => is_array($item) ? ($item['code'] ?? '') : '',
                            'total_encounters' => 0,
                            'unique_patients' => 0,
                            'patient_ids' => [],
                            'statuses' => [],
                            'queries'  => [],
                            'encounters' => []
                        ];
                    }
                    $grouped[$name]['total_encounters']++;
                    if (!in_array($e->patient_id, $grouped[$name]['patient_ids'])) {
                        $grouped[$name]['unique_patients']++;
                        $grouped[$name]['patient_ids'][] = $e->patient_id;
                    }
                    if ($status !== 'N/A' && $status !== 'NA' && !in_array($status, $grouped[$name]['statuses'])) {
                        $grouped[$name]['statuses'][] = $status;
                    }
                    if ($query !== 'N/A' && $query !== 'NA' && !in_array($query, $grouped[$name]['queries'])) {
                        $grouped[$name]['queries'][] = $query;
                    }

                    $grouped[$name]['encounters'][] = [
                        'id' => $e->id,
                        'patient_name' => userfullname($e->patient->user_id),
                        'patient_id' => $e->patient_id,
                        'file_no' => $e->patient->file_no ?? '',
                        'date' => $e->created_at->format('M d, Y H:i'),
                        'doctor' => $e->doctor ? userfullname($e->doctor->id) : 'N/A',
                        'query' => $query,
                        'status' => $status,
                        'icd_code' => is_array($item) ? ($item['code'] ?? '') : '',
                    ];
                }
            }
        }

        // Sort by unique patients desc, strip internal tracking key
        usort($grouped, fn($a, $b) => $b['unique_patients'] - $a['unique_patients']);
        foreach ($grouped as &$g) {
            unset($g['patient_ids']);
        }
        return response()->json(array_values($grouped));
    }

    /**
     * Get drill-down details for a specific encounter
     */
    public function getEncounterDrillDown($encounterId)
    {
        $encounter = Encounter::with([
            'patient.user',
            'doctor',
            'labRequests.service',
            'imagingRequests.service',
            'productRequests.product',
        ])->findOrFail($encounterId);

        $procedures = Procedure::with('procedureDefinition')
            ->where('encounter_id', $encounterId)
            ->get();

        return response()->json([
            'notes' => $encounter->notes,
            'labs' => $encounter->labRequests,
            'imaging' => $encounter->imagingRequests,
            'prescriptions' => $encounter->productRequests,
            'procedures' => $procedures,
        ]);
    }

    private function getVisitsByUnit($from, $to)
    {
        return Encounter::whereBetween('encounters.created_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->join('doctor_queues', 'encounters.queue_id', '=', 'doctor_queues.id')
            ->join('clinics', 'doctor_queues.clinic_id', '=', 'clinics.id')
            ->select('clinics.name', DB::raw('count(*) as count'))
            ->groupBy('clinics.name')
            ->orderByDesc('count')
            ->get();
    }

    private function _hmoTrendsSummary($from, $to)
    {
        return Patient::join('encounters', 'patients.id', '=', 'encounters.patient_id')
            ->whereBetween('encounters.created_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->join('hmos', 'patients.hmo_id', '=', 'hmos.id')
            ->select('hmos.name', DB::raw('count(encounters.id) as count'))
            ->groupBy('hmos.name')
            ->orderByDesc('count')
            ->get();
    }

    private function getCountByDiagnosisKeyword($keyword, $from, $to)
    {
        return Encounter::whereBetween('created_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->where('reasons_for_encounter', 'like', "%{$keyword}%")
            ->count();
    }

    private function getMaternityStats($from, $to)
    {
        $births = \App\Models\DeliveryRecord::whereBetween('delivery_date', [$from, $to])->sum('number_of_babies');
        $ancEnrollments = \App\Models\MaternityEnrollment::whereBetween('enrollment_date', [$from, $to])->count();
        $ancVisits = \App\Models\AncVisit::whereBetween('visit_date', [$from, $to])->count();
        $postnatalVisits = \App\Models\PostnatalVisit::whereBetween('visit_date', [$from, $to])->count();

        return [
            'births' => (int) $births,
            'anc_enrollments' => $ancEnrollments,
            'anc_visits' => $ancVisits,
            'postnatal_visits' => $postnatalVisits
        ];
    }

    public function getDrillDownDetails(Request $request)
    {
        $type   = $request->get('type');
        $wardId = $request->get('ward_id');
        $from   = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : now()->startOfMonth()->startOfDay();
        $to     = $request->get('date_to')   ? Carbon::parse($request->get('date_to'))->endOfDay()     : now()->endOfDay();

        $data = [];
        switch ($type) {
            case 'mortality':
                $data = DeathRecord::with(['patient.user'])
                    ->whereBetween('date_of_death', [$from, $to])
                    ->get()
                    ->map(function ($r) {
                        $dob = $r->patient->dob ?? null;
                        $age = $dob ? Carbon::parse($dob)->age : 'N/A';
                        return [
                            'patient'              => userfullname($r->patient->user_id),
                            'file_no'              => $r->patient->file_no ?? '',
                            'age'                  => $age,
                            'sex'                  => ucfirst($r->patient->sex ?? $r->patient->gender ?? 'N/A'),
                            'date'                 => Carbon::parse($r->date_of_death)->format('Y-m-d') . ($r->time_of_death ? ' ' . $r->time_of_death : ''),
                            'death_type'           => strtoupper($r->death_type ?? 'RIP'),
                            'primary_cause'        => $r->cause_of_death_primary ?? 'N/A',
                            'contributing_factors' => $r->cause_of_death_description ?? 'None',
                            'patient_id'           => $r->patient_id,
                        ];
                    });
                break;
            case 'maternity':
                $sub = $request->get('sub_category', 'deliveries');
                switch($sub) {
                    case 'enrollments':
                        $data = \App\Models\MaternityEnrollment::with(['patient.user'])
                            ->whereBetween('enrollment_date', [$from, $to])
                            ->get()->map(fn($r) => [
                                'patient' => userfullname($r->patient->user_id),
                                'patient_id' => $r->patient_id,
                                'file_no' => $r->patient->file_no ?? '',
                                'date' => $r->enrollment_date->format('Y-m-d'),
                                'risk' => ucfirst($r->risk_level),
                                'status' => ucfirst($r->status)
                            ]);
                        break;
                    case 'visits':
                        $data = \App\Models\AncVisit::with(['enrollment.patient.user'])
                            ->whereBetween('visit_date', [$from, $to])
                            ->get()->map(fn($r) => [
                                'patient' => userfullname($r->enrollment->patient->user_id),
                                'patient_id' => $r->enrollment->patient_id ?? null,
                                'file_no' => $r->enrollment->patient->file_no ?? '',
                                'date' => $r->visit_date->format('Y-m-d'),
                                'weight' => $r->weight_kg . 'kg',
                                'bp' => ($r->blood_pressure_systolic ?? '-') . '/' . ($r->blood_pressure_diastolic ?? '-')
                            ]);
                        break;
                    case 'babies':
                        $data = \App\Models\MaternityBaby::with(['enrollment.patient.user', 'patient.user'])
                            ->whereBetween('created_at', [$from, $to])
                            ->get()->map(fn($r) => [
                                'mother' => userfullname($r->enrollment->patient->user_id),
                                'baby' => userfullname($r->patient->user_id),
                                'sex' => ucfirst($r->sex),
                                'status' => ucfirst($r->status)
                            ]);
                        break;
                    case 'admissions':
                        $data = \App\Models\AdmissionRequest::with(['patient.user', 'doctor'])
                            ->whereBetween('created_at', [$from, $to])
                            ->get()
                            ->map(function ($r) {
                                return [
                                    'patient' => userfullname($r->patient->user_id),
                                    'date' => $r->created_at->format('Y-m-d H:i'),
                                    'reason' => $r->admission_reason ?? 'N/A',
                                    'doctor' => $r->doctor ? userfullname($r->doctor->id) : 'N/A',
                                    'status' => ucfirst(str_replace('_', ' ', $r->admission_status))
                                ];
                            });
                        break;
                    case 'postnatal':
                        $data = \App\Models\PostnatalVisit::with(['enrollment.patient.user'])
                            ->whereBetween('visit_date', [$from, $to])
                            ->get()->map(fn($r) => [
                                'patient' => userfullname($r->enrollment->patient->user_id),
                                'patient_id' => $r->enrollment->patient_id ?? null,
                                'date' => $r->visit_date->format('Y-m-d'),
                                'mother_condition' => $r->general_condition ?? 'N/A',
                                'baby_condition' => $r->baby_general_condition ?? 'N/A'
                            ]);
                        break;
                    case 'discharges':
                        $data = \App\Models\MaternityEnrollment::with(['patient.user'])
                            ->where('status', 'completed')
                            ->whereBetween('completed_at', [$from, $to])
                            ->get()->map(fn($r) => [
                                'patient' => userfullname($r->patient->user_id),
                                'date' => $r->completed_at->format('Y-m-d'),
                                'outcome' => $r->outcome_summary,
                                'risk' => ucfirst($r->risk_level)
                            ]);
                        break;
                    case 'deliveries':
                    default:
                        $data = DeliveryRecord::with(['patient.user'])
                            ->whereBetween('delivery_date', [$from, $to])
                            ->get()
                            ->map(function ($r) {
                                return [
                                    'mother' => userfullname($r->patient->user_id),
                                    'date' => $r->delivery_date->format('Y-m-d') . ' ' . $r->delivery_time,
                                    'babies' => $r->number_of_babies,
                                    'outcome' => $r->type_of_delivery ?? 'N/A'
                                ];
                            });
                        break;
                }
                break;
            case 'surgeries':
                $data = Procedure::with(['patient.user', 'requestedByUser', 'service', 'procedureDefinition.procedureCategory'])
                    ->where('procedure_status', 'completed')
                    ->whereBetween('actual_end_time', [$from, $to])
                    ->whereHas('procedureDefinition', function ($q) {
                        $q->where('is_surgical', 1);
                    })
                    ->get()
                    ->map(function ($r) {
                        return [
                            'patient'        => userfullname($r->patient->user_id),
                            'patient_id'     => $r->patient_id,
                            'file_no'        => $r->patient->file_no ?? '',
                            'date'           => $r->actual_end_time->format('Y-m-d H:i'),
                            'procedure_name' => $r->procedureDefinition ? $r->procedureDefinition->name : ($r->service ? $r->service->name : 'N/A'),
                            'category'       => $r->procedureDefinition && $r->procedureDefinition->procedureCategory ? $r->procedureDefinition->procedureCategory->name : 'N/A',
                            'doctor'         => $r->requestedByUser ? userfullname($r->requestedByUser->id) : 'N/A',
                            'outcome'        => $r->outcome ?? 'N/A',
                        ];
                    });
                break;
            case 'diagnosis':
                $icdCode  = $request->get('icd_code');
                $diagName = $request->get('diagnosis_name');
                $dq = Encounter::with(['patient.user', 'doctor'])
                    ->whereBetween('created_at', [$from, $to]);
                if ($icdCode) {
                    $dq->where('reasons_for_encounter', 'like', '%' . $icdCode . '%');
                } elseif ($diagName) {
                    $dq->where('reasons_for_encounter', 'like', '%' . $diagName . '%');
                }
                $data = $dq->orderByDesc('created_at')->get()->map(function ($e) use ($icdCode, $diagName) {
                    $reasons   = json_decode($e->reasons_for_encounter, true) ?: [];
                    $queryType = 'N/A';
                    foreach ($reasons as $item) {
                        if (!is_array($item)) continue;
                        $matchCode = $icdCode && isset($item['code']) && trim($item['code']) === trim($icdCode);
                        $matchName = !$icdCode && $diagName && stripos($item['name'] ?? '', $diagName) !== false;
                        if ($matchCode || $matchName) {
                            $queryType = $item['comment_1'] ?? 'N/A';
                            break;
                        }
                    }
                    return [
                        'id'         => $e->id,
                        'patient'    => userfullname($e->patient->user_id),
                        'file_no'    => $e->patient->file_no ?? '',
                        'patient_id' => $e->patient_id,
                        'date'       => $e->created_at->format('Y-m-d H:i'),
                        'doctor'     => $e->doctor ? userfullname($e->doctor->id) : 'N/A',
                        'query_type' => $queryType,
                        'status'     => $e->status ?? 'N/A',
                    ];
                });
                break;
            case 'immunization':
                $data = ImmunizationRecord::with(['patient.user', 'administeredBy'])
                    ->whereBetween('administered_at', [$from, $to])
                    ->get()
                    ->map(function ($r) {
                        return [
                            'patient' => userfullname($r->patient->user_id),
                            'patient_id' => $r->patient_id,
                            'file_no' => $r->patient->file_no ?? '',
                            'date' => $r->administered_at->format('Y-m-d H:i'),
                            'vaccine' => $r->vaccine_name,
                            'nurse' => $r->administeredBy ? userfullname($r->administeredBy->id) : 'N/A'
                        ];
                    });
                break;
            case 'referrals':
                $data = SpecialistReferral::with(['patient.user', 'referringDoctor.user', 'targetClinic'])
                    ->whereBetween('created_at', [$from, $to])
                    ->get()
                    ->map(function ($r) {
                        return [
                            'patient' => userfullname($r->patient->user_id),
                            'patient_id' => $r->patient_id,
                            'file_no' => $r->patient->file_no ?? '',
                            'date' => $r->created_at->format('Y-m-d H:i'),
                            'from_doctor' => $r->referringDoctor && $r->referringDoctor->user ? userfullname($r->referringDoctor->user->id) : 'N/A',
                            'to_clinic' => $r->targetClinic ? $r->targetClinic->name : ($r->external_facility_name ?? 'N/A')
                        ];
                    });
                break;
            case 'occupancy':
                $occupiedBeds = Bed::with(['wardRelation', 'occupant'])
                    ->where('bed_status', 'occupied')
                    ->when($wardId, fn($q) => $q->where('ward_id', $wardId))
                    ->get();
                $occupantIds = $occupiedBeds->pluck('occupant_id')->filter()->unique()->values();
                $activeAdmissions = AdmissionRequest::where('discharged', 0)
                    ->whereIn('patient_id', $occupantIds)
                    ->get()
                    ->keyBy('patient_id');
                $data = $occupiedBeds->map(function ($b) use ($activeAdmissions) {
                    $adm        = $b->occupant_id ? ($activeAdmissions[$b->occupant_id] ?? null) : null;
                    $admittedAt = $adm ? $adm->created_at : $b->updated_at;
                    return [
                        'patient'     => $b->occupant ? userfullname($b->occupant->user_id) : 'N/A',
                        'patient_id'  => $b->occupant_id,
                        'ward'        => $b->wardRelation ? $b->wardRelation->name : ($b->ward ?? 'N/A'),
                        'bed'         => $b->name,
                        'admitted_at' => $admittedAt ? Carbon::parse($admittedAt)->format('Y-m-d H:i') : 'N/A',
                        'days'        => $admittedAt ? (int) Carbon::parse($admittedAt)->diffInDays(now()) : 0,
                    ];
                });
                break;
        }

        return response()->json($data);
    }

    private function getWardOccupancy()
    {
        return Bed::where('bed_status', 'occupied')->count();
    }

    // -----------------------------------------------------------------------
    // NEW: Unit Visits — encounters per clinic with optional drill-down
    // -----------------------------------------------------------------------
    public function getUnitVisits(Request $request)
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $to   = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : now()->endOfDay();
        $clinicId = $request->get('clinic_id');

        $summary = Encounter::whereBetween('encounters.created_at', [$from, $to])
            ->join('doctor_queues', 'encounters.queue_id', '=', 'doctor_queues.id')
            ->join('clinics', 'doctor_queues.clinic_id', '=', 'clinics.id')
            ->when($clinicId, fn($q) => $q->where('clinics.id', $clinicId))
            ->select('clinics.id as clinic_id', 'clinics.name as clinic_name', DB::raw('count(*) as total'))
            ->groupBy('clinics.id', 'clinics.name')
            ->orderByDesc('total')
            ->get();

        $drillDown = null;
        if ($clinicId) {
            $drillDown = Encounter::with(['patient.user', 'doctor'])
                ->whereBetween('encounters.created_at', [$from, $to])
                ->join('doctor_queues', 'encounters.queue_id', '=', 'doctor_queues.id')
                ->where('doctor_queues.clinic_id', $clinicId)
                ->select('encounters.*')
                ->orderByDesc('encounters.created_at')
                ->get()
                ->map(fn($e) => [
                    'patient'    => userfullname($e->patient->user_id),
                    'file_no'    => $e->patient->file_no ?? '',
                    'patient_id' => $e->patient_id,
                    'date'       => $e->created_at->format('Y-m-d H:i'),
                    'doctor'     => $e->doctor ? userfullname($e->doctor->id) : 'N/A',
                    'hmo'        => $e->patient->hmo->name ?? 'Self/Private',
                    'status'     => $e->status ?? 'N/A',
                ]);
        }

        return response()->json([
            'summary'    => $summary,
            'drill_down' => $drillDown,
        ]);
    }

    // -----------------------------------------------------------------------
    // NEW: HMO Trends — daily series + totals, optional drill-down by HMO
    // -----------------------------------------------------------------------
    public function getHmoTrends(Request $request)
    {
        $from   = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $to     = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : now()->endOfDay();
        $hmoId  = $request->get('hmo_id');

        $totals = Patient::join('encounters', 'patients.id', '=', 'encounters.patient_id')
            ->whereBetween('encounters.created_at', [$from, $to])
            ->join('hmos', 'patients.hmo_id', '=', 'hmos.id')
            ->when($hmoId, fn($q) => $q->where('hmos.id', $hmoId))
            ->select('hmos.id as hmo_id', 'hmos.name as hmo_name', DB::raw('count(encounters.id) as total'), DB::raw('count(distinct patients.id) as unique_patients'))
            ->groupBy('hmos.id', 'hmos.name')
            ->orderByDesc('total')
            ->get();

        // Daily series for chart — group by date + HMO
        $daily = Patient::join('encounters', 'patients.id', '=', 'encounters.patient_id')
            ->whereBetween('encounters.created_at', [$from, $to])
            ->join('hmos', 'patients.hmo_id', '=', 'hmos.id')
            ->when($hmoId, fn($q) => $q->where('hmos.id', $hmoId))
            ->select('hmos.name as hmo_name', DB::raw('DATE(encounters.created_at) as day'), DB::raw('count(*) as total'))
            ->groupBy('hmos.name', DB::raw('DATE(encounters.created_at)'))
            ->orderBy('day')
            ->get();

        $drillDown = null;
        if ($hmoId) {
            $drillDown = Encounter::with(['patient.user'])
                ->join('patients', 'encounters.patient_id', '=', 'patients.id')
                ->where('patients.hmo_id', $hmoId)
                ->whereBetween('encounters.created_at', [$from, $to])
                ->select('encounters.*')
                ->orderByDesc('encounters.created_at')
                ->get()
                ->map(fn($e) => [
                    'patient'    => userfullname($e->patient->user_id),
                    'file_no'    => $e->patient->file_no ?? '',
                    'patient_id' => $e->patient_id,
                    'date'       => $e->created_at->format('Y-m-d H:i'),
                ]);
        }

        return response()->json([
            'totals'     => $totals,
            'daily'      => $daily,
            'drill_down' => $drillDown,
        ]);
    }

    // -----------------------------------------------------------------------
    // NEW: Maternity Report — sub_category determines which dataset is returned
    // -----------------------------------------------------------------------
    public function getMaternityReport(Request $request)
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $to   = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : now()->endOfDay();
        $sub  = $request->get('sub_category', 'summary');

        // Summary counts (always returned)
        $enrollments   = MaternityEnrollment::whereBetween('created_at', [$from, $to])->count();
        $activeEnroll  = MaternityEnrollment::where('status', 'active')->count();
        $highRisk      = MaternityEnrollment::where('risk_level', 'high')->where('status', 'active')->count();
        $ancVisits     = AncVisit::whereBetween('visit_date', [$from, $to])->count();
        $deliveries    = DeliveryRecord::whereBetween('delivery_date', [$from, $to])->count();
        $liveBirths    = MaternityBaby::where('is_still_birth', 0)->whereBetween('created_at', [$from, $to])->count();
        $stillbirths   = MaternityBaby::where('is_still_birth', 1)->whereBetween('created_at', [$from, $to])->count();
        $neonatalDeath = MaternityBaby::where('status', 'deceased')->where('is_still_birth', 0)->whereBetween('deceased_at', [$from, $to])->count();
        $postnatal     = PostnatalVisit::whereBetween('visit_date', [$from, $to])->count();

        $summary = compact('enrollments', 'activeEnroll', 'highRisk', 'ancVisits', 'deliveries', 'liveBirths', 'stillbirths', 'neonatalDeath', 'postnatal');

        $data = [];
        switch ($sub) {
            case 'enrollments':
                $data = MaternityEnrollment::with(['patient.user'])
                    ->whereBetween('enrollment_date', [$from, $to])
                    ->get()
                    ->map(fn($r) => [
                        'patient'    => userfullname($r->patient->user_id),
                        'file_no'    => $r->patient->file_no ?? '',
                        'patient_id' => $r->patient_id,
                        'date'       => $r->enrollment_date ? Carbon::parse($r->enrollment_date)->format('Y-m-d') : 'N/A',
                        'edd'        => $r->edd ? Carbon::parse($r->edd)->format('Y-m-d') : 'N/A',
                        'risk'       => ucfirst($r->risk_level ?? 'normal'),
                        'status'     => ucfirst($r->status ?? 'active'),
                    ]);
                break;

            case 'anc_visits':
                $data = AncVisit::with(['enrollment.patient.user'])
                    ->whereBetween('visit_date', [$from, $to])
                    ->get()
                    ->map(fn($r) => [
                        'patient'    => $r->enrollment ? userfullname($r->enrollment->patient->user_id) : 'N/A',
                        'file_no'    => $r->enrollment ? ($r->enrollment->patient->file_no ?? '') : '',
                        'date'       => Carbon::parse($r->visit_date)->format('Y-m-d'),
                        'weight_kg'  => $r->weight_kg,
                        'bp'         => ($r->blood_pressure_systolic ?? '-') . '/' . ($r->blood_pressure_diastolic ?? '-'),
                        'fundal_ht'  => $r->fundal_height_cm ?? 'N/A',
                        'gestational_age' => $r->gestational_age_weeks ? $r->gestational_age_weeks . ' wks' : 'N/A',
                    ]);
                break;

            case 'deliveries':
                $data = DeliveryRecord::with(['patient.user'])
                    ->whereBetween('delivery_date', [$from, $to])
                    ->get()
                    ->map(fn($r) => [
                        'mother'      => userfullname($r->patient->user_id),
                        'file_no'     => $r->patient->file_no ?? '',
                        'patient_id'  => $r->patient_id,
                        'date'        => $r->delivery_date ? Carbon::parse($r->delivery_date)->format('Y-m-d') : 'N/A',
                        'type'        => strtoupper(str_replace('_', ' ', $r->type_of_delivery)),
                        'babies'      => $r->number_of_babies,
                        'blood_loss'  => $r->blood_loss_ml ? $r->blood_loss_ml . ' ml' : 'N/A',
                        'complications' => $r->complications ?? 'None',
                    ]);
                break;

            case 'babies':
                $data = MaternityBaby::with(['enrollment.patient.user', 'patient.user'])
                    ->whereBetween('created_at', [$from, $to])
                    ->get()
                    ->map(fn($r) => [
                        'mother'      => $r->enrollment ? userfullname($r->enrollment->patient->user_id) : 'N/A',
                        'baby'        => $r->patient ? userfullname($r->patient->user_id) : ('Baby #' . $r->birth_order),
                        'sex'         => ucfirst($r->sex),
                        'weight_kg'   => $r->birth_weight_kg ?? 'N/A',
                        'still_birth' => $r->is_still_birth ? 'Yes' : 'No',
                        'status'      => ucfirst($r->status),
                        'cause_of_death' => $r->status === 'deceased' ? ($r->cause_of_death ?? 'N/A') : null,
                        'deceased_at' => $r->deceased_at ? Carbon::parse($r->deceased_at)->format('Y-m-d') : null,
                    ]);
                break;

            case 'postnatal':
                $data = PostnatalVisit::with(['enrollment.patient.user'])
                    ->whereBetween('visit_date', [$from, $to])
                    ->get()
                    ->map(fn($r) => [
                        'patient'          => $r->enrollment ? userfullname($r->enrollment->patient->user_id) : 'N/A',
                        'date'             => Carbon::parse($r->visit_date)->format('Y-m-d'),
                        'mother_condition' => $r->general_condition ?? 'N/A',
                        'baby_condition'   => $r->baby_general_condition ?? 'N/A',
                    ]);
                break;

            default: // summary only
                break;
        }

        return response()->json(compact('summary', 'data'));
    }

    // -----------------------------------------------------------------------
    // NEW: Referrals — internal/external breakdown with conversion rate
    // -----------------------------------------------------------------------
    public function getReferrals(Request $request)
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $to   = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : now()->endOfDay();
        $type = $request->get('referral_type'); // internal | external | null (all)

        $q = SpecialistReferral::with(['patient.user', 'referringDoctor.user', 'targetClinic'])
            ->whereBetween('created_at', [$from, $to])
            ->when($type, fn($q) => $q->where('referral_type', $type));

        $all = $q->get();

        $summary = [
            'total'             => $all->count(),
            'internal'          => $all->where('referral_type', 'internal')->count(),
            'external'          => $all->where('referral_type', 'external')->count(),
            'booked'            => $all->where('status', 'booked')->count(),
            'completed'         => $all->where('status', 'completed')->count(),
            'pending'           => $all->where('status', 'pending')->count(),
            'declined_cancelled'=> $all->whereIn('status', ['declined', 'cancelled'])->count(),
            'internal_booked'   => $all->where('referral_type', 'internal')->where('status', 'booked')->count(),
            'internal_total'    => $all->where('referral_type', 'internal')->count(),
        ];
        $summary['conversion_rate'] = $summary['internal_total'] > 0
            ? round($summary['internal_booked'] / $summary['internal_total'] * 100, 1)
            : 0;

        $rows = $all->map(fn($r) => [
            'id'            => $r->id,
            'patient'       => userfullname($r->patient->user_id),
            'file_no'       => $r->patient->file_no ?? '',
            'patient_id'    => $r->patient_id,
            'type'          => ucfirst($r->referral_type),
            'from_doctor'   => $r->referringDoctor && $r->referringDoctor->user ? userfullname($r->referringDoctor->user->id) : 'N/A',
            'to'            => $r->referral_type === 'internal'
                ? ($r->targetClinic ? $r->targetClinic->name : 'N/A')
                : ($r->external_facility_name ?? 'N/A'),
            'to_doctor'     => $r->referral_type === 'external' ? ($r->external_doctor_name ?? 'N/A') : 'N/A',
            'reason'        => $r->reason,
            'urgency'       => ucfirst($r->urgency),
            'status'        => ucfirst($r->status),
            'booked'        => $r->appointment_id ? 'Yes' : 'No',
            'date'          => $r->created_at->format('Y-m-d H:i'),
        ]);

        return response()->json(compact('summary', 'rows'));
    }

    // -----------------------------------------------------------------------
    // NEW: Vaccinations — administered records + schedule status counts
    // -----------------------------------------------------------------------
    public function getVaccinations(Request $request)
    {
        $from    = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $to      = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : now()->endOfDay();
        $vaccine = $request->get('vaccine_name');

        // Administered doses
        $administered = ImmunizationRecord::with(['patient.user', 'administeredBy'])
            ->whereBetween('administered_at', [$from, $to])
            ->when($vaccine, fn($q) => $q->where('vaccine_name', $vaccine))
            ->get();

        $byVaccine = $administered->groupBy('vaccine_name')->map(fn($g) => [
            'vaccine_name' => $g->first()->vaccine_name,
            'total_doses'  => $g->count(),
            'patients'     => $g->pluck('patient_id')->unique()->count(),
        ])->values();

        $rows = $administered->map(fn($r) => [
            'patient'       => userfullname($r->patient->user_id),
            'file_no'       => $r->patient->file_no ?? '',
            'patient_id'    => $r->patient_id,
            'vaccine'       => $r->vaccine_name,
            'dose_no'       => $r->dose_number,
            'route'         => $r->route,
            'date'          => Carbon::parse($r->administered_at)->format('Y-m-d H:i'),
            'nurse'         => $r->administeredBy ? userfullname($r->administeredBy->id) : 'N/A',
            'batch'         => $r->batch_number ?? 'N/A',
            'next_due'      => $r->next_due_date ? Carbon::parse($r->next_due_date)->format('Y-m-d') : 'N/A',
        ]);

        // Schedule status summary (current snapshot, not date-filtered)
        $scheduleStats = PatientImmunizationSchedule::select('status', DB::raw('count(*) as count'))
            ->when($vaccine, fn($q) => $q->whereHas('scheduleItem', fn($sq) => $sq->where('vaccine_name', $vaccine)))
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json(compact('byVaccine', 'rows', 'scheduleStats'));
    }

    // -----------------------------------------------------------------------
    // NEW: Occupancy — per ward snapshot + avg LOS from admission_requests
    // -----------------------------------------------------------------------
    public function getOccupancy(Request $request)
    {
        $from   = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $to     = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : now()->endOfDay();
        $wardId = $request->get('ward_id');

        // Current occupancy per ward
        $wards = Ward::withCount([
            'beds as total_beds',
            'beds as occupied_beds' => fn($q) => $q->where('bed_status', 'occupied'),
            'beds as available_beds' => fn($q) => $q->where('bed_status', 'available'),
        ])
        ->where('is_active', 1)
        ->when($wardId, fn($q) => $q->where('id', $wardId))
        ->get()
        ->map(fn($w) => [
            'ward_id'       => $w->id,
            'ward_name'     => $w->name,
            'type'          => ucfirst($w->type),
            'capacity'      => $w->capacity,
            'total_beds'    => $w->total_beds,
            'occupied'      => $w->occupied_beds,
            'available'     => $w->available_beds,
            'occupancy_pct' => $w->total_beds > 0 ? round($w->occupied_beds / $w->total_beds * 100, 1) : 0,
        ]);

        // Average LOS from discharged admissions in date range
        $avgLos = AdmissionRequest::where('discharged', 1)
            ->whereBetween('discharge_date', [$from, $to])
            ->whereNotNull('created_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, discharge_date)) / 24 as avg_days')
            ->value('avg_days');

        // Patients currently admitted (per ward)
        $occupiedBeds = Bed::with(['wardRelation', 'occupant'])
            ->where('bed_status', 'occupied')
            ->when($wardId, fn($q) => $q->where('ward_id', $wardId))
            ->get();

        // Load active admission requests for occupied beds
        $occupantIds = $occupiedBeds->pluck('occupant_id')->filter()->unique()->values();
        $activeAdmissions = AdmissionRequest::where('discharged', 0)
            ->whereIn('patient_id', $occupantIds)
            ->get()
            ->keyBy('patient_id');

        $currentPatients = $occupiedBeds->map(function ($b) use ($activeAdmissions) {
                $adm = $b->occupant_id ? ($activeAdmissions[$b->occupant_id] ?? null) : null;
                $admittedAt = $adm ? $adm->created_at : $b->updated_at;
                return [
                    'patient'    => $b->occupant ? userfullname($b->occupant->user_id) : 'N/A',
                    'file_no'    => $b->occupant->file_no ?? '',
                    'patient_id' => $b->occupant_id,
                    'ward'       => $b->wardRelation ? $b->wardRelation->name : ($b->ward ?? 'N/A'),
                    'bed'        => $b->name,
                    'admitted_at'=> $admittedAt ? Carbon::parse($admittedAt)->format('Y-m-d H:i') : 'N/A',
                    'days'       => $admittedAt ? (int) Carbon::parse($admittedAt)->diffInDays(now()) : 0,
                ];
            });

        return response()->json([
            'wards'           => $wards,
            'avg_los_days'    => $avgLos ? round($avgLos, 1) : 0,
            'current_patients'=> $currentPatients,
        ]);
    }
}
