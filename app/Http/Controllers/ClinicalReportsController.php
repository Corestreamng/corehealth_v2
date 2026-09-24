<?php

namespace App\Http\Controllers;

use App\Models\AdmissionRequest;
use App\Models\AncVisit;
use App\Models\Bed;
use App\Models\Clinic;
use App\Models\DeathRecord;
use App\Models\DeliveryRecord;
use App\Models\DoctorQueue;
use App\Models\Encounter;
use App\Models\Hmo;
use App\Models\ImagingServiceRequest;
use App\Models\ImmunizationRecord;
use App\Models\LabServiceRequest;
use App\Models\MaternityBaby;
use App\Models\MaternityEnrollment;
use App\Models\Patient;
use App\Models\PatientImmunizationSchedule;
use App\Models\PostnatalVisit;
use App\Models\Procedure;
use App\Models\SpecialistReferral;
use App\Models\User;
use App\Models\Ward;
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

        $totalEncounters = Encounter::whereBetween('created_at', [$from, $to])->count();
        $uniquePatients = Encounter::whereBetween('created_at', [$from, $to])->distinct('patient_id')->count('patient_id');
        $totalAdmissions = AdmissionRequest::whereBetween('created_at', [$from, $to])->count();
        $totalSurgeries = Procedure::where('procedure_status', 'completed')
                            ->whereBetween('actual_end_time', [$from, $to])
                            ->whereHas('procedureDefinition', fn ($q) => $q->where('is_surgical', 1))
                            ->count();
        $totalDeaths = DeathRecord::whereBetween('date_of_death', [$from, $to])->count();
        $totalBirths = (int) DeliveryRecord::whereBetween('delivery_date', [$from, $to])->sum('number_of_babies');
        $totalLab = LabServiceRequest::whereBetween('created_at', [$from, $to])->count();
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
            'total_encounters' => $totalEncounters,
            'unique_patients' => $uniquePatients,
            'total_admissions' => $totalAdmissions,
            'total_surgeries' => $totalSurgeries,
            'total_deaths' => $totalDeaths,
            'total_births' => $totalBirths,
            'total_lab' => $totalLab,
            'total_imaging' => $totalImaging,
            'encounters_by_day' => $encountersByDay,
            'admissions_by_day' => $admissionsByDay,
        ]);
    }

    /**
     * Normalize a diagnosis item (from JSON array, string, or notes) into a standard representation
     */
    private function normalizeDiagnosisItem($item, $defaultComment1 = 'N/A', $defaultComment2 = 'N/A')
    {
        $code = '';
        $name = '';
        $query = $defaultComment1 ?: 'N/A';
        $status = $defaultComment2 ?: 'N/A';

        if (is_array($item)) {
            $code = trim($item['code'] ?? '');
            $name = trim($item['name'] ?? ($item['value'] ?? ($item['display'] ?? '')));
            if (!empty($item['comment_1']) && $item['comment_1'] !== 'NA') {
                $query = $item['comment_1'];
            }
            if (!empty($item['comment_2']) && $item['comment_2'] !== 'NA') {
                $status = $item['comment_2'];
            }
        } else {
            $name = trim((string) $item);
            if (preg_match('/^([A-Za-z][0-9]{2,3}(?:\.[0-9]+)?)\s*[-:]\s*(.+)$/i', $name, $m)) {
                $code = trim($m[1]);
                $name = trim($m[2]);
            }
        }

        $name = trim(preg_replace('/^custom:\s*/i', '', $name));
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $name = rtrim($name, '.');

        if (empty($code) && preg_match('/^([A-Za-z][0-9]{2,3}(?:\.[0-9]+)?)\s*[-:]\s*(.+)$/i', $name, $m)) {
            $code = trim($m[1]);
            $name = trim($m[2]);
        }

        $isCustom = (empty($code) || strtoupper($code) === 'CUSTOM');
        if (!$isCustom) {
            $cleanCode = strtoupper(trim($code));
            $groupKey = 'ICD_' . $cleanCode;
            $displayCode = $cleanCode;
            $displayName = $name ?: $cleanCode;
        } else {
            $groupKey = 'CUSTOM_' . strtolower($name);
            $displayCode = 'CUSTOM';
            $displayName = $name !== '' ? mb_convert_case($name, MB_CASE_TITLE, 'UTF-8') : 'Unknown';
        }

        return [
            'group_key' => $groupKey,
            'code' => $displayCode,
            'name' => $displayName,
            'raw_name' => $name,
            'query' => $query,
            'status' => $status,
        ];
    }

    /**
     * Format full name from an in-memory User model (or cached User ID) with 0 redundant queries
     */
    protected function formatPersonName($userOrId): string
    {
        if (!$userOrId) {
            return 'N/A';
        }

        if ($userOrId instanceof User) {
            $othername = $userOrId->othername ? ' ' . $userOrId->othername : '';

            return trim(ucwords(trim($userOrId->surname . ' ' . $userOrId->firstname . $othername))) ?: 'N/A';
        }

        static $userCache = [];
        $userId = is_numeric($userOrId) ? (int) $userOrId : null;
        if (!$userId) {
            return 'N/A';
        }

        if (!isset($userCache[$userId])) {
            $user = User::select(['id', 'surname', 'firstname', 'othername'])->find($userId);
            $userCache[$userId] = $user ? $this->formatPersonName($user) : 'Unknown';
        }

        return $userCache[$userId];
    }

    /**
     * Search Diagnosis with Keyword (High-Performance Column-Projected Query)
     */
    public function searchDiagnosis(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string|min:2',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $keyword = trim($request->keyword);
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : now()->endOfDay();

        // Optimized query: select projected columns & eager load only required fields
        $query = Encounter::select([
            'id',
            'patient_id',
            'doctor_id',
            'queue_id',
            'reasons_for_encounter',
            'reasons_for_encounter_comment_1',
            'reasons_for_encounter_comment_2',
            'notes',
            'created_at',
        ])->with([
            'patient:id,user_id,file_no,hmo_id',
            'patient.user:id,surname,firstname,othername',
            'patient.hmo:id,name',
            'doctor:id,surname,firstname,othername',
            'queue:id,clinic_id',
            'queue.clinic:id,name',
        ])->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('reasons_for_encounter', 'like', "%{$keyword}%")
                  ->orWhere('notes', 'like', "%{$keyword}%");
            });
        } else {
            $query->where(function ($q) {
                $q->whereNotNull('reasons_for_encounter')
                  ->where('reasons_for_encounter', '!=', '')
                  ->orWhereNotNull('notes');
            });
        }

        // Avoid correlated subqueries by using indexed whereIn on foreign keys
        if ($request->filled('hmo_id')) {
            $patientIds = Patient::where('hmo_id', $request->hmo_id)->pluck('id');
            $query->whereIn('patient_id', $patientIds);
        }

        if ($request->filled('clinic_id')) {
            $queueIds = DoctorQueue::where('clinic_id', $request->clinic_id)->pluck('id');
            $query->whereIn('queue_id', $queueIds);
        }

        if ($request->filled('ward_id')) {
            $admissionEncIds = AdmissionRequest::where('ward_id', $request->ward_id)
                ->orWhereHas('bed', fn ($bq) => $bq->where('ward_id', $request->ward_id))
                ->whereNotNull('encounter_id')
                ->pluck('encounter_id');
            $query->whereIn('id', $admissionEncIds);
        }

        $encounters = $query->orderByDesc('created_at')->get();

        $grouped = [];
        foreach ($encounters as $e) {
            $rawReasons = !empty($e->reasons_for_encounter) ? json_decode($e->reasons_for_encounter, true) : [];
            if (!is_array($rawReasons)) {
                if (is_string($e->reasons_for_encounter) && trim($e->reasons_for_encounter) !== '') {
                    $rawReasons = array_filter(array_map('trim', explode(',', $e->reasons_for_encounter)));
                } else {
                    $rawReasons = [];
                }
            }

            $matchedInReasons = false;
            foreach ($rawReasons as $item) {
                $norm = $this->normalizeDiagnosisItem($item, $e->reasons_for_encounter_comment_1, $e->reasons_for_encounter_comment_2);

                if (stripos($norm['name'], $keyword) !== false || stripos($norm['raw_name'], $keyword) !== false || ($norm['code'] !== 'CUSTOM' && stripos($norm['code'], $keyword) !== false)) {
                    $matchedInReasons = true;
                    $gk = $norm['group_key'];

                    if (!isset($grouped[$gk])) {
                        $grouped[$gk] = [
                            'diagnosis' => $norm['name'],
                            'icd_code' => $norm['code'],
                            'total_encounters' => 0,
                            'unique_patients' => 0,
                            'encounter_ids' => [],
                            'patient_ids' => [],
                            'statuses' => [],
                            'queries' => [],
                            'encounters' => [],
                        ];
                    }

                    if (!in_array($e->id, $grouped[$gk]['encounter_ids'])) {
                        $grouped[$gk]['encounter_ids'][] = $e->id;
                        $grouped[$gk]['total_encounters']++;

                        if (!in_array($e->patient_id, $grouped[$gk]['patient_ids'])) {
                            $grouped[$gk]['patient_ids'][] = $e->patient_id;
                            $grouped[$gk]['unique_patients']++;
                        }

                        $status = $norm['status'];
                        if ($status !== 'N/A' && $status !== 'NA' && !in_array($status, $grouped[$gk]['statuses'])) {
                            $grouped[$gk]['statuses'][] = $status;
                        }

                        $query = $norm['query'];
                        if ($query !== 'N/A' && $query !== 'NA' && !in_array($query, $grouped[$gk]['queries'])) {
                            $grouped[$gk]['queries'][] = $query;
                        }

                        $patientFormatted = $this->formatPersonName($e->patient?->user);
                        $doctorFormatted = $this->formatPersonName($e->doctor);

                        $grouped[$gk]['encounters'][] = [
                            'id' => $e->id,
                            'patient' => $patientFormatted,
                            'patient_name' => $patientFormatted,
                            'patient_id' => $e->patient_id,
                            'file_no' => $e->patient->file_no ?? '',
                            'date' => $e->created_at->format('Y-m-d H:i'),
                            'doctor' => $doctorFormatted,
                            'query_type' => $query,
                            'status' => $status,
                            'icd_code' => $norm['code'],
                        ];
                    }
                }
            }

            // If no match in reasons_for_encounter, check notes for keyword
            // Optimization: check stripos on raw notes before calling strip_tags
            if (!$matchedInReasons && !empty($e->notes) && stripos($e->notes, $keyword) !== false) {
                if (stripos(strip_tags($e->notes), $keyword) !== false) {
                    $norm = $this->normalizeDiagnosisItem($keyword, $e->reasons_for_encounter_comment_1, $e->reasons_for_encounter_comment_2);
                    $gk = $norm['group_key'];

                    if (!isset($grouped[$gk])) {
                        $grouped[$gk] = [
                            'diagnosis' => $norm['name'],
                            'icd_code' => $norm['code'],
                            'total_encounters' => 0,
                            'unique_patients' => 0,
                            'encounter_ids' => [],
                            'patient_ids' => [],
                            'statuses' => [],
                            'queries' => [],
                            'encounters' => [],
                        ];
                    }

                    if (!in_array($e->id, $grouped[$gk]['encounter_ids'])) {
                        $grouped[$gk]['encounter_ids'][] = $e->id;
                        $grouped[$gk]['total_encounters']++;

                        if (!in_array($e->patient_id, $grouped[$gk]['patient_ids'])) {
                            $grouped[$gk]['patient_ids'][] = $e->patient_id;
                            $grouped[$gk]['unique_patients']++;
                        }

                        $status = $norm['status'];
                        if ($status !== 'N/A' && $status !== 'NA' && !in_array($status, $grouped[$gk]['statuses'])) {
                            $grouped[$gk]['statuses'][] = $status;
                        }

                        $query = $norm['query'];
                        if ($query !== 'N/A' && $query !== 'NA' && !in_array($query, $grouped[$gk]['queries'])) {
                            $grouped[$gk]['queries'][] = $query;
                        }

                        $patientFormatted = $this->formatPersonName($e->patient?->user);
                        $doctorFormatted = $this->formatPersonName($e->doctor);

                        $grouped[$gk]['encounters'][] = [
                            'id' => $e->id,
                            'patient' => $patientFormatted,
                            'patient_name' => $patientFormatted,
                            'patient_id' => $e->patient_id,
                            'file_no' => $e->patient->file_no ?? '',
                            'date' => $e->created_at->format('Y-m-d H:i'),
                            'doctor' => $doctorFormatted,
                            'query_type' => $query,
                            'status' => $status,
                            'icd_code' => $norm['code'],
                        ];
                    }
                }
            }
        }

        // Sort by unique patients desc, then total encounters desc
        usort($grouped, fn ($a, $b) => $b['unique_patients'] <=> $a['unique_patients'] ?: $b['total_encounters'] <=> $a['total_encounters']);
        foreach ($grouped as &$g) {
            unset($g['patient_ids'], $g['encounter_ids']);
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
            'patient.hmo',
            'doctor',
            'queue.clinic',
            'labRequests.service',
            'imagingRequests.service',
            'productRequests.product',
        ])->findOrFail($encounterId);

        $procedures = Procedure::with(['procedureDefinition', 'service'])
            ->where('encounter_id', $encounterId)
            ->get();

        $patientUser = $encounter->patient?->user;
        $patientName = $patientUser
            ? trim($patientUser->surname . ' ' . $patientUser->firstname . ($patientUser->othername ? ' ' . $patientUser->othername : ''))
            : 'Unknown Patient';

        $doctorName = $encounter->doctor
            ? trim($encounter->doctor->surname . ' ' . $encounter->doctor->firstname . ($encounter->doctor->othername ? ' ' . $encounter->doctor->othername : ''))
            : 'N/A';

        // Format diagnosis / reasons for encounter
        $reasonsText = 'N/A';
        if (!empty($encounter->reasons_for_encounter)) {
            $rawReasons = json_decode($encounter->reasons_for_encounter, true);
            if (is_array($rawReasons)) {
                $diagList = [];
                foreach ($rawReasons as $r) {
                    if (is_array($r)) {
                        $diagList[] = $r['name'] ?? ($r['diagnosis'] ?? null);
                    } elseif (is_string($r)) {
                        $diagList[] = $r;
                    }
                }
                $filtered = array_filter($diagList);
                $reasonsText = !empty($filtered) ? implode(', ', $filtered) : 'N/A';
            } elseif (is_string($encounter->reasons_for_encounter)) {
                $reasonsText = $encounter->reasons_for_encounter;
            }
        }

        // Map Prescriptions
        $prescriptions = $encounter->productRequests->map(function ($rx) {
            $name = $rx->product?->product_name ?? $rx->free_form_name ?? ('Drug #' . $rx->id);
            $statusCode = (int) $rx->status;
            $statusMap = [
                0 => ['label' => 'Dismissed', 'badge' => 'bg-secondary text-white'],
                1 => ['label' => 'Unbilled', 'badge' => 'bg-warning text-dark'],
                2 => ['label' => 'Ready to Dispense', 'badge' => 'bg-info text-white'],
                3 => ['label' => 'Dispensed', 'badge' => 'bg-success text-white'],
                4 => ['label' => 'Returned', 'badge' => 'bg-danger text-white'],
            ];
            $statusInfo = $statusMap[$statusCode] ?? ['label' => 'Status #' . $statusCode, 'badge' => 'bg-secondary text-white'];

            $rxData = $rx->toArray();
            $rxData['item_name'] = $name;
            $rxData['status_label'] = $statusInfo['label'];
            $rxData['status_badge'] = $statusInfo['badge'];
            $rxData['dose_formatted'] = $rx->dose ?: 'N/A';
            $rxData['qty_formatted'] = $rx->qty ?: 1;

            return $rxData;
        });

        // Map Labs
        $labs = $encounter->labRequests->map(function ($lab) {
            $name = $lab->service?->service_name ?? $lab->free_form_name ?? ('Test #' . $lab->id);
            $statusCode = (int) $lab->status;
            $statusMap = [
                0 => ['label' => 'Dismissed', 'badge' => 'bg-secondary text-white'],
                1 => ['label' => 'Awaiting Billing', 'badge' => 'bg-warning text-dark'],
                2 => ['label' => 'Awaiting Sample', 'badge' => 'bg-info text-white'],
                3 => ['label' => 'Awaiting Results', 'badge' => 'bg-primary text-white'],
                4 => ['label' => 'Completed', 'badge' => 'bg-success text-white'],
                5 => ['label' => 'Pending Approval', 'badge' => 'bg-dark text-white'],
                6 => ['label' => 'Rejected', 'badge' => 'bg-danger text-white'],
            ];
            $statusInfo = $statusMap[$statusCode] ?? ['label' => 'Status #' . $statusCode, 'badge' => 'bg-secondary text-white'];

            $labData = $lab->toArray();
            $labData['item_name'] = $name;
            $labData['status_label'] = $statusInfo['label'];
            $labData['status_badge'] = $statusInfo['badge'];
            $labData['result_display'] = !empty($lab->result) ? $lab->result : 'Pending';

            return $labData;
        });

        // Map Imaging
        $imaging = $encounter->imagingRequests->map(function ($img) {
            $name = $img->service?->service_name ?? $img->free_form_name ?? ('Investigation #' . $img->id);
            $statusCode = (int) $img->status;
            $statusMap = [
                0 => ['label' => 'Dismissed', 'badge' => 'bg-secondary text-white'],
                1 => ['label' => 'Awaiting Billing', 'badge' => 'bg-warning text-dark'],
                2 => ['label' => 'In Progress / Scanned', 'badge' => 'bg-info text-white'],
                3 => ['label' => 'Results Entered', 'badge' => 'bg-primary text-white'],
                4 => ['label' => 'Completed', 'badge' => 'bg-success text-white'],
                5 => ['label' => 'Pending Approval', 'badge' => 'bg-dark text-white'],
                6 => ['label' => 'Rejected', 'badge' => 'bg-danger text-white'],
            ];
            $statusInfo = $statusMap[$statusCode] ?? ['label' => 'Status #' . $statusCode, 'badge' => 'bg-secondary text-white'];

            $imgData = $img->toArray();
            $imgData['item_name'] = $name;
            $imgData['status_label'] = $statusInfo['label'];
            $imgData['status_badge'] = $statusInfo['badge'];
            $imgData['result_display'] = !empty($img->result) ? $img->result : 'Pending';

            return $imgData;
        });

        // Map Procedures
        $procList = $procedures->map(function ($proc) {
            $name = $proc->procedureDefinition?->name ?? $proc->service?->service_name ?? $proc->free_form_name ?? ('Procedure #' . $proc->id);
            $procStatus = $proc->procedure_status ?? ($proc->status ? 'completed' : 'requested');
            $statusMap = [
                'requested' => ['label' => 'Requested', 'badge' => 'bg-warning text-dark'],
                'scheduled' => ['label' => 'Scheduled', 'badge' => 'bg-info text-white'],
                'in_progress' => ['label' => 'In Progress', 'badge' => 'bg-primary text-white'],
                'completed' => ['label' => 'Completed', 'badge' => 'bg-success text-white'],
                'cancelled' => ['label' => 'Cancelled', 'badge' => 'bg-secondary text-white'],
            ];
            $statusInfo = $statusMap[$procStatus] ?? [
                'label' => ucfirst(str_replace('_', ' ', $procStatus)),
                'badge' => 'bg-secondary text-white',
            ];

            $pData = $proc->toArray();
            $pData['item_name'] = $name;
            $pData['status_label'] = $statusInfo['label'];
            $pData['status_badge'] = $statusInfo['badge'];
            $pData['notes_display'] = $proc->outcome_notes ?? $proc->post_notes ?? $proc->pre_notes ?? 'N/A';

            return $pData;
        });

        return response()->json([
            'notes' => $encounter->notes,
            'labs' => $labs,
            'imaging' => $imaging,
            'prescriptions' => $prescriptions,
            'procedures' => $procList,
            'encounter' => [
                'id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'patient_name' => $patientName,
                'file_no' => $encounter->patient?->file_no ?? 'N/A',
                'doctor_name' => $doctorName,
                'clinic_name' => $encounter->queue?->clinic?->name ?? 'N/A',
                'hmo_name' => $encounter->patient?->hmo?->name ?? 'Cash / Private',
                'date' => $encounter->created_at ? $encounter->created_at->format('d M Y, h:i A') : 'N/A',
                'reasons' => $reasonsText,
            ],
        ]);
    }

    /**
     * Unified Export dispatcher for Clinical Reports
     */
    public function export(Request $request)
    {
        $tab = $request->get('tab', 'diagnosis');

        switch ($tab) {
            case 'diagnosis':
                return $this->exportDiagnosis($request);
            default:
                // Start from diagnosis search, fallback to diagnosis if unknown
                return $this->exportDiagnosis($request);
        }
    }

    /**
     * Export Diagnosis Search report as CSV with filters metadata, summary, and detailed encounters
     */
    public function exportDiagnosis(Request $request)
    {
        $keyword = trim($request->get('keyword', ''));
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : now()->endOfDay();

        $query = Encounter::select([
            'id',
            'patient_id',
            'doctor_id',
            'queue_id',
            'reasons_for_encounter',
            'reasons_for_encounter_comment_1',
            'reasons_for_encounter_comment_2',
            'notes',
            'created_at',
        ])->with([
            'patient:id,user_id,file_no,hmo_id',
            'patient.user:id,surname,firstname,othername',
            'patient.hmo:id,name',
            'doctor:id,surname,firstname,othername',
            'queue:id,clinic_id',
            'queue.clinic:id,name',
        ])->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('reasons_for_encounter', 'like', "%{$keyword}%")
                  ->orWhere('notes', 'like', "%{$keyword}%");
            });
        } else {
            $query->where(function ($q) {
                $q->whereNotNull('reasons_for_encounter')
                  ->where('reasons_for_encounter', '!=', '')
                  ->orWhereNotNull('notes');
            });
        }

        if ($request->filled('hmo_id')) {
            $patientIds = Patient::where('hmo_id', $request->hmo_id)->pluck('id');
            $query->whereIn('patient_id', $patientIds);
        }

        if ($request->filled('clinic_id')) {
            $queueIds = DoctorQueue::where('clinic_id', $request->clinic_id)->pluck('id');
            $query->whereIn('queue_id', $queueIds);
        }

        if ($request->filled('ward_id')) {
            $admissionEncIds = AdmissionRequest::where('ward_id', $request->ward_id)
                ->orWhereHas('bed', fn ($bq) => $bq->where('ward_id', $request->ward_id))
                ->whereNotNull('encounter_id')
                ->pluck('encounter_id');
            $query->whereIn('id', $admissionEncIds);
        }

        $encounters = $query->orderByDesc('created_at')->get();

        $grouped = [];
        $allEncounters = [];

        foreach ($encounters as $e) {
            $rawReasons = !empty($e->reasons_for_encounter) ? json_decode($e->reasons_for_encounter, true) : [];
            if (!is_array($rawReasons)) {
                if (is_string($e->reasons_for_encounter) && trim($e->reasons_for_encounter) !== '') {
                    $rawReasons = array_filter(array_map('trim', explode(',', $e->reasons_for_encounter)));
                } else {
                    $rawReasons = [];
                }
            }

            $matchedInReasons = false;
            foreach ($rawReasons as $item) {
                $norm = $this->normalizeDiagnosisItem($item, $e->reasons_for_encounter_comment_1, $e->reasons_for_encounter_comment_2);

                $matches = ($keyword === '') || (stripos($norm['name'], $keyword) !== false || stripos($norm['raw_name'], $keyword) !== false || ($norm['code'] !== 'CUSTOM' && stripos($norm['code'], $keyword) !== false));

                if ($matches) {
                    $matchedInReasons = true;
                    $gk = $norm['group_key'];

                    if (!isset($grouped[$gk])) {
                        $grouped[$gk] = [
                            'diagnosis' => $norm['name'],
                            'icd_code' => $norm['code'],
                            'total_encounters' => 0,
                            'unique_patients' => 0,
                            'encounter_ids' => [],
                            'patient_ids' => [],
                            'statuses' => [],
                            'queries' => [],
                        ];
                    }

                    if (!in_array($e->id, $grouped[$gk]['encounter_ids'])) {
                        $grouped[$gk]['encounter_ids'][] = $e->id;
                        $grouped[$gk]['total_encounters']++;

                        if (!in_array($e->patient_id, $grouped[$gk]['patient_ids'])) {
                            $grouped[$gk]['patient_ids'][] = $e->patient_id;
                            $grouped[$gk]['unique_patients']++;
                        }

                        $status = $norm['status'];
                        if ($status !== 'N/A' && $status !== 'NA' && !in_array($status, $grouped[$gk]['statuses'])) {
                            $grouped[$gk]['statuses'][] = $status;
                        }

                        $queryType = $norm['query'];
                        if ($queryType !== 'N/A' && $queryType !== 'NA' && !in_array($queryType, $grouped[$gk]['queries'])) {
                            $grouped[$gk]['queries'][] = $queryType;
                        }

                        $patientFormatted = $this->formatPersonName($e->patient?->user);
                        $doctorFormatted = $this->formatPersonName($e->doctor);

                        $allEncounters[] = [
                            'id' => $e->id,
                            'icd_code' => $norm['code'],
                            'diagnosis' => $norm['name'],
                            'patient_name' => $patientFormatted,
                            'file_no' => $e->patient->file_no ?? '',
                            'date' => $e->created_at->format('Y-m-d H:i'),
                            'doctor' => $doctorFormatted,
                            'query_type' => $queryType,
                            'status' => $status,
                            'hmo' => $e->patient && $e->patient->hmo ? $e->patient->hmo->name : 'Private / Self Pay',
                            'clinic' => $e->queue && $e->queue->clinic ? $e->queue->clinic->name : 'N/A',
                            'notes' => trim(preg_replace('/\s+/', ' ', strip_tags($e->notes ?? ''))),
                        ];
                    }
                }
            }

            if (!$matchedInReasons && $keyword !== '' && !empty($e->notes) && stripos($e->notes, $keyword) !== false) {
                if (stripos(strip_tags($e->notes), $keyword) !== false) {
                    $norm = $this->normalizeDiagnosisItem($keyword, $e->reasons_for_encounter_comment_1, $e->reasons_for_encounter_comment_2);
                    $gk = $norm['group_key'];

                    if (!isset($grouped[$gk])) {
                        $grouped[$gk] = [
                            'diagnosis' => $norm['name'],
                            'icd_code' => $norm['code'],
                            'total_encounters' => 0,
                            'unique_patients' => 0,
                            'encounter_ids' => [],
                            'patient_ids' => [],
                            'statuses' => [],
                            'queries' => [],
                        ];
                    }

                    if (!in_array($e->id, $grouped[$gk]['encounter_ids'])) {
                        $grouped[$gk]['encounter_ids'][] = $e->id;
                        $grouped[$gk]['total_encounters']++;

                        if (!in_array($e->patient_id, $grouped[$gk]['patient_ids'])) {
                            $grouped[$gk]['patient_ids'][] = $e->patient_id;
                            $grouped[$gk]['unique_patients']++;
                        }

                        $status = $norm['status'];
                        if ($status !== 'N/A' && $status !== 'NA' && !in_array($status, $grouped[$gk]['statuses'])) {
                            $grouped[$gk]['statuses'][] = $status;
                        }

                        $queryType = $norm['query'];
                        if ($queryType !== 'N/A' && $queryType !== 'NA' && !in_array($queryType, $grouped[$gk]['queries'])) {
                            $grouped[$gk]['queries'][] = $queryType;
                        }

                        $patientFormatted = $this->formatPersonName($e->patient?->user);
                        $doctorFormatted = $this->formatPersonName($e->doctor);

                        $allEncounters[] = [
                            'id' => $e->id,
                            'icd_code' => $norm['code'],
                            'diagnosis' => $norm['name'],
                            'patient_name' => $patientFormatted,
                            'file_no' => $e->patient->file_no ?? '',
                            'date' => $e->created_at->format('Y-m-d H:i'),
                            'doctor' => $doctorFormatted,
                            'query_type' => $queryType,
                            'status' => $status,
                            'hmo' => $e->patient && $e->patient->hmo ? $e->patient->hmo->name : 'Private / Self Pay',
                            'clinic' => $e->queue && $e->queue->clinic ? $e->queue->clinic->name : 'N/A',
                            'notes' => trim(preg_replace('/\s+/', ' ', strip_tags($e->notes ?? ''))),
                        ];
                    }
                }
            }
        }

        // Sort grouped diagnoses by unique patients desc, then total encounters desc
        usort($grouped, fn ($a, $b) => $b['unique_patients'] <=> $a['unique_patients'] ?: $b['total_encounters'] <=> $a['total_encounters']);

        // Filter label names for the header
        $clinicName = $request->filled('clinic_id') ? (\App\Models\Clinic::find($request->clinic_id)?->name ?? 'All') : 'All Clinics';
        $hmoName = $request->filled('hmo_id') ? (\App\Models\Hmo::find($request->hmo_id)?->name ?? 'All') : 'All HMOs';
        $wardName = $request->filled('ward_id') ? (\App\Models\Ward::find($request->ward_id)?->name ?? 'All') : 'All Wards';
        $totalEncounters = count($allEncounters);
        $totalUniquePatients = count(array_unique(array_column($allEncounters, 'patient_name')));

        $metadata = [
            ['Hospital', appsettings('hospitalname', 'CoreHealth Hospital')],
            ['Report', 'Clinical Reports - Diagnosis Search Export'],
            ['Generated At', now()->format('Y-m-d H:i:s')],
            ['Generated By', auth()->check() ? $this->formatPersonName(auth()->user()) : 'System Admin'],
            ['Search Keyword', $keyword !== '' ? $keyword : 'All Diagnoses'],
            ['Date Range', $dateFrom->format('Y-m-d') . ' to ' . $dateTo->format('Y-m-d')],
            ['Clinic Filter', $clinicName],
            ['HMO Filter', $hmoName],
            ['Ward Filter', $wardName],
            ['Total Diagnoses Found', count($grouped)],
            ['Total Encounters', $totalEncounters],
            ['Total Unique Patients', $totalUniquePatients],
        ];

        $summaryHeaders = ['ICD-10 Code', 'Diagnosis', 'Unique Patients', 'Total Encounters', 'Statuses', 'Query Types'];
        $summaryRows = [];
        foreach ($grouped as $g) {
            $summaryRows[] = [
                $g['icd_code'],
                $g['diagnosis'],
                $g['unique_patients'],
                $g['total_encounters'],
                implode(', ', $g['statuses']) ?: 'N/A',
                implode(', ', $g['queries']) ?: 'N/A',
            ];
        }

        $encounterHeaders = ['#', 'ICD-10 Code', 'Diagnosis', 'Patient Name', 'File No', 'Date', 'Doctor', 'Query Type', 'Status', 'HMO', 'Clinic', 'Clinical Notes'];
        $encounterRows = [];
        $seq = 1;
        foreach ($allEncounters as $enc) {
            $encounterRows[] = [
                $seq++,
                $enc['icd_code'],
                $enc['diagnosis'],
                $enc['patient_name'],
                $enc['file_no'],
                $enc['date'],
                $enc['doctor'],
                $enc['query_type'],
                $enc['status'],
                $enc['hmo'],
                $enc['clinic'],
                $enc['notes'],
            ];
        }

        $slug = $keyword !== '' ? \Illuminate\Support\Str::slug($keyword) : 'all_diagnoses';
        $filename = 'diagnosis_report_' . $slug . '_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($metadata, $summaryHeaders, $summaryRows, $encounterHeaders, $encounterRows) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            // Metadata / Filters Header Block
            fputcsv($file, ['=== REPORT FILTERS & METADATA ===']);
            foreach ($metadata as $meta) {
                fputcsv($file, $meta);
            }

            fputcsv($file, []); // Blank separator

            // Section 1: Diagnosis Summary
            fputcsv($file, ['=== DIAGNOSIS SUMMARY ===']);
            fputcsv($file, $summaryHeaders);
            foreach ($summaryRows as $row) {
                fputcsv($file, $row);
            }

            fputcsv($file, []); // Blank separator

            // Section 2: Detailed Encounters
            fputcsv($file, ['=== DETAILED ENCOUNTER RECORDS ===']);
            fputcsv($file, $encounterHeaders);
            foreach ($encounterRows as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
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
            'postnatal_visits' => $postnatalVisits,
        ];
    }

    public function getDrillDownDetails(Request $request)
    {
        $type = $request->get('type');
        $wardId = $request->get('ward_id');
        $from = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : now()->startOfMonth()->startOfDay();
        $to = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : now()->endOfDay();

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
                            'patient' => $this->formatPersonName($r->patient?->user),
                            'file_no' => $r->patient->file_no ?? '',
                            'age' => $age,
                            'sex' => ucfirst($r->patient->sex ?? $r->patient->gender ?? 'N/A'),
                            'date' => Carbon::parse($r->date_of_death)->format('Y-m-d') . ($r->time_of_death ? ' ' . $r->time_of_death : ''),
                            'death_type' => strtoupper($r->death_type ?? 'RIP'),
                            'primary_cause' => $r->cause_of_death_primary ?? 'N/A',
                            'contributing_factors' => $r->cause_of_death_description ?? 'None',
                            'patient_id' => $r->patient_id,
                        ];
                    });

                break;
            case 'maternity':
                $sub = $request->get('sub_category', 'deliveries');
                switch ($sub) {
                    case 'enrollments':
                        $data = \App\Models\MaternityEnrollment::with(['patient.user'])
                            ->whereBetween('enrollment_date', [$from, $to])
                            ->get()->map(fn ($r) => [
                                'patient' => $this->formatPersonName($r->patient?->user),
                                'patient_id' => $r->patient_id,
                                'file_no' => $r->patient->file_no ?? '',
                                'date' => $r->enrollment_date->format('Y-m-d'),
                                'risk' => ucfirst($r->risk_level),
                                'status' => ucfirst($r->status),
                            ]);

                        break;
                    case 'visits':
                        $data = \App\Models\AncVisit::with(['enrollment.patient.user'])
                            ->whereBetween('visit_date', [$from, $to])
                            ->get()->map(fn ($r) => [
                                'patient' => $this->formatPersonName($r->enrollment?->patient?->user),
                                'patient_id' => $r->enrollment->patient_id ?? null,
                                'file_no' => $r->enrollment->patient->file_no ?? '',
                                'date' => $r->visit_date->format('Y-m-d'),
                                'weight' => $r->weight_kg . 'kg',
                                'bp' => ($r->blood_pressure_systolic ?? '-') . '/' . ($r->blood_pressure_diastolic ?? '-'),
                            ]);

                        break;
                    case 'babies':
                        $data = \App\Models\MaternityBaby::with(['enrollment.patient.user', 'patient.user'])
                            ->whereBetween('created_at', [$from, $to])
                            ->get()->map(fn ($r) => [
                                'mother' => $this->formatPersonName($r->enrollment?->patient?->user),
                                'baby' => $this->formatPersonName($r->patient?->user),
                                'sex' => ucfirst($r->sex),
                                'status' => ucfirst($r->status),
                            ]);

                        break;
                    case 'admissions':
                        $data = \App\Models\AdmissionRequest::with(['patient.user', 'doctor'])
                            ->whereBetween('created_at', [$from, $to])
                            ->get()
                            ->map(function ($r) {
                                return [
                                    'patient' => $this->formatPersonName($r->patient?->user),
                                    'date' => $r->created_at->format('Y-m-d H:i'),
                                    'reason' => $r->admission_reason ?? 'N/A',
                                    'doctor' => $this->formatPersonName($r->doctor),
                                    'status' => ucfirst(str_replace('_', ' ', $r->admission_status)),
                                ];
                            });

                        break;
                    case 'postnatal':
                        $data = \App\Models\PostnatalVisit::with(['enrollment.patient.user'])
                            ->whereBetween('visit_date', [$from, $to])
                            ->get()->map(fn ($r) => [
                                'patient' => $this->formatPersonName($r->enrollment?->patient?->user),
                                'patient_id' => $r->enrollment->patient_id ?? null,
                                'date' => $r->visit_date->format('Y-m-d'),
                                'mother_condition' => $r->general_condition ?? 'N/A',
                                'baby_condition' => $r->baby_general_condition ?? 'N/A',
                            ]);

                        break;
                    case 'discharges':
                        $data = \App\Models\MaternityEnrollment::with(['patient.user'])
                            ->where('status', 'completed')
                            ->whereBetween('completed_at', [$from, $to])
                            ->get()->map(fn ($r) => [
                                'patient' => $this->formatPersonName($r->patient?->user),
                                'date' => $r->completed_at->format('Y-m-d'),
                                'outcome' => $r->outcome_summary,
                                'risk' => ucfirst($r->risk_level),
                            ]);

                        break;
                    case 'deliveries':
                    default:
                        $data = DeliveryRecord::with(['patient.user'])
                            ->whereBetween('delivery_date', [$from, $to])
                            ->get()
                            ->map(function ($r) {
                                return [
                                    'mother' => $this->formatPersonName($r->patient?->user),
                                    'date' => $r->delivery_date->format('Y-m-d') . ' ' . $r->delivery_time,
                                    'babies' => $r->number_of_babies,
                                    'outcome' => $r->type_of_delivery ?? 'N/A',
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
                            'patient' => $this->formatPersonName($r->patient?->user),
                            'patient_id' => $r->patient_id,
                            'file_no' => $r->patient->file_no ?? '',
                            'date' => $r->actual_end_time->format('Y-m-d H:i'),
                            'procedure_name' => $r->procedureDefinition ? $r->procedureDefinition->name : ($r->service ? $r->service->name : 'N/A'),
                            'category' => $r->procedureDefinition && $r->procedureDefinition->procedureCategory ? $r->procedureDefinition->procedureCategory->name : 'N/A',
                            'doctor' => $this->formatPersonName($r->requestedByUser),
                            'outcome' => $r->outcome ?? 'N/A',
                        ];
                    });

                break;
            case 'diagnosis':
                $icdCode = trim($request->get('icd_code') ?? '');
                $diagName = trim($request->get('diagnosis_name') ?? '');
                $isCustom = ($icdCode === '' || strtoupper($icdCode) === 'CUSTOM');

                $dq = Encounter::select([
                    'id',
                    'patient_id',
                    'doctor_id',
                    'queue_id',
                    'reasons_for_encounter',
                    'reasons_for_encounter_comment_1',
                    'reasons_for_encounter_comment_2',
                    'notes',
                    'created_at',
                ])->with([
                    'patient:id,user_id,file_no,hmo_id',
                    'patient.user:id,surname,firstname,othername',
                    'patient.hmo:id,name',
                    'doctor:id,surname,firstname,othername',
                    'queue:id,clinic_id',
                    'queue.clinic:id,name',
                ])->whereBetween('created_at', [$from, $to]);

                if ($request->filled('hmo_id')) {
                    $patientIds = Patient::where('hmo_id', $request->hmo_id)->pluck('id');
                    $dq->whereIn('patient_id', $patientIds);
                }

                if ($request->filled('clinic_id')) {
                    $queueIds = DoctorQueue::where('clinic_id', $request->clinic_id)->pluck('id');
                    $dq->whereIn('queue_id', $queueIds);
                }

                if ($request->filled('ward_id')) {
                    $admissionEncIds = AdmissionRequest::where('ward_id', $request->ward_id)
                        ->orWhereHas('bed', fn ($bq) => $bq->where('ward_id', $request->ward_id))
                        ->whereNotNull('encounter_id')
                        ->pluck('encounter_id');
                    $dq->whereIn('id', $admissionEncIds);
                }

                if (!$isCustom) {
                    $dq->where('reasons_for_encounter', 'like', '%' . $icdCode . '%');
                } elseif ($diagName !== '') {
                    $dq->where(function ($q) use ($diagName) {
                        $q->where('reasons_for_encounter', 'like', '%' . $diagName . '%')
                          ->orWhere('notes', 'like', '%' . $diagName . '%');
                    });
                }

                $targetGroupKey = !$isCustom ? ('ICD_' . strtoupper($icdCode)) : ('CUSTOM_' . strtolower(trim(preg_replace('/\s+/', ' ', preg_replace('/^custom:\s*/i', '', $diagName)))));

                $data = $dq->orderByDesc('created_at')->get()->map(function ($e) use ($isCustom, $targetGroupKey, $diagName) {
                    $rawReasons = !empty($e->reasons_for_encounter) ? json_decode($e->reasons_for_encounter, true) : [];
                    if (!is_array($rawReasons)) {
                        if (is_string($e->reasons_for_encounter) && trim($e->reasons_for_encounter) !== '') {
                            $rawReasons = array_filter(array_map('trim', explode(',', $e->reasons_for_encounter)));
                        } else {
                            $rawReasons = [];
                        }
                    }

                    $matchedNorm = null;
                    foreach ($rawReasons as $item) {
                        $norm = $this->normalizeDiagnosisItem($item, $e->reasons_for_encounter_comment_1, $e->reasons_for_encounter_comment_2);
                        if ($norm['group_key'] === $targetGroupKey || ($diagName !== '' && (stripos($norm['name'], $diagName) !== false || stripos($diagName, $norm['name']) !== false))) {
                            $matchedNorm = $norm;

                            break;
                        }
                    }

                    // If not found in reasons_for_encounter, check notes for custom diagnosis
                    if (!$matchedNorm && $isCustom && $diagName !== '' && !empty($e->notes) && stripos($e->notes, $diagName) !== false) {
                        if (stripos(strip_tags($e->notes), $diagName) !== false) {
                            $matchedNorm = $this->normalizeDiagnosisItem($diagName, $e->reasons_for_encounter_comment_1, $e->reasons_for_encounter_comment_2);
                        }
                    }

                    if (!$matchedNorm) {
                        return null;
                    }

                    $patientFormatted = $this->formatPersonName($e->patient?->user);
                    $doctorFormatted = $this->formatPersonName($e->doctor);

                    return [
                        'id' => $e->id,
                        'patient' => $patientFormatted,
                        'patient_name' => $patientFormatted,
                        'file_no' => $e->patient->file_no ?? '',
                        'patient_id' => $e->patient_id,
                        'date' => $e->created_at->format('Y-m-d H:i'),
                        'doctor' => $doctorFormatted,
                        'query_type' => $matchedNorm['query'],
                        'status' => $matchedNorm['status'],
                        'icd_code' => $matchedNorm['code'],
                    ];
                })->filter()->unique('id')->values();

                break;
            case 'immunization':
                $data = ImmunizationRecord::with(['patient.user', 'administeredBy'])
                    ->whereBetween('administered_at', [$from, $to])
                    ->get()
                    ->map(function ($r) {
                        return [
                            'patient' => $this->formatPersonName($r->patient?->user),
                            'patient_id' => $r->patient_id,
                            'file_no' => $r->patient->file_no ?? '',
                            'date' => $r->administered_at->format('Y-m-d H:i'),
                            'vaccine' => $r->vaccine_name,
                            'nurse' => $this->formatPersonName($r->administeredBy),
                        ];
                    });

                break;
            case 'referrals':
                $data = SpecialistReferral::with(['patient.user', 'referringDoctor.user', 'targetClinic'])
                    ->whereBetween('created_at', [$from, $to])
                    ->get()
                    ->map(function ($r) {
                        return [
                            'patient' => $this->formatPersonName($r->patient?->user),
                            'patient_id' => $r->patient_id,
                            'file_no' => $r->patient->file_no ?? '',
                            'date' => $r->created_at->format('Y-m-d H:i'),
                            'from_doctor' => $this->formatPersonName($r->referringDoctor?->user),
                            'to_clinic' => $r->targetClinic ? $r->targetClinic->name : ($r->external_facility_name ?? 'N/A'),
                        ];
                    });

                break;
            case 'occupancy':
                $occupiedBeds = Bed::with(['wardRelation', 'occupant.user'])
                    ->where('bed_status', 'occupied')
                    ->when($wardId, fn ($q) => $q->where('ward_id', $wardId))
                    ->get();
                $occupantIds = $occupiedBeds->pluck('occupant_id')->filter()->unique()->values();
                $activeAdmissions = AdmissionRequest::where('discharged', 0)
                    ->whereIn('patient_id', $occupantIds)
                    ->get()
                    ->keyBy('patient_id');
                $data = $occupiedBeds->map(function ($b) use ($activeAdmissions) {
                    $adm = $b->occupant_id ? ($activeAdmissions[$b->occupant_id] ?? null) : null;
                    $admittedAt = $adm ? $adm->created_at : $b->updated_at;

                    return [
                        'patient' => $this->formatPersonName($b->occupant?->user),
                        'patient_id' => $b->occupant_id,
                        'ward' => $b->wardRelation ? $b->wardRelation->name : ($b->ward ?? 'N/A'),
                        'bed' => $b->name,
                        'admitted_at' => $admittedAt ? Carbon::parse($admittedAt)->format('Y-m-d H:i') : 'N/A',
                        'days' => $admittedAt ? (int) Carbon::parse($admittedAt)->diffInDays(now()) : 0,
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
        $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : now()->endOfDay();
        $clinicId = $request->get('clinic_id');

        $summary = Encounter::whereBetween('encounters.created_at', [$from, $to])
            ->join('doctor_queues', 'encounters.queue_id', '=', 'doctor_queues.id')
            ->join('clinics', 'doctor_queues.clinic_id', '=', 'clinics.id')
            ->when($clinicId, fn ($q) => $q->where('clinics.id', $clinicId))
            ->select('clinics.id as clinic_id', 'clinics.name as clinic_name', DB::raw('count(*) as total'))
            ->groupBy('clinics.id', 'clinics.name')
            ->orderByDesc('total')
            ->get();

        $drillDown = null;
        if ($clinicId) {
            $drillDown = Encounter::with(['patient.user', 'doctor', 'patient.hmo'])
                ->whereBetween('encounters.created_at', [$from, $to])
                ->join('doctor_queues', 'encounters.queue_id', '=', 'doctor_queues.id')
                ->where('doctor_queues.clinic_id', $clinicId)
                ->select('encounters.*')
                ->orderByDesc('encounters.created_at')
                ->get()
                ->map(fn ($e) => [
                    'patient' => $this->formatPersonName($e->patient?->user),
                    'file_no' => $e->patient->file_no ?? '',
                    'patient_id' => $e->patient_id,
                    'date' => $e->created_at->format('Y-m-d H:i'),
                    'doctor' => $this->formatPersonName($e->doctor),
                    'hmo' => $e->patient?->hmo?->name ?? 'Self/Private',
                    'status' => $e->status ?? 'N/A',
                ]);
        }

        return response()->json([
            'summary' => $summary,
            'drill_down' => $drillDown,
        ]);
    }

    // -----------------------------------------------------------------------
    // NEW: HMO Trends — daily series + totals, optional drill-down by HMO
    // -----------------------------------------------------------------------
    public function getHmoTrends(Request $request)
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : now()->endOfDay();
        $hmoId = $request->get('hmo_id');

        $totals = Patient::join('encounters', 'patients.id', '=', 'encounters.patient_id')
            ->whereBetween('encounters.created_at', [$from, $to])
            ->join('hmos', 'patients.hmo_id', '=', 'hmos.id')
            ->when($hmoId, fn ($q) => $q->where('hmos.id', $hmoId))
            ->select('hmos.id as hmo_id', 'hmos.name as hmo_name', DB::raw('count(encounters.id) as total'), DB::raw('count(distinct patients.id) as unique_patients'))
            ->groupBy('hmos.id', 'hmos.name')
            ->orderByDesc('total')
            ->get();

        // Daily series for chart — group by date + HMO
        $daily = Patient::join('encounters', 'patients.id', '=', 'encounters.patient_id')
            ->whereBetween('encounters.created_at', [$from, $to])
            ->join('hmos', 'patients.hmo_id', '=', 'hmos.id')
            ->when($hmoId, fn ($q) => $q->where('hmos.id', $hmoId))
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
                ->map(fn ($e) => [
                    'patient' => $this->formatPersonName($e->patient?->user),
                    'file_no' => $e->patient->file_no ?? '',
                    'patient_id' => $e->patient_id,
                    'date' => $e->created_at->format('Y-m-d H:i'),
                ]);
        }

        return response()->json([
            'totals' => $totals,
            'daily' => $daily,
            'drill_down' => $drillDown,
        ]);
    }

    // -----------------------------------------------------------------------
    // NEW: Maternity Report — sub_category determines which dataset is returned
    // -----------------------------------------------------------------------
    public function getMaternityReport(Request $request)
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : now()->endOfDay();
        $sub = $request->get('sub_category', 'summary');

        // Summary counts (always returned)
        $enrollments = MaternityEnrollment::whereBetween('created_at', [$from, $to])->count();
        $activeEnroll = MaternityEnrollment::where('status', 'active')->count();
        $highRisk = MaternityEnrollment::where('risk_level', 'high')->where('status', 'active')->count();
        $ancVisits = AncVisit::whereBetween('visit_date', [$from, $to])->count();
        $deliveries = DeliveryRecord::whereBetween('delivery_date', [$from, $to])->count();
        $liveBirths = MaternityBaby::where('is_still_birth', 0)->whereBetween('created_at', [$from, $to])->count();
        $stillbirths = MaternityBaby::where('is_still_birth', 1)->whereBetween('created_at', [$from, $to])->count();
        $neonatalDeath = MaternityBaby::where('status', 'deceased')->where('is_still_birth', 0)->whereBetween('deceased_at', [$from, $to])->count();
        $postnatal = PostnatalVisit::whereBetween('visit_date', [$from, $to])->count();

        $summary = compact('enrollments', 'activeEnroll', 'highRisk', 'ancVisits', 'deliveries', 'liveBirths', 'stillbirths', 'neonatalDeath', 'postnatal');

        $data = [];
        switch ($sub) {
            case 'enrollments':
                $data = MaternityEnrollment::with(['patient.user'])
                    ->whereBetween('enrollment_date', [$from, $to])
                    ->get()
                    ->map(fn ($r) => [
                        'patient' => $this->formatPersonName($r->patient?->user),
                        'file_no' => $r->patient->file_no ?? '',
                        'patient_id' => $r->patient_id,
                        'date' => $r->enrollment_date ? Carbon::parse($r->enrollment_date)->format('Y-m-d') : 'N/A',
                        'edd' => $r->edd ? Carbon::parse($r->edd)->format('Y-m-d') : 'N/A',
                        'risk' => ucfirst($r->risk_level ?? 'normal'),
                        'status' => ucfirst($r->status ?? 'active'),
                    ]);

                break;

            case 'anc_visits':
                $data = AncVisit::with(['enrollment.patient.user'])
                    ->whereBetween('visit_date', [$from, $to])
                    ->get()
                    ->map(fn ($r) => [
                        'patient' => $r->enrollment ? $this->formatPersonName($r->enrollment->patient?->user) : 'N/A',
                        'file_no' => $r->enrollment ? ($r->enrollment->patient->file_no ?? '') : '',
                        'date' => Carbon::parse($r->visit_date)->format('Y-m-d'),
                        'weight_kg' => $r->weight_kg,
                        'bp' => ($r->blood_pressure_systolic ?? '-') . '/' . ($r->blood_pressure_diastolic ?? '-'),
                        'fundal_ht' => $r->fundal_height_cm ?? 'N/A',
                        'gestational_age' => $r->gestational_age_weeks ? $r->gestational_age_weeks . ' wks' : 'N/A',
                    ]);

                break;

            case 'deliveries':
                $data = DeliveryRecord::with(['patient.user'])
                    ->whereBetween('delivery_date', [$from, $to])
                    ->get()
                    ->map(fn ($r) => [
                        'mother' => $this->formatPersonName($r->patient?->user),
                        'file_no' => $r->patient->file_no ?? '',
                        'patient_id' => $r->patient_id,
                        'date' => $r->delivery_date ? Carbon::parse($r->delivery_date)->format('Y-m-d') : 'N/A',
                        'type' => strtoupper(str_replace('_', ' ', $r->type_of_delivery)),
                        'babies' => $r->number_of_babies,
                        'blood_loss' => $r->blood_loss_ml ? $r->blood_loss_ml . ' ml' : 'N/A',
                        'complications' => $r->complications ?? 'None',
                    ]);

                break;

            case 'babies':
                $data = MaternityBaby::with(['enrollment.patient.user', 'patient.user'])
                    ->whereBetween('created_at', [$from, $to])
                    ->get()
                    ->map(fn ($r) => [
                        'mother' => $r->enrollment ? $this->formatPersonName($r->enrollment->patient?->user) : 'N/A',
                        'baby' => $r->patient ? $this->formatPersonName($r->patient->user) : ('Baby #' . $r->birth_order),
                        'sex' => ucfirst($r->sex),
                        'weight_kg' => $r->birth_weight_kg ?? 'N/A',
                        'still_birth' => $r->is_still_birth ? 'Yes' : 'No',
                        'status' => ucfirst($r->status),
                        'cause_of_death' => $r->status === 'deceased' ? ($r->cause_of_death ?? 'N/A') : null,
                        'deceased_at' => $r->deceased_at ? Carbon::parse($r->deceased_at)->format('Y-m-d') : null,
                    ]);

                break;

            case 'postnatal':
                $data = PostnatalVisit::with(['enrollment.patient.user'])
                    ->whereBetween('visit_date', [$from, $to])
                    ->get()
                    ->map(fn ($r) => [
                        'patient' => $r->enrollment ? $this->formatPersonName($r->enrollment->patient?->user) : 'N/A',
                        'date' => Carbon::parse($r->visit_date)->format('Y-m-d'),
                        'mother_condition' => $r->general_condition ?? 'N/A',
                        'baby_condition' => $r->baby_general_condition ?? 'N/A',
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
        $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : now()->endOfDay();
        $type = $request->get('referral_type'); // internal | external | null (all)

        $q = SpecialistReferral::with(['patient.user', 'referringDoctor.user', 'targetClinic'])
            ->whereBetween('created_at', [$from, $to])
            ->when($type, fn ($q) => $q->where('referral_type', $type));

        $all = $q->get();

        $summary = [
            'total' => $all->count(),
            'internal' => $all->where('referral_type', 'internal')->count(),
            'external' => $all->where('referral_type', 'external')->count(),
            'booked' => $all->where('status', 'booked')->count(),
            'completed' => $all->where('status', 'completed')->count(),
            'pending' => $all->where('status', 'pending')->count(),
            'declined_cancelled' => $all->whereIn('status', ['declined', 'cancelled'])->count(),
            'internal_booked' => $all->where('referral_type', 'internal')->where('status', 'booked')->count(),
            'internal_total' => $all->where('referral_type', 'internal')->count(),
        ];
        $summary['conversion_rate'] = $summary['internal_total'] > 0
            ? round($summary['internal_booked'] / $summary['internal_total'] * 100, 1)
            : 0;

        $rows = $all->map(fn ($r) => [
            'id' => $r->id,
            'patient' => $this->formatPersonName($r->patient?->user),
            'file_no' => $r->patient->file_no ?? '',
            'patient_id' => $r->patient_id,
            'type' => ucfirst($r->referral_type),
            'from_doctor' => $this->formatPersonName($r->referringDoctor?->user),
            'to' => $r->referral_type === 'internal'
                ? ($r->targetClinic ? $r->targetClinic->name : 'N/A')
                : ($r->external_facility_name ?? 'N/A'),
            'to_doctor' => $r->referral_type === 'external' ? ($r->external_doctor_name ?? 'N/A') : 'N/A',
            'reason' => $r->reason,
            'urgency' => ucfirst($r->urgency),
            'status' => ucfirst($r->status),
            'booked' => $r->appointment_id ? 'Yes' : 'No',
            'date' => $r->created_at->format('Y-m-d H:i'),
        ]);

        return response()->json(compact('summary', 'rows'));
    }

    // -----------------------------------------------------------------------
    // NEW: Vaccinations — administered records + schedule status counts
    // -----------------------------------------------------------------------
    public function getVaccinations(Request $request)
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : now()->endOfDay();
        $vaccine = $request->get('vaccine_name');

        // Administered doses
        $administered = ImmunizationRecord::with(['patient.user', 'administeredBy'])
            ->whereBetween('administered_at', [$from, $to])
            ->when($vaccine, fn ($q) => $q->where('vaccine_name', $vaccine))
            ->get();

        $byVaccine = $administered->groupBy('vaccine_name')->map(fn ($g) => [
            'vaccine_name' => $g->first()->vaccine_name,
            'total_doses' => $g->count(),
            'patients' => $g->pluck('patient_id')->unique()->count(),
        ])->values();

        $rows = $administered->map(fn ($r) => [
            'patient' => $this->formatPersonName($r->patient?->user),
            'file_no' => $r->patient->file_no ?? '',
            'patient_id' => $r->patient_id,
            'vaccine' => $r->vaccine_name,
            'dose_no' => $r->dose_number,
            'route' => $r->route,
            'date' => Carbon::parse($r->administered_at)->format('Y-m-d H:i'),
            'nurse' => $this->formatPersonName($r->administeredBy),
            'batch' => $r->batch_number ?? 'N/A',
            'next_due' => $r->next_due_date ? Carbon::parse($r->next_due_date)->format('Y-m-d') : 'N/A',
        ]);

        // Schedule status summary (current snapshot, not date-filtered)
        $scheduleStats = PatientImmunizationSchedule::select('status', DB::raw('count(*) as count'))
            ->when($vaccine, fn ($q) => $q->whereHas('scheduleItem', fn ($sq) => $sq->where('vaccine_name', $vaccine)))
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json(compact('byVaccine', 'rows', 'scheduleStats'));
    }

    // -----------------------------------------------------------------------
    // NEW: Occupancy — per ward snapshot + avg LOS from admission_requests
    // -----------------------------------------------------------------------
    public function getOccupancy(Request $request)
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : now()->endOfDay();
        $wardId = $request->get('ward_id');

        // Current occupancy per ward
        $wards = Ward::withCount([
            'beds as total_beds',
            'beds as occupied_beds' => fn ($q) => $q->where('bed_status', 'occupied'),
            'beds as available_beds' => fn ($q) => $q->where('bed_status', 'available'),
        ])
        ->where('is_active', 1)
        ->when($wardId, fn ($q) => $q->where('id', $wardId))
        ->get()
        ->map(fn ($w) => [
            'ward_id' => $w->id,
            'ward_name' => $w->name,
            'type' => ucfirst($w->type),
            'capacity' => $w->capacity,
            'total_beds' => $w->total_beds,
            'occupied' => $w->occupied_beds,
            'available' => $w->available_beds,
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
            ->when($wardId, fn ($q) => $q->where('ward_id', $wardId))
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
                'patient' => $b->occupant ? userfullname($b->occupant->user_id) : 'N/A',
                'file_no' => $b->occupant->file_no ?? '',
                'patient_id' => $b->occupant_id,
                'ward' => $b->wardRelation ? $b->wardRelation->name : ($b->ward ?? 'N/A'),
                'bed' => $b->name,
                'admitted_at' => $admittedAt ? Carbon::parse($admittedAt)->format('Y-m-d H:i') : 'N/A',
                'days' => $admittedAt ? (int) Carbon::parse($admittedAt)->diffInDays(now()) : 0,
            ];
        });

        return response()->json([
            'wards' => $wards,
            'avg_los_days' => $avgLos ? round($avgLos, 1) : 0,
            'current_patients' => $currentPatients,
        ]);
    }
}
