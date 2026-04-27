<?php

namespace App\Http\Controllers;

use App\Models\AdmissionRequest;
use App\Models\DeathRecord;
use App\Models\DeliveryRecord;
use App\Models\ImmunizationRecord;
use App\Models\Encounter;
use App\Models\Hmo;
use App\Models\ImagingServiceRequest;
use App\Models\LabServiceRequest;
use App\Models\Patient;
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
        $to = $request->filled('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : now()->endOfDay();

        $maternityStats = $this->getMaternityStats($from, $to);
        $stats = [
            'unit_visits' => $this->getVisitsByUnit($from, $to),
            'hmo_trends' => $this->getHmoTrends($from, $to),
            'malaria' => $this->getCountByDiagnosisKeyword('malaria', $from, $to),
            'total_births' => $maternityStats['births'],
            'maternity' => $maternityStats,
            'deaths' => DeathRecord::whereBetween('date_of_death', [$from, $to])->count() + 
                      \App\Models\MaternityBaby::where('status', 'deceased')->whereBetween('created_at', [$from, $to])->count(),
            'surgeries' => \App\Models\Procedure::where('procedure_status', 'completed')
                ->whereBetween('actual_end_time', [$from, $to])
                ->count(),
            'vaccinations' => \App\Models\ImmunizationRecord::whereBetween('administered_at', [$from, $to])->count(),
            'referrals' => SpecialistReferral::whereBetween('created_at', [$from, $to])->count(),
            'ward_occupancy' => $this->getWardOccupancy(),
            'total_admissions' => \App\Models\AdmissionRequest::whereBetween('created_at', [$from, $to])->count(),
            'discharge_stats' => \App\Models\AdmissionRequest::whereNotNull('discharge_reason')
                ->whereBetween('discharge_date', [$from, $to])
                ->select('discharge_reason', DB::raw('count(*) as count'))
                ->groupBy('discharge_reason')
                ->get(),
        ];

        return response()->json($stats);
    }

    /**
     * Search Diagnosis with Keyword
     */
    public function searchDiagnosis(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string|min:2',
            'date_from' => 'required|date',
            'date_to' => 'required|date',
        ]);

        $keyword = $request->keyword;
        $dateFrom = Carbon::parse($request->date_from)->startOfDay();
        $dateTo = Carbon::parse($request->date_to)->endOfDay();

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
                            'total_occurrences' => 0,
                            'unique_patients' => 0,
                            'patient_ids' => [],
                            'encounters' => []
                        ];
                    }
                    $grouped[$name]['total_occurrences']++;
                    if (!in_array($e->patient_id, $grouped[$name]['patient_ids'])) {
                        $grouped[$name]['unique_patients']++;
                        $grouped[$name]['patient_ids'][] = $e->patient_id;
                    }

                    $grouped[$name]['encounters'][] = [
                        'id' => $e->id,
                        'patient_name' => userfullname($e->patient->user_id),
                        'patient_id' => $e->patient_id,
                        'date' => $e->created_at->format('M d, Y H:i'),
                        'doctor' => $e->doctor ? userfullname($e->doctor->id) : 'N/A',
                        'query' => $query,
                        'status' => $status
                    ];
                }
            }
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

        // Procedures are often service requests
        $procedures = \App\Models\ProductOrServiceRequest::with('service')
            ->where('encounter_id', $encounterId)
            ->whereHas('service', function ($q) {
                $q->whereHas('category', function ($cat) {
                    $cat->where('category_name', 'like', '%procedure%');
                });
            })->get();

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

    private function getHmoTrends($from, $to)
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

    private function getCountByServiceCategory($categoryKeyword, $from, $to)
    {
        return \App\Models\ProductOrServiceRequest::whereBetween('created_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->whereHas('service', function ($q) use ($categoryKeyword) {
                $q->whereHas('category', function ($cat) use ($categoryKeyword) {
                    $cat->where('category_name', 'like', "%{$categoryKeyword}%");
                });
            })->count();
    }

    public function getDrillDownDetails(Request $request)
    {
        $category = $request->get('category');
        $from = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : now()->startOfMonth()->startOfDay();
        $to = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : now()->endOfDay();

        $data = [];
        switch ($category) {
            case 'mortality':
                $data = DeathRecord::with(['patient.user', 'doctor'])
                    ->whereBetween('date_of_death', [$from, $to])
                    ->get()
                    ->map(function ($r) {
                        return [
                            'patient' => userfullname($r->patient->user_id),
                            'date' => $r->date_of_death->format('Y-m-d') . ' ' . $r->time_of_death,
                            'cause' => $r->cause_of_death_primary,
                            'doctor' => $r->doctor ? userfullname($r->doctor->id) : 'N/A'
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
                                'date' => $r->visit_date->format('Y-m-d'),
                                'weight' => $r->weight_kg . 'kg',
                                'bp' => $r->bp_sys . '/' . $r->bp_dia
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
                                'date' => $r->visit_date->format('Y-m-d'),
                                'mother_condition' => $r->mother_general_condition,
                                'baby_condition' => $r->baby_general_condition
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
                $data = Procedure::with(['patient.user', 'requestedByUser', 'service'])
                    ->where('procedure_status', 'completed')
                    ->whereBetween('actual_end_time', [$from, $to])
                    ->get()
                    ->map(function ($r) {
                        return [
                            'patient' => userfullname($r->patient->user_id),
                            'date' => $r->actual_end_time->format('Y-m-d H:i'),
                            'procedure' => $r->service ? $r->service->name : 'N/A',
                            'doctor' => $r->requestedByUser ? userfullname($r->requestedByUser->id) : 'N/A'
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
                            'date' => $r->created_at->format('Y-m-d H:i'),
                            'from_doctor' => $r->referringDoctor && $r->referringDoctor->user ? userfullname($r->referringDoctor->user->id) : 'N/A',
                            'to_clinic' => $r->targetClinic ? $r->targetClinic->name : ($r->external_facility_name ?? 'N/A')
                        ];
                    });
                break;
            case 'occupancy':
                $data = Bed::with(['wardRelation', 'occupant.user'])
                    ->where('bed_status', 'occupied')
                    ->get()
                    ->map(function ($r) {
                        return [
                            'patient' => $r->occupant ? userfullname($r->occupant->user_id) : 'N/A',
                            'ward' => $r->wardRelation ? $r->wardRelation->name : ($r->ward ?? 'N/A'),
                            'bed' => $r->name,
                            'since' => $r->updated_at->format('Y-m-d H:i')
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
}
