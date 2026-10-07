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
use App\Models\HmoScheme;
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
            case 'dns':
                return $this->exportDns($request);
            case 'diagnosis':
                return $this->exportDiagnosis($request);
            default:
                // Start from diagnosis search, fallback to diagnosis if unknown
                return $this->exportDiagnosis($request);
        }
    }

    /**
     * Export DNS Report as CSV
     */
    public function exportDns(Request $request)
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : now()->endOfDay();
        $wardId = $request->get('ward_id');

        $data = $this->getDnsReportData($from, $to, $wardId);
        $filename = 'dns_report_' . $from->format('Y_m_d') . '_to_' . $to->format('Y_m_d') . '.csv';

        $callback = function () use ($data) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['DNS CLINICAL REPORT & CENSUS']);
            fputcsv($fh, ['Hospital', $data['hospital']['name']]);
            fputcsv($fh, ['Period', $data['period']['formatted']]);
            fputcsv($fh, []);
            fputcsv($fh, ['KEY CLINICAL INDICATORS']);
            fputcsv($fh, ['Indicator', 'Count / Value']);
            $kpiLabels = [
                'total_outpatient' => 'Total Outpatient (All Clinics)',
                'gopd' => 'GOPD Visits (General Outpatient)',
                'popd' => 'POPD Visits (Private Outpatient)',
                'total_inpatients' => 'Total Inpatients (Active in Wards)',
                'empty_beds' => 'Available Empty Beds',
                'total_admissions' => 'Inpatient Admissions',
                'total_discharges' => 'Inpatient Discharges',
                'sama' => 'Signed Against Medical Advice (SAMA)',
                'absconsion' => 'Absconsion (Left Without Notice)',
                'referrals' => 'Referrals & Outward Transfers',
                'normal_delivery' => 'Normal Deliveries (SVD / Vaginal)',
                'cs_delivery' => 'Caesarean Sections (CS)',
                'total_deliveries' => 'Total Deliveries',
                'cs_rate' => 'Caesarean Section (CS) Rate (%)',
                'total_surgeries' => 'Completed Surgical Operations',
                'total_deaths' => 'Total Mortalities / Deaths',
                'corpses' => 'Morgue Received (Corpses)',
                'corpses_active' => 'Current Bodies in Mortuary',
                'day_care' => 'Day Care & Emergency Inpatients',
                'emergency_intakes' => 'Emergency Intakes (A&E Queue)',
                'emergency_admissions' => 'Emergency Admissions',
                'same_day_observations' => 'Same-Day Observations',
            ];
            foreach ($data['kpis'] as $key => $val) {
                $label = $kpiLabels[$key] ?? ucwords(str_replace('_', ' ', $key));
                $displayVal = ($key === 'cs_rate') ? ($val . '%') : $val;
                fputcsv($fh, [$label, $displayVal]);
            }
            fputcsv($fh, []);
            fputcsv($fh, ['WARD INPATIENT & BED CENSUS']);
            fputcsv($fh, ['Ward Name', 'Specialty', 'Total Beds', 'Inpatients', 'Available Beds', 'Occupancy %']);
            foreach ($data['ward_census']['wards'] as $w) {
                fputcsv($fh, [$w['ward_name'], $w['specialty'], $w['total_beds'], $w['occupied'], $w['available'], $w['occupancy_rate']]);
            }
            fputcsv($fh, ['TOTAL / AVERAGE', '', $data['ward_census']['total_beds'], $data['ward_census']['occupied'], $data['ward_census']['available'], $data['ward_census']['occupancy_rate']]);
            fputcsv($fh, []);
            fputcsv($fh, ['ALL CLINICS OUTPATIENT VOLUME']);
            fputcsv($fh, ['Clinic Name', 'Attended Encounters', 'Percentage Share']);
            foreach ($data['clinics'] as $c) {
                fputcsv($fh, [$c['name'], $c['total'], $c['percentage'] . '%']);
            }
            fclose($fh);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
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

    // -----------------------------------------------------------------------
    // DNS (Director of Nursing Services) Statistics & Census Report Engine
    // -----------------------------------------------------------------------

    /**
     * Build generic DNS operational statistics and census dataset
     */
    public function getDnsReportData(Carbon $from, Carbon $to, $wardId = null): array
    {
        // 1. ALL ACTIVE CLINICS OUTPATIENT VOLUME (Generic, dynamic, resilient)
        $clinics = Clinic::where('status', 1)->orderBy('name')->get();
        $encountersByClinic = Encounter::whereBetween('encounters.created_at', [$from, $to])
            ->join('doctor_queues', 'encounters.queue_id', '=', 'doctor_queues.id')
            ->select('doctor_queues.clinic_id', DB::raw('count(*) as total'))
            ->groupBy('doctor_queues.clinic_id')
            ->pluck('total', 'clinic_id');

        $totalOutpatient = Encounter::whereBetween('created_at', [$from, $to])->count();
        $clinicData = [];
        $totalMappedClinics = 0;

        foreach ($clinics as $clinic) {
            $count = (int) ($encountersByClinic[$clinic->id] ?? 0);
            $totalMappedClinics += $count;
            $clinicData[] = [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'total' => $count,
                'percentage' => $totalOutpatient > 0 ? round(($count / $totalOutpatient) * 100, 1) : 0,
            ];
        }

        // Catch unassigned or direct encounters if any
        $unassignedCount = max(0, $totalOutpatient - $totalMappedClinics);
        if ($unassignedCount > 0) {
            $clinicData[] = [
                'id' => null,
                'name' => 'General / Direct Outpatient',
                'total' => $unassignedCount,
                'percentage' => $totalOutpatient > 0 ? round(($unassignedCount / $totalOutpatient) * 100, 1) : 0,
            ];
        }

        // Dynamically compute GOPD and POPD without fragile clinic assumptions
        // POPD: Any consultation in a clinic named POPD/Private OR patient has Private scheme
        $popdCount = Encounter::whereBetween('encounters.created_at', [$from, $to])
            ->where(function ($q) {
                $q->whereHas('patient', function ($p) {
                    $p->where('hmo_id', 1)
                      ->orWhereHas('hmo', fn ($h) => $h->where('name', 'LIKE', '%Private%'));
                })
                ->orWhereHas('queue.clinic', function ($c) {
                    $c->where('name', 'LIKE', '%POPD%')
                      ->orWhere('name', 'LIKE', '%Private%');
                });
            })
            ->count();

        // GOPD: Consultations in General/GOPD clinic or non-private patients
        $gopdCount = Encounter::whereBetween('encounters.created_at', [$from, $to])
            ->where(function ($q) {
                $q->whereHas('queue.clinic', function ($c) {
                    $c->where('name', 'LIKE', '%General%')
                      ->orWhere('name', 'LIKE', '%GOPD%');
                })
                ->orWhereDoesntHave('patient', function ($p) {
                    $p->where('hmo_id', 1)
                      ->orWhereHas('hmo', fn ($h) => $h->where('name', 'LIKE', '%Private%'));
                });
            })
            ->count();

        if ($gopdCount === 0 && $popdCount === 0 && $totalOutpatient > 0) {
            $gopdCount = $totalOutpatient;
        }

        // 2. DAY CARE & EMERGENCY INTAKE
        $emergencyQueueCount = DoctorQueue::whereBetween('created_at', [$from, $to])
            ->where('source', 'emergency_intake')
            ->count();

        $emergencyAdmissionCount = AdmissionRequest::whereBetween('created_at', [$from, $to])
            ->where('priority', 'emergency')
            ->count();

        $sameDayObservationsCount = AdmissionRequest::where('discharged', 1)
            ->whereBetween('discharge_date', [$from, $to])
            ->whereDate('created_at', DB::raw('DATE(discharge_date)'))
            ->count();

        $totalDayCare = $emergencyQueueCount + $sameDayObservationsCount;

        // 3. INPATIENT ADMISSIONS & DISCHARGES
        $totalAdmissions = AdmissionRequest::whereBetween('created_at', [$from, $to])
            ->when($wardId, fn ($q) => $q->whereHas('bed', fn ($b) => $b->where('ward_id', $wardId)))
            ->count();

        $totalDischarges = AdmissionRequest::where('discharged', 1)
            ->whereBetween('discharge_date', [$from, $to])
            ->when($wardId, fn ($q) => $q->whereHas('bed', fn ($b) => $b->where('ward_id', $wardId)))
            ->count();

        // 4. CRITICAL DISCHARGES: SAMA & ABSCONSION
        $samaCount = AdmissionRequest::where('discharged', 1)
            ->whereBetween('discharge_date', [$from, $to])
            ->when($wardId, fn ($q) => $q->whereHas('bed', fn ($b) => $b->where('ward_id', $wardId)))
            ->where(function ($q) {
                $q->where('discharge_reason', 'LIKE', '%against medical advice%')
                  ->orWhere('discharge_reason', 'LIKE', '%ama%')
                  ->orWhere('discharge_reason', 'LIKE', '%sama%')
                  ->orWhere('discharge_reason', 'LIKE', '%dama%')
                  ->orWhere('discharge_note', 'LIKE', '%against medical advice%')
                  ->orWhere('discharge_note', 'LIKE', '%ama%');
            })
            ->count();

        $absconsionCount = AdmissionRequest::where('discharged', 1)
            ->whereBetween('discharge_date', [$from, $to])
            ->when($wardId, fn ($q) => $q->whereHas('bed', fn ($b) => $b->where('ward_id', $wardId)))
            ->where(function ($q) {
                $q->where('discharge_reason', 'LIKE', '%abscond%')
                  ->orWhere('discharge_reason', 'LIKE', '%left without notice%')
                  ->orWhere('discharge_reason', 'LIKE', '%eloped%')
                  ->orWhere('discharge_note', 'LIKE', '%abscond%');
            })
            ->count();

        // 5. REFERRALS (Inpatient transfer discharges + Outgoing specialist referrals)
        $externalReferrals = SpecialistReferral::whereBetween('created_at', [$from, $to])
            ->where('referral_type', 'external')
            ->count();

        $transferDischarges = AdmissionRequest::where('discharged', 1)
            ->whereBetween('discharge_date', [$from, $to])
            ->when($wardId, fn ($q) => $q->whereHas('bed', fn ($b) => $b->where('ward_id', $wardId)))
            ->where(function ($q) {
                $q->where('discharge_reason', 'LIKE', '%transfer%')
                  ->orWhere('discharge_reason', 'LIKE', '%higher level%')
                  ->orWhere('discharge_reason', 'LIKE', '%another facility%')
                  ->orWhere('discharge_reason', 'LIKE', '%referral%');
            })
            ->count();

        $totalReferrals = $externalReferrals + $transferDischarges;

        // 6. MATERNITY DELIVERIES (Normal vs Caesarean Section)
        $normalDeliveries = DeliveryRecord::whereBetween('delivery_date', [$from, $to])
            ->where(function ($q) {
                $q->whereIn('type_of_delivery', ['svd', 'assisted_vaginal', 'vacuum', 'normal', 'vaginal'])
                  ->orWhere(function ($sub) {
                      $sub->where('type_of_delivery', 'NOT LIKE', '%cs%')
                          ->where('type_of_delivery', 'NOT LIKE', '%caesarean%')
                          ->where('type_of_delivery', 'NOT LIKE', '%cesarean%');
                  });
            })
            ->count();

        $csDeliveries = DeliveryRecord::whereBetween('delivery_date', [$from, $to])
            ->where(function ($q) {
                $q->whereIn('type_of_delivery', ['elective_cs', 'emergency_cs', 'cs'])
                  ->orWhere('type_of_delivery', 'LIKE', '%cs%')
                  ->orWhere('type_of_delivery', 'LIKE', '%caesarean%')
                  ->orWhere('type_of_delivery', 'LIKE', '%cesarean%');
            })
            ->count();

        $totalDeliveries = $normalDeliveries + $csDeliveries;
        $csRate = $totalDeliveries > 0 ? round(($csDeliveries / $totalDeliveries) * 100, 1) : 0;

        // 7. SURGERIES
        $totalSurgeries = Procedure::where('procedure_status', 'completed')
            ->whereBetween('actual_end_time', [$from, $to])
            ->whereHas('procedureDefinition', fn ($q) => $q->where('is_surgical', 1))
            ->count();

        // 8. TOTAL DEATHS
        $deathRecordsCount = DeathRecord::whereBetween('date_of_death', [$from, $to])->count();
        $inpatientDeathsCount = AdmissionRequest::where('discharged', 1)
            ->whereBetween('discharge_date', [$from, $to])
            ->when($wardId, fn ($q) => $q->whereHas('bed', fn ($b) => $b->where('ward_id', $wardId)))
            ->where(function ($q) {
                $q->where('discharge_reason', 'Deceased')
                  ->orWhereNotNull('death_record_id');
            })
            ->count();

        $totalDeaths = max($deathRecordsCount, $inpatientDeathsCount);

        // 9. CORPSES (Morgue)
        $corpsesActive = \Illuminate\Support\Facades\Schema::hasTable('morgue_admissions')
            ? \App\Models\MorgueAdmission::where('status', 'admitted')->count()
            : 0;
        $corpsesReceived = \Illuminate\Support\Facades\Schema::hasTable('morgue_admissions')
            ? \App\Models\MorgueAdmission::whereBetween('arrival_time', [$from, $to])->count()
            : $deathRecordsCount;

        // 10. DYNAMIC WARD CENSUS FOR ALL ACTIVE WARDS (Generic, never hardcoded)
        $wards = Ward::with(['beds'])
            ->where('is_active', 1)
            ->when($wardId, fn ($q) => $q->where('id', $wardId))
            ->orderBy('name')
            ->get();

        $wardData = [];
        $hospitalTotalBeds = 0;
        $hospitalOccupied = 0;
        $hospitalAvailable = 0;
        $hospitalMaintenance = 0;

        foreach ($wards as $w) {
            $totalBeds = $w->beds->count() > 0 ? $w->beds->count() : (int) $w->capacity;
            $wOccupied = $w->beds->where('bed_status', 'occupied')->count();
            $wAvailable = $w->beds->where('bed_status', 'available')->count();
            $wMaint = $w->beds->where('bed_status', 'maintenance')->count();

            if ($wAvailable === 0 && ($totalBeds - $wOccupied - $wMaint) > 0) {
                $wAvailable = max(0, $totalBeds - $wOccupied - $wMaint);
            }

            $rate = $totalBeds > 0 ? round(($wOccupied / $totalBeds) * 100, 1) : 0;

            $hospitalTotalBeds += $totalBeds;
            $hospitalOccupied += $wOccupied;
            $hospitalAvailable += $wAvailable;
            $hospitalMaintenance += $wMaint;

            $wardData[] = [
                'ward_id' => $w->id,
                'ward' => $w->name,
                'ward_name' => $w->name,
                'code' => $w->code ?: strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $w->name), 0, 4)),
                'type' => ucfirst($w->type ?? 'General'),
                'specialty' => ucfirst($w->type ?? 'General'),
                'total' => $totalBeds,
                'total_beds' => $totalBeds,
                'occupied' => $wOccupied,
                'available' => $wAvailable,
                'maintenance' => $wMaint,
                'occupancy_rate' => $rate . '%',
                'occupancy_pct' => $rate,
            ];
        }

        $overallOccupancyRate = $hospitalTotalBeds > 0 ? round(($hospitalOccupied / $hospitalTotalBeds) * 100, 1) : 0;

        return [
            'period' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'formatted' => $from->format('d M Y') . ' — ' . $to->format('d M Y'),
            ],
            'kpis' => [
                'total_outpatient' => $totalOutpatient,
                'gopd' => $gopdCount,
                'popd' => $popdCount,
                'total_inpatients' => $hospitalOccupied,
                'empty_beds' => $hospitalAvailable,
                'total_admissions' => $totalAdmissions,
                'total_discharges' => $totalDischarges,
                'sama' => $samaCount,
                'absconsion' => $absconsionCount,
                'referrals' => $totalReferrals,
                'normal_delivery' => $normalDeliveries,
                'cs_delivery' => $csDeliveries,
                'total_deliveries' => $totalDeliveries,
                'cs_rate' => $csRate,
                'total_surgeries' => $totalSurgeries,
                'total_deaths' => $totalDeaths,
                'corpses' => $corpsesReceived,
                'corpses_active' => $corpsesActive,
                'day_care' => $totalDayCare,
                'emergency_intakes' => $emergencyQueueCount,
                'emergency_admissions' => $emergencyAdmissionCount,
                'same_day_observations' => $sameDayObservationsCount,
            ],
            'ward_census' => [
                'total_beds' => $hospitalTotalBeds,
                'total' => $hospitalTotalBeds,
                'occupied' => $hospitalOccupied,
                'available' => $hospitalAvailable,
                'maintenance' => $hospitalMaintenance,
                'occupancy_rate' => $overallOccupancyRate . '%',
                'occupancy_pct' => $overallOccupancyRate,
                'wards' => $wardData,
            ],
            'clinics' => $clinicData,
            'hospital' => [
                'name' => appsettings('hos_name') ?? appsettings('site_name') ?? 'Hospital Management System',
                'address' => appsettings('contact_address') ?? '',
                'phone' => appsettings('contact_phones') ?? '',
            ],
        ];
    }

    /**
     * Get DNS Statistics and Ward Census Report (JSON API)
     */
    public function getDnsReport(Request $request)
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : now()->endOfDay();
        $wardId = $request->get('ward_id');

        $data = $this->getDnsReportData($from, $to, $wardId);

        return response()->json(array_merge(['success' => true], $data));
    }

    /**
     * Render patient HMO and Scheme markup matching NHMIS workbench standard
     */
    protected function renderPatientHmo($patient): array
    {
        $hmo = $patient?->hmo;
        if (!$hmo) {
            return [
                'hmo_id' => null,
                'hmo_scheme_id' => null,
                'hmo_html' => '<span class="text-muted" style="font-size:0.75rem;">Cash</span>',
            ];
        }

        $html = '<small class="font-weight-bold text-info"><i class="mdi mdi-shield-account"></i> ' . e($hmo->name ?? '-') . '</small>' .
            ($hmo->scheme ? '<br><small class="text-muted" style="font-size:0.7rem;">' . e($hmo->scheme->name) . '</small>' : '');

        return [
            'hmo_id' => $hmo->id,
            'hmo_scheme_id' => $hmo->hmo_scheme_id,
            'hmo_html' => $html,
        ];
    }

    /**
     * Universal Paginated & Server-Side Filterable Drill-Down for DNS Report Metrics
     * Follows the exact architecture of NhmisWorkbenchController::drillDown
     */
    public function getDnsDrillDown(Request $request)
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : now()->endOfDay();
        $metric = $request->get('metric', 'inpatients');
        $clinicId = $request->get('clinic_id');
        $wardId = $request->get('ward_id');

        $results = [];
        $title = 'Drill-Down Details';

        switch ($metric) {
            case 'gopd':
                $title = 'General Outpatient Department (GOPD) Consultations';
                $q = Encounter::with(['patient.user', 'patient.hmo.scheme', 'doctor', 'queue.clinic'])
                    ->whereBetween('encounters.created_at', [$from, $to])
                    ->where(function ($query) {
                        $query->whereHas('queue.clinic', function ($c) {
                            $c->where('name', 'LIKE', '%General%')
                              ->orWhere('name', 'LIKE', '%GOPD%');
                        })
                        ->orWhereDoesntHave('patient', function ($p) {
                            $p->where('hmo_id', 1)
                              ->orWhereHas('hmo', fn ($h) => $h->where('name', 'LIKE', '%Private%'));
                        });
                    })
                    ->orderByDesc('encounters.created_at');

                foreach ($q->get() as $e) {
                    $p = $e->patient;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => $e->id,
                        'patient_id' => $e->patient_id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => $p?->gender ?? 'N/A',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => $e->created_at->format('Y-m-d H:i'),
                        'location' => $e->queue?->clinic?->name ?? 'GOPD / General Outpatient',
                        'doctor_name' => $this->formatPersonName($e->doctor),
                        'details' => $e->reasons_for_encounter ?? $e->notes ?? 'GOPD Consultation',
                    ];
                }

                break;

            case 'popd':
                $title = 'Private Outpatient Department (POPD) Consultations';
                $q = Encounter::with(['patient.user', 'patient.hmo.scheme', 'doctor', 'queue.clinic'])
                    ->whereBetween('encounters.created_at', [$from, $to])
                    ->where(function ($query) {
                        $query->whereHas('patient', function ($p) {
                            $p->where('hmo_id', 1)
                              ->orWhereHas('hmo', fn ($h) => $h->where('name', 'LIKE', '%Private%'));
                        })
                        ->orWhereHas('queue.clinic', function ($c) {
                            $c->where('name', 'LIKE', '%POPD%')
                              ->orWhere('name', 'LIKE', '%Private%');
                        });
                    })
                    ->orderByDesc('encounters.created_at');

                foreach ($q->get() as $e) {
                    $p = $e->patient;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => $e->id,
                        'patient_id' => $e->patient_id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => $p?->gender ?? 'N/A',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => $e->created_at->format('Y-m-d H:i'),
                        'location' => $e->queue?->clinic?->name ?? 'POPD / Private Outpatient',
                        'doctor_name' => $this->formatPersonName($e->doctor),
                        'details' => $e->reasons_for_encounter ?? $e->notes ?? 'POPD Private Consultation',
                    ];
                }

                break;

            case 'outpatient':
            case 'clinic':
                $q = Encounter::with(['patient.user', 'patient.hmo.scheme', 'doctor', 'queue.clinic'])
                    ->whereBetween('encounters.created_at', [$from, $to])
                    ->join('doctor_queues', 'encounters.queue_id', '=', 'doctor_queues.id')
                    ->when($clinicId, fn ($query) => $query->where('doctor_queues.clinic_id', $clinicId))
                    ->select('encounters.*')
                    ->orderByDesc('encounters.created_at');

                $title = $clinicId ? ('Outpatient Consultations: ' . (Clinic::find($clinicId)?->name ?? 'Clinic')) : 'All Outpatient Consultations';
                foreach ($q->get() as $e) {
                    $p = $e->patient;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => $e->id,
                        'patient_id' => $e->patient_id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => $p?->gender ?? 'N/A',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => $e->created_at->format('Y-m-d H:i'),
                        'location' => $e->queue?->clinic?->name ?? 'OPD',
                        'doctor_name' => $this->formatPersonName($e->doctor),
                        'details' => $e->reasons_for_encounter ?? $e->notes ?? 'Outpatient Consultation',
                    ];
                }

                break;

            case 'emergency_intakes':
                $title = 'Emergency Intakes';
                $records = DoctorQueue::with(['patient.user', 'patient.hmo.scheme', 'clinic', 'doctor.user'])
                    ->whereBetween('created_at', [$from, $to])
                    ->where('source', 'emergency_intake')
                    ->orderByDesc('created_at')
                    ->get();

                foreach ($records as $dq) {
                    $p = $dq->patient;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => $dq->id,
                        'patient_id' => $dq->patient_id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => $p?->gender ?? 'N/A',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => $dq->created_at->format('Y-m-d H:i'),
                        'location' => 'Emergency Intake (A&E)' . ($dq->clinic ? ' - ' . $dq->clinic->name : ''),
                        'doctor_name' => $this->formatPersonName($dq->doctor?->user),
                        'details' => 'Emergency Queue | Priority: ' . ucfirst($dq->priority ?? 'emergency') . ($dq->triage_note ? ' | ' . $dq->triage_note : ''),
                    ];
                }

                break;

            case 'same_day_observations':
            case 'day_care':
                $title = 'Day Care & Emergency Inpatients';
                // 1. Emergency Queue
                $eq = DoctorQueue::with(['patient.user', 'patient.hmo.scheme', 'clinic', 'doctor.user'])
                    ->whereBetween('created_at', [$from, $to])
                    ->where('source', 'emergency_intake')
                    ->orderByDesc('created_at')
                    ->get();
                foreach ($eq as $dq) {
                    $p = $dq->patient;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => 'eq_' . $dq->id,
                        'patient_id' => $dq->patient_id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => $p?->gender ?? 'N/A',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => $dq->created_at->format('Y-m-d H:i'),
                        'location' => 'Emergency Room (A&E)',
                        'doctor_name' => $this->formatPersonName($dq->doctor?->user),
                        'details' => 'Emergency Intake Queue | Priority: ' . ucfirst($dq->priority ?? 'emergency'),
                    ];
                }

                // 2. Same-Day Observations
                $obs = AdmissionRequest::with(['patient.user', 'patient.hmo.scheme', 'bed.wardRelation', 'doctor'])
                    ->where('discharged', 1)
                    ->whereBetween('discharge_date', [$from, $to])
                    ->whereDate('created_at', DB::raw('DATE(discharge_date)'))
                    ->orderByDesc('created_at')
                    ->get();
                foreach ($obs as $adm) {
                    $p = $adm->patient;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => 'obs_' . $adm->id,
                        'patient_id' => $adm->patient_id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => $p?->gender ?? 'N/A',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => $adm->created_at->format('Y-m-d H:i') . ' to ' . Carbon::parse($adm->discharge_date)->format('H:i'),
                        'location' => $adm->bed?->wardRelation?->name ?? 'Day Care / Observation',
                        'doctor_name' => $this->formatPersonName($adm->doctor),
                        'details' => 'Same-Day Observation | ' . ($adm->discharge_reason ?? 'Discharged'),
                    ];
                }

                break;

            case 'admissions':
                $title = 'Inpatient Admissions';
                $adms = AdmissionRequest::with(['patient.user', 'patient.hmo.scheme', 'bed.wardRelation', 'doctor'])
                    ->whereBetween('created_at', [$from, $to])
                    ->when($wardId, fn ($q) => $q->whereHas('bed', fn ($b) => $b->where('ward_id', $wardId)))
                    ->orderByDesc('created_at')
                    ->get();
                foreach ($adms as $adm) {
                    $p = $adm->patient;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => $adm->id,
                        'patient_id' => $adm->patient_id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => $p?->gender ?? 'N/A',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => $adm->created_at->format('Y-m-d H:i'),
                        'location' => ($adm->bed?->wardRelation?->name ?? 'Ward') . ' - ' . ($adm->bed?->name ?? 'Bed'),
                        'doctor_name' => $this->formatPersonName($adm->doctor),
                        'details' => $adm->admission_reason ?? $adm->chief_complaint ?? 'Admitted',
                    ];
                }

                break;

            case 'discharges':
                $title = 'Inpatient Discharges';
                $dischs = AdmissionRequest::with(['patient.user', 'patient.hmo.scheme', 'bed.wardRelation', 'doctor'])
                    ->where('discharged', 1)
                    ->whereBetween('discharge_date', [$from, $to])
                    ->when($wardId, fn ($q) => $q->whereHas('bed', fn ($b) => $b->where('ward_id', $wardId)))
                    ->orderByDesc('discharge_date')
                    ->get();
                foreach ($dischs as $adm) {
                    $p = $adm->patient;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => $adm->id,
                        'patient_id' => $adm->patient_id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => $p?->gender ?? 'N/A',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => Carbon::parse($adm->discharge_date)->format('Y-m-d H:i'),
                        'location' => $adm->bed?->wardRelation?->name ?? 'Ward',
                        'doctor_name' => $this->formatPersonName($adm->doctor),
                        'details' => $adm->discharge_reason ?? $adm->discharge_note ?? 'Discharged',
                    ];
                }

                break;

            case 'sama':
                $title = 'Signed Against Medical Advice (SAMA)';
                $samas = AdmissionRequest::with(['patient.user', 'patient.hmo.scheme', 'bed.wardRelation', 'doctor'])
                    ->where('discharged', 1)
                    ->whereBetween('discharge_date', [$from, $to])
                    ->where(function ($q) {
                        $q->where('discharge_reason', 'LIKE', '%against medical advice%')
                          ->orWhere('discharge_reason', 'LIKE', '%ama%')
                          ->orWhere('discharge_reason', 'LIKE', '%sama%')
                          ->orWhere('discharge_reason', 'LIKE', '%dama%')
                          ->orWhere('discharge_note', 'LIKE', '%against medical advice%')
                          ->orWhere('discharge_note', 'LIKE', '%ama%');
                    })
                    ->orderByDesc('discharge_date')
                    ->get();
                foreach ($samas as $adm) {
                    $p = $adm->patient;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => $adm->id,
                        'patient_id' => $adm->patient_id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => $p?->gender ?? 'N/A',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => Carbon::parse($adm->discharge_date)->format('Y-m-d H:i'),
                        'location' => $adm->bed?->wardRelation?->name ?? 'Ward',
                        'doctor_name' => $this->formatPersonName($adm->doctor),
                        'details' => $adm->discharge_reason . ($adm->discharge_note ? ' - ' . $adm->discharge_note : ''),
                    ];
                }

                break;

            case 'absconsion':
                $title = 'Absconsion (Left Without Notice)';
                $abss = AdmissionRequest::with(['patient.user', 'patient.hmo.scheme', 'bed.wardRelation', 'doctor'])
                    ->where('discharged', 1)
                    ->whereBetween('discharge_date', [$from, $to])
                    ->where(function ($q) {
                        $q->where('discharge_reason', 'LIKE', '%abscond%')
                          ->orWhere('discharge_reason', 'LIKE', '%left without notice%')
                          ->orWhere('discharge_reason', 'LIKE', '%eloped%')
                          ->orWhere('discharge_note', 'LIKE', '%abscond%');
                    })
                    ->orderByDesc('discharge_date')
                    ->get();
                foreach ($abss as $adm) {
                    $p = $adm->patient;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => $adm->id,
                        'patient_id' => $adm->patient_id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => $p?->gender ?? 'N/A',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => Carbon::parse($adm->discharge_date)->format('Y-m-d H:i'),
                        'location' => $adm->bed?->wardRelation?->name ?? 'Ward',
                        'doctor_name' => $this->formatPersonName($adm->doctor),
                        'details' => $adm->discharge_reason . ($adm->discharge_note ? ' - ' . $adm->discharge_note : ''),
                    ];
                }

                break;

            case 'referrals':
                $title = 'Referrals & Outward Transfers';
                $external = SpecialistReferral::with(['patient.user', 'patient.hmo.scheme', 'referringDoctor.user'])
                    ->whereBetween('created_at', [$from, $to])
                    ->where('referral_type', 'external')
                    ->get();
                foreach ($external as $r) {
                    $p = $r->patient;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => 'ref_' . $r->id,
                        'patient_id' => $r->patient_id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => $p?->gender ?? 'N/A',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => $r->created_at->format('Y-m-d H:i'),
                        'location' => 'External Facility: ' . ($r->external_facility_name ?? 'Outward Transfer'),
                        'doctor_name' => $this->formatPersonName($r->referringDoctor?->user),
                        'details' => $r->reason ?? 'Specialist Referral',
                    ];
                }

                $transfers = AdmissionRequest::with(['patient.user', 'patient.hmo.scheme', 'bed.wardRelation', 'doctor'])
                    ->where('discharged', 1)
                    ->whereBetween('discharge_date', [$from, $to])
                    ->where(function ($q) {
                        $q->where('discharge_reason', 'LIKE', '%transfer%')
                          ->orWhere('discharge_reason', 'LIKE', '%higher level%')
                          ->orWhere('discharge_reason', 'LIKE', '%another facility%')
                          ->orWhere('discharge_reason', 'LIKE', '%referral%');
                    })
                    ->get();
                foreach ($transfers as $adm) {
                    $p = $adm->patient;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => 'tr_' . $adm->id,
                        'patient_id' => $adm->patient_id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => $p?->gender ?? 'N/A',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => Carbon::parse($adm->discharge_date)->format('Y-m-d H:i'),
                        'location' => $adm->bed?->wardRelation?->name ?? 'Ward Transfer',
                        'doctor_name' => $this->formatPersonName($adm->doctor),
                        'details' => $adm->discharge_reason,
                    ];
                }

                break;

            case 'normal_delivery':
                $title = 'Normal Deliveries (SVD / Vaginal)';
                $delRecords = DeliveryRecord::with(['patient.user', 'patient.hmo.scheme', 'deliveredBy'])
                    ->whereBetween('delivery_date', [$from, $to])
                    ->where(function ($q) {
                        $q->whereIn('type_of_delivery', ['svd', 'assisted_vaginal', 'vacuum', 'normal', 'vaginal'])
                          ->orWhere(function ($sub) {
                              $sub->where('type_of_delivery', 'NOT LIKE', '%cs%')
                                  ->where('type_of_delivery', 'NOT LIKE', '%caesarean%')
                                  ->where('type_of_delivery', 'NOT LIKE', '%cesarean%');
                          });
                    })
                    ->orderByDesc('delivery_date')
                    ->get();
                foreach ($delRecords as $dr) {
                    $p = $dr->patient;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => $dr->id,
                        'patient_id' => $p?->id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => 'Female',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => Carbon::parse($dr->delivery_date)->format('Y-m-d') . ($dr->delivery_time ? ' ' . $dr->delivery_time : ''),
                        'location' => 'Labour / Maternity Ward',
                        'doctor_name' => $dr->deliveredBy ? $this->formatPersonName($dr->deliveredBy) : 'Midwife / Doctor',
                        'details' => strtoupper(str_replace('_', ' ', $dr->type_of_delivery ?? 'Normal')) . ' | Babies: ' . ($dr->number_of_babies ?? 1) . ($dr->blood_loss_ml ? ' | EBL: ' . $dr->blood_loss_ml . 'ml' : ''),
                    ];
                }

                break;

            case 'cs_delivery':
                $title = 'Caesarean Section (CS) Deliveries';
                $csRecords = DeliveryRecord::with(['patient.user', 'patient.hmo.scheme', 'deliveredBy'])
                    ->whereBetween('delivery_date', [$from, $to])
                    ->where(function ($q) {
                        $q->whereIn('type_of_delivery', ['elective_cs', 'emergency_cs', 'cs'])
                          ->orWhere('type_of_delivery', 'LIKE', '%cs%')
                          ->orWhere('type_of_delivery', 'LIKE', '%caesarean%')
                          ->orWhere('type_of_delivery', 'LIKE', '%cesarean%');
                    })
                    ->orderByDesc('delivery_date')
                    ->get();
                foreach ($csRecords as $dr) {
                    $p = $dr->patient;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => $dr->id,
                        'patient_id' => $p?->id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => 'Female',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => Carbon::parse($dr->delivery_date)->format('Y-m-d') . ($dr->delivery_time ? ' ' . $dr->delivery_time : ''),
                        'location' => 'Theatre / Maternity Ward',
                        'doctor_name' => $dr->deliveredBy ? $this->formatPersonName($dr->deliveredBy) : 'Obstetric Surgeon',
                        'details' => strtoupper(str_replace('_', ' ', $dr->type_of_delivery ?? 'CS')) . ' | Babies: ' . ($dr->number_of_babies ?? 1) . ($dr->blood_loss_ml ? ' | EBL: ' . $dr->blood_loss_ml . 'ml' : ''),
                    ];
                }

                break;

            case 'surgeries':
                $title = 'Completed Surgical Procedures';
                $procs = Procedure::with(['patient.user', 'patient.hmo.scheme', 'procedureDefinition', 'requestedByUser'])
                    ->where('procedure_status', 'completed')
                    ->whereBetween('actual_end_time', [$from, $to])
                    ->whereHas('procedureDefinition', fn ($q) => $q->where('is_surgical', 1))
                    ->orderByDesc('actual_end_time')
                    ->get();
                foreach ($procs as $p) {
                    $patient = $p->patient;
                    $hmoInfo = $this->renderPatientHmo($patient);
                    $results[] = [
                        'id' => $p->id,
                        'patient_id' => $p->patient_id,
                        'patient_name' => $this->formatPersonName($patient?->user),
                        'file_no' => $patient?->file_no ?? 'N/A',
                        'gender' => $patient?->gender ?? 'N/A',
                        'age' => $patient?->dob ? Carbon::parse($patient->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => $p->actual_end_time ? Carbon::parse($p->actual_end_time)->format('Y-m-d H:i') : 'N/A',
                        'location' => $p->operating_room ?? 'Operating Theatre',
                        'doctor_name' => $this->formatPersonName($p->requestedByUser),
                        'details' => optional($p->procedureDefinition)->name ?? $p->free_form_name ?? 'Surgical Operation',
                    ];
                }

                break;

            case 'deaths':
                $title = 'Mortality Records';
                $deaths = DeathRecord::with(['patient.user', 'patient.hmo.scheme', 'doctor'])
                    ->whereBetween('date_of_death', [$from, $to])
                    ->orderByDesc('date_of_death')
                    ->get();
                foreach ($deaths as $d) {
                    $p = $d->patient;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => $d->id,
                        'patient_id' => $d->patient_id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => $p?->gender ?? 'N/A',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => Carbon::parse($d->date_of_death)->format('Y-m-d') . ($d->time_of_death ? ' ' . $d->time_of_death : ''),
                        'location' => 'Inpatient / Ward',
                        'doctor_name' => $d->doctor ? $this->formatPersonName($d->doctor) : 'Certifying Physician',
                        'details' => 'Cause: ' . ($d->cause_of_death_primary ?? $d->cause_of_death_description ?? 'N/A'),
                    ];
                }

                break;

            case 'corpses':
                $title = 'Morgue Received / In-Morgue Corpses';
                if (\Illuminate\Support\Facades\Schema::hasTable('morgue_admissions')) {
                    $morgueRecords = \App\Models\MorgueAdmission::with(['patient.user', 'patient.hmo.scheme'])
                        ->whereBetween('arrival_time', [$from, $to])
                        ->orderByDesc('arrival_time')
                        ->get();
                    foreach ($morgueRecords as $ma) {
                        $p = $ma->patient;
                        $hmoInfo = $this->renderPatientHmo($p);
                        $results[] = [
                            'id' => $ma->id,
                            'patient_id' => $ma->patient_id,
                            'patient_name' => $p ? $this->formatPersonName($p->user) : ($ma->deceased_name ?? 'Unidentified'),
                            'file_no' => $p?->file_no ?? ($ma->tag_number ?? 'Morgue Tag'),
                            'gender' => $ma->gender ?? $p?->gender ?? 'N/A',
                            'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                            'hmo_id' => $hmoInfo['hmo_id'],
                            'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                            'hmo_html' => $hmoInfo['hmo_html'],
                            'date' => Carbon::parse($ma->arrival_time)->format('Y-m-d H:i'),
                            'location' => 'Morgue / Mortuary',
                            'doctor_name' => 'Attending Pathologist / Mortuary Team',
                            'details' => 'Tag: ' . ($ma->tag_number ?? 'N/A') . ' | Status: ' . ucfirst($ma->status ?? 'Admitted'),
                        ];
                    }
                }

                break;

            case 'empty_beds':
                $title = 'Hospital Empty / Available Beds';
                $emptyBeds = Bed::with(['wardRelation'])
                    ->where('bed_status', 'available')
                    ->when($wardId, fn ($q) => $q->where('ward_id', $wardId))
                    ->orderBy('name')
                    ->get();
                foreach ($emptyBeds as $b) {
                    $results[] = [
                        'id' => $b->id,
                        'patient_id' => null,
                        'patient_name' => '<span class="badge badge-success text-white">Available Bed</span>',
                        'file_no' => '-',
                        'gender' => '-',
                        'age' => '-',
                        'hmo_id' => null,
                        'hmo_scheme_id' => null,
                        'hmo_html' => '<span class="text-muted">-</span>',
                        'date' => Carbon::parse($b->updated_at)->format('Y-m-d H:i'),
                        'location' => ($b->wardRelation?->name ?? 'Ward') . ' - Bed ' . $b->name,
                        'doctor_name' => 'Nursing Department',
                        'details' => 'Ready for Admission | Status: Available',
                    ];
                }

                break;

            case 'ward_patients':
            case 'inpatients':
            case 'ward':
            default:
                $wardIds = [];
                if ($request->filled('ward_ids')) {
                    $wardIds = array_filter(explode(',', $request->get('ward_ids')));
                } elseif ($wardId) {
                    $wardIds = [$wardId];
                }

                $targetWard = count($wardIds) === 1 ? Ward::find($wardIds[0]) : null;
                $title = count($wardIds) === 1 && $targetWard ? ('Active Inpatients: ' . $targetWard->name) : 'Active Inpatients: All Wards';
                $beds = Bed::with(['wardRelation', 'occupant.user', 'occupant.hmo.scheme'])
                    ->where('bed_status', 'occupied')
                    ->when(!empty($wardIds), fn ($q) => $q->whereIn('ward_id', $wardIds))
                    ->get();
                foreach ($beds as $b) {
                    $p = $b->occupant;
                    $hmoInfo = $this->renderPatientHmo($p);
                    $results[] = [
                        'id' => $b->id,
                        'patient_id' => $p?->id,
                        'patient_name' => $this->formatPersonName($p?->user),
                        'file_no' => $p?->file_no ?? 'N/A',
                        'gender' => $p?->gender ?? 'N/A',
                        'age' => $p?->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'date' => Carbon::parse($b->updated_at)->format('Y-m-d H:i'),
                        'location' => ($b->wardRelation?->name ?? 'Ward') . ' - Bed ' . $b->name,
                        'doctor_name' => 'Attending Nursing Team',
                        'details' => 'Currently Admitted in Bed ' . $b->name,
                    ];
                }

                break;
        }

        // Filter by HMO
        $hmoId = $request->get('hmo_id');
        if ($hmoId === 'cash') {
            $results = array_values(array_filter($results, fn ($r) => empty($r['hmo_id'])));
        } elseif (!empty($hmoId)) {
            $results = array_values(array_filter($results, fn ($r) => ($r['hmo_id'] ?? null) == $hmoId));
        }

        // Filter by Scheme
        $schemeId = $request->get('scheme_id');
        if (!empty($schemeId)) {
            $results = array_values(array_filter($results, fn ($r) => ($r['hmo_scheme_id'] ?? null) == $schemeId));
        }

        // Filter by Search (debounced search across all relevant fields)
        if ($request->filled('search')) {
            $searchLower = mb_strtolower(trim($request->get('search')));
            $results = array_values(array_filter($results, function ($r) use ($searchLower) {
                $haystack = mb_strtolower(
                    ($r['patient_name'] ?? '') . ' ' .
                    ($r['file_no'] ?? '') . ' ' .
                    ($r['doctor_name'] ?? '') . ' ' .
                    ($r['location'] ?? '') . ' ' .
                    ($r['details'] ?? '') . ' ' .
                    strip_tags($r['hmo_html'] ?? '')
                );

                return str_contains($haystack, $searchLower);
            }));
        }

        $totalRecords = count($results);
        $uniquePatientsCount = count(array_unique(array_filter(array_column($results, 'patient_id'))));
        if ($uniquePatientsCount === 0 && $totalRecords > 0) {
            $uniquePatientsCount = $totalRecords;
        }

        $perPage = max(5, min(100, (int) $request->query('per_page', 25)));
        $lastPage = (int) max(1, ceil($totalRecords / $perPage));
        $page = max(1, min((int) $request->query('page', 1), $lastPage));

        $paginatedRecords = array_slice($results, ($page - 1) * $perPage, $perPage);
        $fromIdx = $totalRecords > 0 ? (($page - 1) * $perPage + 1) : 0;
        $toIdx = min($page * $perPage, $totalRecords);

        // Fetch HMO and Scheme lists for dynamic filter population
        $hmos = Hmo::select('id', 'name', 'hmo_scheme_id')->where('status', 1)->orderBy('name')->get();
        $schemes = HmoScheme::select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'metric' => $metric,
            'title' => $title,
            'total_records' => $totalRecords,
            'unique_patients' => $uniquePatientsCount,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'from' => $fromIdx,
            'to' => $toIdx,
            'records' => $paginatedRecords,
            'hmos' => $hmos,
            'schemes' => $schemes,
        ]);
    }

    /**
     * Printable Official DNS Report View
     */
    public function printDnsReport(Request $request)
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : now()->endOfDay();
        $wardId = $request->get('ward_id');

        $report = $this->getDnsReportData($from, $to, $wardId);

        $site = appsettings();

        return view('admin.clinical_reports.dns_print', compact('report', 'site'));
    }
}
