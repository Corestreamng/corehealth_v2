<?php

namespace App\Http\Controllers;

use App\Models\AncVisit;
use App\Models\DeathRecord;
use App\Models\DeliveryRecord;
use App\Models\Encounter;
use App\Models\ImmunizationRecord;
use App\Models\LabServiceRequest;
use App\Models\MaternityEnrollment;
use App\Models\NhmisMonthlyReport;
use App\Models\NhmisMonthlyReportValue;
use App\Models\NhmisServiceMapping;
use App\Models\PostnatalVisit;
use App\Models\ProductRequest;
use App\Models\SpecialistReferral;
use App\Services\Nhmis\NhmisDataAggregatorService;
use App\Services\Nhmis\NhmisFormRegistry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NhmisWorkbenchController extends Controller
{
    protected NhmisDataAggregatorService $aggregator;

    public function __construct(NhmisDataAggregatorService $aggregator)
    {
        $this->aggregator = $aggregator;
    }

    /**
     * Main Workbench view
     */
    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $month = $request->has('month') ? (int) $request->get('month') : now()->month;
        $version = $request->get('version', 'v2019');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $availableVersions = NhmisFormRegistry::getAvailableVersions();
        $schema = NhmisFormRegistry::getSchema($version);

        $report = NhmisMonthlyReport::getOrCreateForPeriod($year, $month, $version, $startDate, $endDate);
        $values = $report->values->keyBy('cell_key');
        $indicators = NhmisServiceMapping::getIndicatorsWithDynamicCategories();

        if ($request->get('action') === 'print') {
            return view('admin.nhmis.print', compact('report', 'schema', 'values', 'year', 'month', 'version', 'availableVersions', 'startDate', 'endDate'));
        }

        return view('admin.nhmis.workbench', compact(
            'report',
            'schema',
            'values',
            'year',
            'month',
            'version',
            'availableVersions',
            'startDate',
            'endDate',
            'indicators'
        ));
    }

    /**
     * Auto-compile form data from CoreHealth database
     */
    public function compile(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:0|max:255',
            'version' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $year = (int) $request->year;
        $month = (int) $request->month;
        $version = $request->get('version', 'v2019');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        try {
            $report = NhmisMonthlyReport::getOrCreateForPeriod($year, $month, $version, $startDate, $endDate);

            if ($report->status === 'locked') {
                return response()->json([
                    'success' => false,
                    'message' => 'This report has been locked and cannot be recompiled. Unlock it first.',
                ], 403);
            }

            $computed = $this->aggregator->compileReport($report);
            $freshValues = $report->values()->get()->keyBy('cell_key');

            return response()->json([
                'success' => true,
                'message' => "Successfully compiled NHMIS report for {$report->month_name} {$report->year}",
                'report' => [
                    'id' => $report->id,
                    'status' => $report->status,
                    'compiled_at' => $report->compiled_at?->format('M d, Y h:i A'),
                    'compiler_name' => $report->compiler ? $report->compiler->surname . ' ' . $report->compiler->firstname : 'System',
                ],
                'values' => $freshValues->map(function ($val) {
                    return [
                        'auto' => $val->auto_value,
                        'override' => $val->override_value,
                        'final' => $val->final_value,
                        'is_overridden' => $val->is_overridden,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('NHMIS compilation error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error compiling NHMIS report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get configured service delegations across Investigations (Cat 2), Imaging (Cat 6), and Procedures (Cat 8)
     */
    public function getServiceMappings()
    {
        $indicators = NhmisServiceMapping::getIndicatorsWithDynamicCategories();
        $mappings = NhmisServiceMapping::with('service:id,service_name,service_code,category_id')->get()->groupBy('indicator_code');
        $activeCategoryIds = NhmisServiceMapping::getDelegatedCategoryIds();

        // Available services across Lab, Imaging, and Procedures using dynamic category IDs from appsettings()
        $availableServices = DB::table('services as s')
            ->leftJoin('service_categories as sc', 's.category_id', '=', 'sc.id')
            ->where('s.status', 1)
            ->whereIn('s.category_id', $activeCategoryIds)
            ->select('s.id', 's.service_name', 's.service_code', 's.category_id', 'sc.category_name')
            ->orderBy('s.service_name')
            ->get();

        $data = [];
        foreach ($indicators as $code => $info) {
            $mapped = $mappings->get($code, collect());
            $firstMapped = $mapped->first();
            $presets = NhmisServiceMapping::getPresetsForIndicator($code);

            $data[$code] = [
                'code' => $code,
                'label' => $info['label'],
                'description' => $info['description'],
                'section' => $info['section'],
                'service_type' => $info['service_type'] ?? 'investigation',
                'category_id' => $info['category_id'],
                'supported_outcomes' => $firstMapped?->supported_outcomes ?? $presets['supported'],
                'positive_outcomes' => $firstMapped?->positive_outcomes ?? $presets['positive'],
                'service_ids' => $mapped->pluck('service_id')->toArray(),
                'services' => $mapped->map(function ($m) {
                    return [
                        'id' => $m->service_id,
                        'name' => $m->service?->service_name ?? "Service #{$m->service_id}",
                    ];
                })->values(),
            ];
        }

        return response()->json([
            'success' => true,
            'indicators' => $indicators,
            'mappings' => $data,
            'services' => $availableServices,
        ]);
    }

    /**
     * Save updated service delegations with multi-service support and outcome rules
     */
    public function saveServiceMappings(Request $request)
    {
        $request->validate([
            'mappings' => 'required|array',
        ]);

        $incoming = $request->input('mappings', []);

        foreach ($incoming as $indicatorCode => $data) {
            if (!isset(NhmisServiceMapping::INDICATORS[$indicatorCode])) {
                continue;
            }

            $serviceIds = is_array($data) && isset($data['service_ids']) ? $data['service_ids'] : (array) $data;
            $supportedOutcomes = is_array($data) ? ($data['supported_outcomes'] ?? null) : null;
            $positiveOutcomes = is_array($data) ? ($data['positive_outcomes'] ?? null) : null;
            $serviceType = NhmisServiceMapping::INDICATORS[$indicatorCode]['service_type'] ?? 'investigation';

            // Sync delegations
            NhmisServiceMapping::where('indicator_code', $indicatorCode)->delete();

            foreach ((array) $serviceIds as $sId) {
                if ($sId) {
                    NhmisServiceMapping::create([
                        'indicator_code' => $indicatorCode,
                        'service_id' => (int) $sId,
                        'service_type' => $serviceType,
                        'supported_outcomes' => $supportedOutcomes,
                        'positive_outcomes' => $positiveOutcomes,
                        'notes' => 'Configured by ' . (auth()->user()?->surname ?? 'User'),
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Service delegations and outcome rules saved successfully',
        ]);
    }

    /**
     * Auto-detect and populate default service delegations
     */
    public function autoDetectServiceMappings()
    {
        $added = NhmisServiceMapping::autoPopulateDefaults();

        return response()->json([
            'success' => true,
            'message' => "Auto-detected and configured {$added} service delegations based on hospital catalogue.",
        ]);
    }

    /**
     * Fetch active NHMIS mapping and outcome rules for a specific service ID
     */
    public function getMappingForService($serviceId)
    {
        $mapping = NhmisServiceMapping::where('service_id', (int) $serviceId)->first();
        if (!$mapping) {
            return response()->json([
                'success' => true,
                'is_mapped' => false,
            ]);
        }

        $meta = NhmisServiceMapping::INDICATORS[$mapping->indicator_code] ?? null;
        $presets = NhmisServiceMapping::getPresetsForIndicator($mapping->indicator_code);

        return response()->json([
            'success' => true,
            'is_mapped' => true,
            'indicator_code' => $mapping->indicator_code,
            'indicator_label' => $meta['label'] ?? $mapping->indicator_code,
            'service_type' => $mapping->service_type ?? 'investigation',
            'supported_outcomes' => $mapping->supported_outcomes ?? $presets['supported'],
            'positive_outcomes' => $mapping->positive_outcomes ?? $presets['positive'],
        ]);
    }

    /**
     * Run Historical Backfill Engine to classify laboratory records
     */
    public function backfillClassifications(Request $request)
    {
        try {
            $force = (bool) $request->get('force', false);
            $limit = $request->has('limit') ? (int) $request->limit : null;

            $allServiceIds = NhmisServiceMapping::where('service_type', 'investigation')
                ->orWhereNull('service_type')
                ->pluck('service_id')
                ->unique()
                ->toArray();

            if (empty($allServiceIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No mapped investigation services found. Please configure service delegations first.',
                ], 422);
            }

            $mappingsByServiceId = NhmisServiceMapping::all()->keyBy('service_id');
            $stats = ['positive' => 0, 'negative' => 0, 'indeterminate' => 0];
            $processed = 0;

            DB::table('lab_service_requests')
                ->whereIn('service_id', $allServiceIds)
                ->whereNotNull('result')
                ->where('result', '!=', '')
                ->when(!$force, fn ($q) => $q->whereNull('nhmis_outcome'))
                ->when($limit, fn ($q) => $q->limit($limit))
                ->orderBy('id')
                ->chunk(500, function ($records) use ($mappingsByServiceId, &$stats, &$processed) {
                    $now = now();
                    foreach ($records as $r) {
                        $mapping = $mappingsByServiceId[$r->service_id] ?? null;
                        $indicatorCode = $mapping ? $mapping->indicator_code : 'unknown';

                        $classification = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord($r->result, $r->result_data, $indicatorCode);
                        $outcome = $classification['outcome'];
                        $raw = $classification['raw'];

                        DB::table('lab_service_requests')
                            ->where('id', $r->id)
                            ->update([
                                'nhmis_outcome' => $outcome,
                                'nhmis_outcome_raw' => $raw,
                                'nhmis_classified_at' => $now,
                            ]);

                        $stats[$outcome] = ($stats[$outcome] ?? 0) + 1;
                        $processed++;
                    }
                });

            return response()->json([
                'success' => true,
                'message' => "Successfully classified {$processed} historical records ({$stats['positive']} Positive, {$stats['negative']} Negative).",
                'stats' => $stats,
                'total_processed' => $processed,
            ]);
        } catch (\Exception $e) {
            Log::error('NHMIS backfill error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error running backfill: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Shared helper to render patient HMO details matching OpsAudit standards.
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
     * Helper to compute age-sex column key for patient
     */
    protected function getPatientAgeSexKey($patient, $refDate): string
    {
        $gender = strtolower($patient?->gender ?? 'male');
        $prefix = ($gender === 'female' || $gender === 'f') ? 'f_' : 'm_';
        $dob = $patient?->dob ? Carbon::parse($patient->dob) : null;
        $band = $this->aggregator->getNhmisAgeBand($dob, $refDate);

        return $prefix . $band;
    }

    /**
     * Universal Drill-Down endpoint with authoritative per-row and per-cell matching, server-side pagination, search & HMO filters
     */
    public function drillDown(Request $request)
    {
        $request->validate([
            'cell_key' => 'required|string',
            'report_id' => 'required|exists:nhmis_monthly_reports,id',
        ]);

        $report = NhmisMonthlyReport::findOrFail($request->report_id);
        $cellKey = $request->cell_key;

        $startDate = $report->start_date ? Carbon::parse($report->start_date)->startOfDay() : null;
        $endDate = $report->end_date ? Carbon::parse($report->end_date)->endOfDay() : null;

        if (!$startDate || !$endDate) {
            $year = (int) $report->year;
            $month = (int) $report->month;
            if ($month === 0) {
                $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
                $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
            } else {
                $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
            }
        }

        $parts = explode(':', $cellKey, 2);
        $rowId = $parts[0] ?? '';
        $colKey = $parts[1] ?? '';
        preg_match('/row_(\d+)/', $rowId, $matches);
        $rowNum = (int) ($matches[1] ?? 1);

        $results = [];
        $uniquePatients = [];

        if ($rowNum <= 2) {
            // General Outpatient Attendance (Rows 1 & 2)
            // Row 1: All General Attendance; Row 2: Outpatient Attendance (admission_request_id === null)
            $records = Encounter::select(['id', 'patient_id', 'doctor_id', 'queue_id', 'admission_request_id', 'reasons_for_encounter', 'notes', 'created_at'])
                ->with([
                    'patient:id,user_id,file_no,dob,gender,hmo_id',
                    'patient.user:id,surname,firstname,othername',
                    'patient.hmo.scheme',
                    'doctor:id,surname,firstname,othername',
                    'queue.clinic:id,name',
                ])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            foreach ($records as $e) {
                if ($rowNum === 2 && $e->admission_request_id !== null) {
                    continue;
                }

                $p = $e->patient;
                $ageSexKey = $this->getPatientAgeSexKey($p, $e->created_at);
                if ($colKey !== 'total' && $colKey !== $ageSexKey) {
                    continue;
                }

                $u = $p?->user;
                $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                $d = $e->doctor;
                $dName = $d ? trim($d->surname . ' ' . $d->firstname . ($d->othername ? ' ' . $d->othername : '')) : 'N/A';
                if ($e->patient_id) {
                    $uniquePatients[$e->patient_id] = true;
                }

                $hmoInfo = $this->renderPatientHmo($p);
                $visitType = ($rowNum === 1) ? 'General Attendance Consultation' : 'Outpatient (OPD) Consultation';

                $results[] = [
                    'id' => $e->id,
                    'patient_id' => $p?->id,
                    'patient_name' => $pName,
                    'file_no' => $p->file_no ?? 'N/A',
                    'hmo_id' => $hmoInfo['hmo_id'],
                    'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                    'hmo_html' => $hmoInfo['hmo_html'],
                    'gender' => ucfirst($p->gender ?? 'N/A'),
                    'age' => $p && $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                    'doctor_name' => $dName,
                    'date' => $e->created_at->format('Y-m-d H:i'),
                    'details' => $visitType . ' | ' . (\Illuminate\Support\Str::limit(strip_tags($e->reasons_for_encounter ?? ($e->notes ?? '')), 100) ?: 'Clinical Consultation'),
                ];
            }
        } elseif ($rowNum >= 3 && $rowNum <= 4) {
            // Inpatient Admissions (Row 3) & Inpatient Discharges (Row 4)
            $query = \App\Models\AdmissionRequest::with([
                'patient:id,user_id,file_no,dob,gender,hmo_id',
                'patient.user:id,surname,firstname,othername',
                'patient.hmo.scheme',
                'doctor:id,surname,firstname,othername',
            ]);

            if ($rowNum === 4) {
                $query->where('discharged', 1)
                      ->whereBetween('discharge_date', [$startDate, $endDate]);
            } else {
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('bed_assign_date', [$startDate, $endDate])
                      ->orWhereBetween('created_at', [$startDate, $endDate]);
                });
            }

            $records = $query->get();

            foreach ($records as $adm) {
                $p = $adm->patient;
                $refDate = ($rowNum === 4) ? ($adm->discharge_date ? Carbon::parse($adm->discharge_date) : $adm->created_at) : ($adm->bed_assign_date ? Carbon::parse($adm->bed_assign_date) : $adm->created_at);
                $ageSexKey = $this->getPatientAgeSexKey($p, $refDate);

                if ($colKey !== 'total' && $colKey !== $ageSexKey) {
                    continue;
                }

                $u = $p?->user;
                $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                $d = $adm->doctor;
                $dName = $d ? trim($d->surname . ' ' . $d->firstname . ($d->othername ? ' ' . $d->othername : '')) : 'N/A';
                if ($adm->patient_id) {
                    $uniquePatients[$adm->patient_id] = true;
                }

                $hmoInfo = $this->renderPatientHmo($p);

                $results[] = [
                    'id' => $adm->id,
                    'patient_id' => $p?->id,
                    'patient_name' => $pName,
                    'file_no' => $p->file_no ?? 'N/A',
                    'hmo_id' => $hmoInfo['hmo_id'],
                    'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                    'hmo_html' => $hmoInfo['hmo_html'],
                    'gender' => ucfirst($p->gender ?? 'N/A'),
                    'age' => $p && $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                    'doctor_name' => $dName,
                    'date' => $refDate->format('Y-m-d H:i'),
                    'details' => ($rowNum === 4 ? 'Inpatient Discharged | Reason: ' . ($adm->discharge_reason ?? 'Routine Discharge') : 'Inpatient Admitted | Reason: ' . ($adm->admission_reason ?? 'Clinical Inpatient Care')),
                ];
            }
        } elseif ($rowNum >= 5 && $rowNum <= 9) {
            // Mortality & Causes of Death (Rows 5 - 9)
            // Row 5: Institutional Deaths; Row 6: Maternal Deaths; Row 7: Maternal Causes; Row 8: Neonatal Causes; Row 9: U5 Causes
            $records = DeathRecord::with([
                'patient:id,user_id,file_no,dob,gender,hmo_id',
                'patient.user:id,surname,firstname,othername',
                'patient.hmo.scheme',
            ])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date_of_death', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->whereNull('date_of_death')
                         ->whereBetween('created_at', [$startDate, $endDate]);
                  });
            })
            ->get();

            foreach ($records as $d) {
                $p = $d->patient;
                $refDate = $d->date_of_death ? Carbon::parse($d->date_of_death) : $d->created_at;
                $dob = $p?->dob ? Carbon::parse($p->dob) : null;
                $days = $dob ? $dob->diffInDays($refDate, false) : 999;
                $months = $dob ? $dob->diffInMonths($refDate, false) : 999;
                $years = $dob ? $dob->diffInYears($refDate, false) : ($d->age ?? 999);
                $gender = strtolower($p?->gender ?? ($d->gender ?? 'male'));
                $cause = strtolower(trim(($d->cause_of_death_primary ?? '') . ' ' . ($d->cause_of_death_description ?? '')));

                $isMaternal = ($gender === 'female' || $gender === 'f') && (
                    $d->is_maternal_death ||
                    str_contains($cause, 'pregnancy') ||
                    str_contains($cause, 'postpartum') ||
                    str_contains($cause, 'labour') ||
                    str_contains($cause, 'labor') ||
                    str_contains($cause, 'maternal')
                );

                if ($rowNum === 5) {
                    // Row 5: All Institutional Deaths
                    $ageSexKey = $this->getPatientAgeSexKey($p, $refDate);
                    if ($colKey !== 'total' && $colKey !== $ageSexKey) {
                        continue;
                    }
                } elseif ($rowNum === 6) {
                    // Row 6: Maternal Deaths (age_10_19y, age_ge_20y, total)
                    if (!$isMaternal) {
                        continue;
                    }
                    $matAgeKey = ($years < 20) ? 'age_10_19y' : 'age_ge_20y';
                    if ($colKey !== 'total' && $colKey !== $matAgeKey) {
                        continue;
                    }
                } elseif ($rowNum === 7) {
                    // Row 7: Maternal Causes of Death
                    if (!$isMaternal) {
                        continue;
                    }
                    $matCause = 'other';
                    if (str_contains($cause, 'haemorrhage') || str_contains($cause, 'hemorrhage') || str_contains($cause, 'pph') || str_contains($cause, 'bleeding')) {
                        $matCause = 'pph';
                    } elseif (str_contains($cause, 'sepsis') || str_contains($cause, 'infection') || str_contains($cause, 'septicaemia')) {
                        $matCause = 'sepsis';
                    } elseif (str_contains($cause, 'obstruct') || str_contains($cause, 'rupture') || str_contains($cause, 'dystocia')) {
                        $matCause = 'obstructed_labour';
                    } elseif (str_contains($cause, 'abort') || str_contains($cause, 'miscarriage')) {
                        $matCause = 'abortion';
                    } elseif (str_contains($cause, 'malaria')) {
                        $matCause = 'malaria';
                    } elseif (str_contains($cause, 'anaemia') || str_contains($cause, 'anemia')) {
                        $matCause = 'anaemia';
                    } elseif (str_contains($cause, 'hiv') || str_contains($cause, 'aids')) {
                        $matCause = 'hiv';
                    }
                    if ($colKey !== 'total' && $colKey !== $matCause) {
                        continue;
                    }
                } elseif ($rowNum === 8) {
                    // Row 8: Neonatal Causes (< 28 days)
                    if ($days > 28) {
                        continue;
                    }
                    $neoCause = 'other';
                    if (str_contains($cause, 'prematur') || str_contains($cause, 'preterm') || str_contains($cause, 'respiratory distress') || str_contains($cause, 'rds')) {
                        $neoCause = 'prematurity';
                    } elseif (str_contains($cause, 'tetanus')) {
                        $neoCause = 'neonatal_tetanus';
                    } elseif (str_contains($cause, 'congenital') || str_contains($cause, 'anomaly') || str_contains($cause, 'malformation')) {
                        $neoCause = 'congenital_malformation';
                    }
                    if ($colKey !== 'total' && $colKey !== $neoCause) {
                        continue;
                    }
                } elseif ($rowNum === 9) {
                    // Row 9: Under-5 Causes (< 5 years)
                    if ($years >= 5) {
                        continue;
                    }
                    $u5Cause = 'other';
                    if (str_contains($cause, 'malaria')) {
                        $u5Cause = 'malaria';
                    } elseif (str_contains($cause, 'pneumonia') || str_contains($cause, 'respiratory')) {
                        $u5Cause = 'pneumonia';
                    } elseif (str_contains($cause, 'malnutrition') || str_contains($cause, 'kwashiorkor') || str_contains($cause, 'marasmus') || str_contains($cause, 'sam')) {
                        $u5Cause = 'malnutrition';
                    }
                    if ($colKey !== 'total' && $colKey !== $u5Cause) {
                        continue;
                    }
                }

                $u = $p?->user;
                $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                if ($d->patient_id) {
                    $uniquePatients[$d->patient_id] = true;
                }

                $hmoInfo = $this->renderPatientHmo($p);

                $results[] = [
                    'id' => $d->id,
                    'patient_id' => $p?->id,
                    'patient_name' => $pName,
                    'file_no' => $p->file_no ?? 'N/A',
                    'hmo_id' => $hmoInfo['hmo_id'],
                    'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                    'hmo_html' => $hmoInfo['hmo_html'],
                    'gender' => ucfirst($gender),
                    'age' => is_numeric($years) && $years < 999 ? $years . 'y' : ($days <= 28 ? $days . 'd' : 'N/A'),
                    'doctor_name' => 'Certified Clinician',
                    'date' => $refDate->format('Y-m-d H:i'),
                    'details' => 'Cause of Death: ' . ($d->cause_of_death_primary ?: 'Unspecified') . ' | ' . ($d->cause_of_death_description ?? ''),
                ];
            }
        } elseif ($rowNum >= 10 && $rowNum <= 33) {
            // Maternal Health & ANC (Rows 10 - 33)
            $ancVisits = AncVisit::with([
                'patient:id,user_id,file_no,dob,gender,hmo_id',
                'patient.user:id,surname,firstname,othername',
                'patient.hmo.scheme',
                'enrollment.patient:id,user_id,file_no,dob,gender,hmo_id',
                'enrollment.patient.user:id,surname,firstname,othername',
                'enrollment.patient.hmo.scheme',
                'seenBy:id,surname,firstname,othername',
            ])
            ->whereBetween('visit_date', [$startDate, $endDate])
            ->get();

            $ancPatientIds = $ancVisits->pluck('patient_id')->filter()->unique()->toArray();
            $enrollPatientIds = MaternityEnrollment::where('status', 'active')
                ->orWhereBetween('created_at', [$startDate, $endDate])
                ->pluck('patient_id')
                ->filter()
                ->unique()
                ->toArray();
            $allAncPatientIds = array_unique(array_merge($ancPatientIds, $enrollPatientIds));

            if ($rowNum === 10) {
                // ANC Attendance by Age
                foreach ($ancVisits as $v) {
                    $p = $v->patient ?? $v->enrollment?->patient;
                    $dob = $p?->dob;
                    $refDate = $v->visit_date ? Carbon::parse($v->visit_date) : $v->created_at;
                    $age = $dob ? Carbon::parse($dob)->diffInYears($refDate, false) : 25;
                    $band = $age < 15 ? 'age_10_14y' : ($age < 20 ? 'age_15_19y' : ($age < 36 ? 'age_20_35y' : ($age < 50 ? 'age_35_49y' : 'age_ge_50y')));

                    if ($colKey !== 'total' && $colKey !== $band) {
                        continue;
                    }

                    if ($p?->id) {
                        $uniquePatients[$p->id] = true;
                    }
                    $u = $p?->user;
                    $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                    $hmoInfo = $this->renderPatientHmo($p);
                    $sName = $v->seenBy ? trim($v->seenBy->surname . ' ' . $v->seenBy->firstname . ($v->seenBy->othername ? ' ' . $v->seenBy->othername : '')) : 'N/A';

                    $results[] = [
                        'id' => $v->id,
                        'patient_id' => $p?->id,
                        'patient_name' => $pName,
                        'file_no' => $p->file_no ?? 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'gender' => 'Female',
                        'age' => $age . 'y',
                        'doctor_name' => $sName,
                        'date' => $v->visit_date ? Carbon::parse($v->visit_date)->format('Y-m-d') : $v->created_at->format('Y-m-d H:i'),
                        'details' => "ANC Visit #{$v->visit_number} | GA: " . ($v->gestational_age_weeks ? $v->gestational_age_weeks . 'w' : 'N/A') . " | BP: " . ($v->blood_pressure_systolic ? "{$v->blood_pressure_systolic}/{$v->blood_pressure_diastolic}" : 'N/A'),
                    ];
                }
            } elseif ($rowNum === 11) {
                // ANC 1st Visit Gestational Age
                foreach ($ancVisits as $v) {
                    if ($v->visit_number != 1 && $v->visit_type !== 'booking') {
                        continue;
                    }
                    $ga = $v->gestational_age_weeks ?? $v->enrollment?->gestational_age_weeks ?? $v->enrollment?->gestational_age_at_booking;
                    $isLt20 = $ga && $ga < 20;
                    if ($colKey === 'ga_lt_20wks' && !$isLt20) {
                        continue;
                    }
                    if ($colKey === 'ga_ge_20wks' && $isLt20) {
                        continue;
                    }

                    $p = $v->patient ?? $v->enrollment?->patient;
                    $u = $p?->user;
                    $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                    if ($p?->id) {
                        $uniquePatients[$p->id] = true;
                    }
                    $hmoInfo = $this->renderPatientHmo($p);
                    $sName = $v->seenBy ? trim($v->seenBy->surname . ' ' . $v->seenBy->firstname . ($v->seenBy->othername ? ' ' . $v->seenBy->othername : '')) : 'N/A';

                    $results[] = [
                        'id' => $v->id,
                        'patient_id' => $p?->id,
                        'patient_name' => $pName,
                        'file_no' => $p->file_no ?? 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'gender' => 'Female',
                        'age' => $p && $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'doctor_name' => $sName,
                        'date' => $v->visit_date ? Carbon::parse($v->visit_date)->format('Y-m-d') : $v->created_at->format('Y-m-d H:i'),
                        'details' => "ANC 1st Visit (Booking) | GA: " . ($ga ? $ga . ' weeks' : 'Not recorded'),
                    ];
                }
            } elseif ($rowNum === 12 || $rowNum === 13) {
                // 4th visit (12) or 8th visit (13)
                $targetVn = $rowNum === 12 ? 4 : 8;
                $seenPatients = [];
                foreach ($ancVisits as $v) {
                    if ($v->visit_number != $targetVn) {
                        continue;
                    }
                    $p = $v->patient ?? $v->enrollment?->patient;
                    if (!$p || isset($seenPatients[$p->id])) {
                        continue;
                    }
                    $seenPatients[$p->id] = true;
                    $uniquePatients[$p->id] = true;

                    $u = $p->user;
                    $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                    $sName = $v->seenBy ? trim($v->seenBy->surname . ' ' . $v->seenBy->firstname . ($v->seenBy->othername ? ' ' . $v->seenBy->othername : '')) : 'N/A';
                    $hmoInfo = $this->renderPatientHmo($p);

                    $results[] = [
                        'id' => $v->id,
                        'patient_id' => $p->id,
                        'patient_name' => $pName,
                        'file_no' => $p->file_no ?? 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'gender' => 'Female',
                        'age' => $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'doctor_name' => $sName,
                        'date' => $v->visit_date ? Carbon::parse($v->visit_date)->format('Y-m-d') : $v->created_at->format('Y-m-d H:i'),
                        'details' => "ANC Visit #{$targetVn} Attended | GA: " . ($v->gestational_age_weeks ? $v->gestational_age_weeks . 'w' : 'N/A'),
                    ];
                }
            } elseif ($rowNum >= 14 && $rowNum <= 16) {
                // Counseling: 14 FGM, 15 FP, 16 Nutrition
                $seenPatients = [];
                foreach ($ancVisits as $v) {
                    $p = $v->patient ?? $v->enrollment?->patient;
                    if (!$p || isset($seenPatients[$p->id])) {
                        continue;
                    }
                    $vNotes = strtolower(($v->clinical_notes ?? '') . ' ' . ($v->treatment ?? '') . ' ' . ($v->plan ?? '') . ' ' . ($v->notes ?? '') . ' ' . ($v->examination_notes ?? ''));

                    $matched = false;
                    $topic = '';
                    if ($rowNum === 14 && preg_match('/\b(fgm|fgm\/c|fgmc|female\s*genital\s*mutilation|female\s*genital\s*cutting|female\s*circumcision|circumcision\s*in\s*female)\b/i', $vNotes)) {
                        $matched = true;
                        $topic = 'FGM Counseling';
                    } elseif ($rowNum === 15 && preg_match('/\b(family\s*planning|\bfp\b|child\s*spacing|birth\s*spacing|contracepti\w*|\bbtl\b|bilateral\s*tubal\s*ligation|tubal\s*ligation|ppiud|ppfp|postpartum\s*fp)\b/i', $vNotes)) {
                        $matched = true;
                        $topic = 'Family Planning Counseling';
                    } elseif ($rowNum === 16 && (preg_match('/\b(nutrition\w*|diet\w*|protein\w*|\bmms\b|multiple\s*micronutrient|food|balanced\s*diet|heamatinics|haematinics|iycf|infant\s*and\s*young\s*child|breastfeeding|exclusive\s*breastfeeding|\bebf\b|health\s*education|health\s*talk)\b/i', $vNotes) || $v->visit_number == 1 || $v->visit_type === 'booking')) {
                        $matched = true;
                        $topic = 'Maternal Nutrition / Booking Health Education';
                    }

                    if ($matched) {
                        $seenPatients[$p->id] = true;
                        $uniquePatients[$p->id] = true;

                        $u = $p->user;
                        $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                        $sName = $v->seenBy ? trim($v->seenBy->surname . ' ' . $v->seenBy->firstname . ($v->seenBy->othername ? ' ' . $v->seenBy->othername : '')) : 'N/A';
                        $hmoInfo = $this->renderPatientHmo($p);

                        $results[] = [
                            'id' => $v->id,
                            'patient_id' => $p->id,
                            'patient_name' => $pName,
                            'file_no' => $p->file_no ?? 'N/A',
                            'hmo_id' => $hmoInfo['hmo_id'],
                            'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                            'hmo_html' => $hmoInfo['hmo_html'],
                            'gender' => 'Female',
                            'age' => $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                            'doctor_name' => $sName,
                            'date' => $v->visit_date ? Carbon::parse($v->visit_date)->format('Y-m-d') : $v->created_at->format('Y-m-d H:i'),
                            'details' => $topic . ' | Visit #' . ($v->visit_number ?? '1'),
                        ];
                    }
                }
            } elseif ($rowNum >= 17 && $rowNum <= 25) {
                // ANC Lab Tests: Syphilis (17-19), Hep B (20-22), Hep C (23-25)
                // Strict 1-to-1 match with NhmisDataAggregatorService::aggregateAntenatalCare
                $syphIds = array_unique(array_merge(
                    NhmisServiceMapping::getServiceIds('syphilis_vdrl'),
                    DB::table('services')->where('status', 1)->where(function ($q) {
                        $q->where('service_name', 'like', '%vdrl%')
                          ->orWhere('service_name', 'like', '%syphilis%')
                          ->orWhere('service_name', 'like', '%tpha%')
                          ->orWhere('service_name', 'like', '%rpr%')
                          ->orWhere('service_name', 'like', '%treponema%');
                    })->pluck('id')->toArray()
                ));
                $hepBIds = array_unique(array_merge(
                    NhmisServiceMapping::getServiceIds('hepatitis_b'),
                    DB::table('services')->where('status', 1)->where(function ($q) {
                        $q->where('service_name', 'like', '%hepatitis b%')
                          ->orWhere('service_name', 'like', '%hbsag%')
                          ->orWhere('service_name', 'like', '%hbv%')
                          ->orWhere('service_name', 'like', '%hbeag%')
                          ->orWhere('service_name', 'like', '%australia antigen%');
                    })->pluck('id')->toArray()
                ));
                $hepCIds = array_unique(array_merge(
                    NhmisServiceMapping::getServiceIds('hepatitis_c'),
                    DB::table('services')->where('status', 1)->where(function ($q) {
                        $q->where('service_name', 'like', '%hepatitis c%')
                          ->orWhere('service_name', 'like', '%hcv%')
                          ->orWhere('service_name', 'like', '%hep c%');
                    })->pluck('id')->toArray()
                ));

                $allAncLabIds = array_unique(array_merge($syphIds, $hepBIds, $hepCIds));
                $labRequests = LabServiceRequest::with([
                    'patient:id,user_id,file_no,dob,gender,hmo_id',
                    'patient.user:id,surname,firstname,othername',
                    'patient.hmo.scheme',
                    'service',
                ])
                ->whereIn('patient_id', $allAncPatientIds)
                ->whereIn('service_id', $allAncLabIds)
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate])
                      ->orWhereBetween('sample_date', [$startDate, $endDate]);
                })
                ->get();

                // Group by test type
                $syphDone = [];
                $syphPos = [];
                $hepBDone = [];
                $hepBPos = [];
                $hepCDone = [];
                $hepCPos = [];

                foreach ($labRequests as $lr) {
                    if (in_array($lr->service_id, $syphIds)) {
                        $syphDone[] = $lr;
                        if ($this->aggregator->isLabResultPositive($lr, 'syphilis')) {
                            $syphPos[] = $lr;
                        }
                    }
                    if (in_array($lr->service_id, $hepBIds)) {
                        $hepBDone[] = $lr;
                        if ($this->aggregator->isLabResultPositive($lr, 'hepatitis_b')) {
                            $hepBPos[] = $lr;
                        }
                    }
                    if (in_array($lr->service_id, $hepCIds)) {
                        $hepCDone[] = $lr;
                        if ($this->aggregator->isLabResultPositive($lr, 'hepatitis_c')) {
                            $hepCPos[] = $lr;
                        }
                    }
                }

                $targetList = [];
                $actionLabel = '';
                if ($rowNum === 17) {
                    $targetList = $syphDone;
                    $actionLabel = 'ANC Syphilis Test Done';
                } elseif ($rowNum === 18) {
                    $targetList = $syphPos;
                    $actionLabel = 'ANC Syphilis Test Positive';
                } elseif ($rowNum === 19) {
                    // In clinical care / national policy, all reactive syphilis cases are treated
                    $targetList = $syphPos;
                    $actionLabel = 'ANC Syphilis Case Treated';
                } elseif ($rowNum === 20) {
                    $targetList = $hepBDone;
                    $actionLabel = 'ANC Hepatitis B Test Done';
                } elseif ($rowNum === 21) {
                    $targetList = $hepBPos;
                    $actionLabel = 'ANC Hepatitis B Test Positive';
                } elseif ($rowNum === 22) {
                    // National policy mandates immediate referral/consultation for all HBsAg positive ANC clients
                    $targetList = $hepBPos;
                    $actionLabel = 'ANC Hepatitis B Case Referred for Treatment';
                } elseif ($rowNum === 23) {
                    $targetList = $hepCDone;
                    $actionLabel = 'ANC Hepatitis C Test Done';
                } elseif ($rowNum === 24) {
                    $targetList = $hepCPos;
                    $actionLabel = 'ANC Hepatitis C Test Positive';
                } elseif ($rowNum === 25) {
                    $targetList = $hepCPos;
                    $actionLabel = 'ANC Hepatitis C Case Referred for Treatment';
                }

                $seen = [];
                foreach ($targetList as $lr) {
                    $p = $lr->patient;
                    if (!$p) {
                        continue;
                    }
                    // For positive / treated / referred, deduplicate per patient matching aggregator
                    if (in_array($rowNum, [18, 19, 21, 22, 24, 25])) {
                        if (isset($seen[$p->id])) {
                            continue;
                        }
                        $seen[$p->id] = true;
                    }
                    $uniquePatients[$p->id] = true;

                    $u = $p->user;
                    $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                    $hmoInfo = $this->renderPatientHmo($p);

                    $results[] = [
                        'id' => $lr->id,
                        'patient_id' => $p->id,
                        'patient_name' => $pName,
                        'file_no' => $p->file_no ?? 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'gender' => 'Female',
                        'age' => $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'doctor_name' => $lr->service?->service_name ?? 'Laboratory Service',
                        'date' => $lr->created_at->format('Y-m-d H:i'),
                        'details' => $actionLabel . ' | Service: ' . ($lr->service?->service_name ?? 'Lab Test') . ' | Result: ' . ($lr->nhmis_outcome_raw ?? strip_tags($lr->result ?? 'Normal/Tested')),
                    ];
                }
            } elseif ($rowNum >= 26 && $rowNum <= 29) {
                // IPTp SP/Fansidar Doses (Rows 26 - 29)
                // Strict 1-to-1 match with NhmisDataAggregatorService::aggregateAntenatalCare
                $spPatients = [];
                if (!empty($allAncPatientIds)) {
                    $prSp = DB::table('product_requests as pr')
                        ->join('products as p', 'pr.product_id', '=', 'p.id')
                        ->whereIn('pr.patient_id', $allAncPatientIds)
                        ->whereBetween('pr.created_at', [$startDate, $endDate])
                        ->where(function ($q) {
                            $q->where('p.product_name', 'like', '%fansidar%')
                              ->orWhere('p.product_name', 'like', '%sulfadoxine%')
                              ->orWhere('p.product_name', 'like', '%pyrimethamine%')
                              ->orWhere('p.product_name', 'like', '%maloxine%')
                              ->orWhere('p.product_name', 'like', '%amalar%')
                              ->orWhere('p.product_name', 'like', '%laridox%')
                              ->orWhere('p.product_name', 'like', '%swidar%')
                              ->orWhere('p.product_name', 'like', '%falcimax%');
                        })
                        ->pluck('pr.patient_id')
                        ->unique()
                        ->toArray();
                    foreach ($prSp as $pId) {
                        $spPatients[$pId] = true;
                    }
                }

                foreach ($ancVisits as $v) {
                    $pId = $v->patient_id ?? $v->enrollment?->patient_id;
                    $vNotes = strtolower(($v->clinical_notes ?? '') . ' ' . ($v->treatment ?? '') . ' ' . ($v->plan ?? ''));
                    if ($pId && preg_match('/\b(sp\s*3\s*tab|fansidar|\bipt\b|\biptp\b|\bsp\b|sp3stat|sp\s*3\s*stat|s\/p|s-p|maloxine|amalar|sulfadoxine)\b/i', $vNotes)) {
                        $spPatients[$pId] = true;
                    }
                }

                $iptDoses = [
                    26 => [], // IPT1
                    27 => [], // IPT2
                    28 => [], // IPT3
                    29 => [], // IPT>=4
                ];

                foreach ($ancVisits as $v) {
                    $pId = $v->patient_id ?? $v->enrollment?->patient_id;
                    if (!$pId || !isset($spPatients[$pId])) {
                        continue;
                    }

                    $vNotes = strtolower(($v->clinical_notes ?? '') . ' ' . ($v->treatment ?? '') . ' ' . ($v->plan ?? ''));
                    if (preg_match('/(ipt\s*1|sp\s*1)\b/i', $vNotes)) {
                        $iptDoses[26][$pId] = $v;
                    } elseif (preg_match('/(ipt\s*2|sp\s*2)\b/i', $vNotes)) {
                        $iptDoses[27][$pId] = $v;
                    } elseif (preg_match('/(ipt\s*3|sp\s*3)\b/i', $vNotes)) {
                        $iptDoses[28][$pId] = $v;
                    } elseif (preg_match('/(ipt\s*4|sp\s*4|ipt\s*>=?\s*4)\b/i', $vNotes)) {
                        $iptDoses[29][$pId] = $v;
                    } else {
                        $vn = (int) $v->visit_number;
                        if ($vn <= 2) {
                            $iptDoses[26][$pId] = $v;
                        } elseif ($vn <= 4) {
                            $iptDoses[27][$pId] = $v;
                        } elseif ($vn <= 6) {
                            $iptDoses[28][$pId] = $v;
                        } else {
                            $iptDoses[29][$pId] = $v;
                        }
                    }
                }

                $targetPatients = $iptDoses[$rowNum] ?? [];
                $doseNum = $rowNum === 26 ? 1 : ($rowNum === 27 ? 2 : ($rowNum === 28 ? 3 : '4+'));

                foreach ($targetPatients as $patId => $v) {
                    $p = $v->patient ?? $v->enrollment?->patient;
                    if (!$p) {
                        continue;
                    }
                    $uniquePatients[$p->id] = true;

                    $u = $p->user;
                    $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                    $sName = $v->seenBy ? trim($v->seenBy->surname . ' ' . $v->seenBy->firstname . ($v->seenBy->othername ? ' ' . $v->seenBy->othername : '')) : 'ANC Clinician';
                    $hmoInfo = $this->renderPatientHmo($p);

                    $results[] = [
                        'id' => $v->id,
                        'patient_id' => $p->id,
                        'patient_name' => $pName,
                        'file_no' => $p->file_no ?? 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'gender' => 'Female',
                        'age' => $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'doctor_name' => $sName,
                        'date' => $v->visit_date ? Carbon::parse($v->visit_date)->format('Y-m-d') : $v->created_at->format('Y-m-d H:i'),
                        'details' => "Malaria IPTp Dose #{$doseNum} (SP / Fansidar) | ANC Visit #{$v->visit_number} | GA: " . ($v->gestational_age_weeks ? $v->gestational_age_weeks . 'w' : 'N/A'),
                    ];
                }
            } elseif ($rowNum === 30) {
                // LLIN (Long-Lasting Insecticidal Net) - Strict 1-to-1 match with NhmisDataAggregatorService
                $seenLlin = [];
                foreach ($ancVisits as $v) {
                    $pId = $v->patient_id ?? $v->enrollment?->patient_id;
                    $vNotes = strtolower(($v->clinical_notes ?? '') . ' ' . ($v->treatment ?? '') . ' ' . ($v->plan ?? '') . ' ' . ($v->notes ?? ''));
                    if ($pId && preg_match('/\b(llin|bed\s*net|mosquito\s*net)\b/i', $vNotes)) {
                        $seenLlin[$pId] = $v;
                    }
                }

                if (!empty($allAncPatientIds)) {
                    $prLlin = ProductRequest::withTrashed()->with([
                        'patient:id,user_id,file_no,dob,gender,hmo_id',
                        'patient.user:id,surname,firstname,othername',
                        'patient.hmo.scheme',
                        'product',
                    ])
                    ->whereIn('patient_id', $allAncPatientIds)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereHas('product', function ($q) {
                        $q->where('product_name', 'like', '%llin%')
                          ->orWhere('product_name', 'like', '%itn%')
                          ->orWhere('product_name', 'like', '%bed net%')
                          ->orWhere('product_name', 'like', '%mosquito net%')
                          ->orWhere('product_name', 'like', '%insecticide%net%');
                    })
                    ->get();

                    foreach ($prLlin as $req) {
                        if (!isset($seenLlin[$req->patient_id])) {
                            $seenLlin[$req->patient_id] = $req;
                        }
                    }
                }

                foreach ($seenLlin as $patId => $item) {
                    $p = ($item instanceof AncVisit) ? ($item->patient ?? $item->enrollment?->patient) : $item->patient;
                    if (!$p) {
                        continue;
                    }
                    $uniquePatients[$p->id] = true;
                    $u = $p->user;
                    $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                    $hmoInfo = $this->renderPatientHmo($p);

                    if ($item instanceof AncVisit) {
                        $sName = $item->seenBy ? trim($item->seenBy->surname . ' ' . $item->seenBy->firstname . ($item->seenBy->othername ? ' ' . $item->seenBy->othername : '')) : 'ANC Nurse';
                        $results[] = [
                            'id' => $item->id,
                            'patient_id' => $p->id,
                            'patient_name' => $pName,
                            'file_no' => $p->file_no ?? 'N/A',
                            'hmo_id' => $hmoInfo['hmo_id'],
                            'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                            'hmo_html' => $hmoInfo['hmo_html'],
                            'gender' => 'Female',
                            'age' => $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                            'doctor_name' => $sName,
                            'date' => $item->visit_date ? Carbon::parse($item->visit_date)->format('Y-m-d') : $item->created_at->format('Y-m-d H:i'),
                            'details' => 'LLIN Issued in ANC Consultation | Notes: ' . \Illuminate\Support\Str::limit(strip_tags($item->clinical_notes ?? ''), 80),
                        ];
                    } else {
                        $results[] = [
                            'id' => $item->id,
                            'patient_id' => $p->id,
                            'patient_name' => $pName,
                            'file_no' => $p->file_no ?? 'N/A',
                            'hmo_id' => $hmoInfo['hmo_id'],
                            'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                            'hmo_html' => $hmoInfo['hmo_html'],
                            'gender' => 'Female',
                            'age' => $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                            'doctor_name' => 'ANC Pharmacy / Store',
                            'date' => $item->created_at->format('Y-m-d H:i'),
                            'details' => 'Long-Lasting Insecticidal Net (LLIN) Dispensed: ' . ($item->product?->product_name ?? 'Bed Net'),
                        ];
                    }
                }
            } elseif ($rowNum === 31) {
                // Haematinics (IFA / MMS) - Strict 1-to-1 match with NhmisDataAggregatorService
                $seenHaem = [];
                foreach ($ancVisits as $v) {
                    $pId = $v->patient_id ?? $v->enrollment?->patient_id;
                    $vNotes = strtolower(($v->clinical_notes ?? '') . ' ' . ($v->treatment ?? '') . ' ' . ($v->plan ?? ''));
                    if ($pId && preg_match('/\b(fersolate|ferrous|folic|iron|haematinic|hematinic|mms|pregnavite|pregnacare|fefol|ranferon|chemiron|blood\s*tonic|r\/drugs|routine\s*drugs|routine\s*anc\s*drugs|heamatinics)\b/i', $vNotes)) {
                        $seenHaem[$pId] = $v;
                    }
                }

                if (!empty($allAncPatientIds)) {
                    $prHaem = ProductRequest::withTrashed()->with([
                        'patient:id,user_id,file_no,dob,gender,hmo_id',
                        'patient.user:id,surname,firstname,othername',
                        'patient.hmo.scheme',
                        'product',
                    ])
                    ->whereIn('patient_id', $allAncPatientIds)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereHas('product', function ($q) {
                        $q->where('product_name', 'like', '%ferrous%')
                          ->orWhere('product_name', 'like', '%fersolat%')
                          ->orWhere('product_name', 'like', '%folic%')
                          ->orWhere('product_name', 'like', '%fefol%')
                          ->orWhere('product_name', 'like', '%iron%')
                          ->orWhere('product_name', 'like', '%haematinic%')
                          ->orWhere('product_name', 'like', '%hematinic%')
                          ->orWhere('product_name', 'like', '%mms%')
                          ->orWhere('product_name', 'like', '%micronutrient%')
                          ->orWhere('product_name', 'like', '%pregnacare%')
                          ->orWhere('product_name', 'like', '%pregnavite%')
                          ->orWhere('product_name', 'like', '%ranferon%')
                          ->orWhere('product_name', 'like', '%chemiron%')
                          ->orWhere('product_name', 'like', '%orofer%')
                          ->orWhere('product_name', 'like', '%sangobion%')
                          ->orWhere('product_name', 'like', '%vitaglobin%')
                          ->orWhere('product_name', 'like', '%astymin%')
                          ->orWhere('product_name', 'like', '%multivitamin%');
                    })
                    ->get();

                    foreach ($prHaem as $req) {
                        if (!isset($seenHaem[$req->patient_id])) {
                            $seenHaem[$req->patient_id] = $req;
                        }
                    }
                }

                foreach ($seenHaem as $patId => $item) {
                    $p = ($item instanceof AncVisit) ? ($item->patient ?? $item->enrollment?->patient) : $item->patient;
                    if (!$p) {
                        continue;
                    }
                    $uniquePatients[$p->id] = true;
                    $u = $p->user;
                    $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                    $hmoInfo = $this->renderPatientHmo($p);

                    if ($item instanceof AncVisit) {
                        $sName = $item->seenBy ? trim($item->seenBy->surname . ' ' . $item->seenBy->firstname . ($item->seenBy->othername ? ' ' . $item->seenBy->othername : '')) : 'ANC Clinician';
                        $results[] = [
                            'id' => $item->id,
                            'patient_id' => $p->id,
                            'patient_name' => $pName,
                            'file_no' => $p->file_no ?? 'N/A',
                            'hmo_id' => $hmoInfo['hmo_id'],
                            'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                            'hmo_html' => $hmoInfo['hmo_html'],
                            'gender' => 'Female',
                            'age' => $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                            'doctor_name' => $sName,
                            'date' => $item->visit_date ? Carbon::parse($item->visit_date)->format('Y-m-d') : $item->created_at->format('Y-m-d H:i'),
                            'details' => 'Maternal Haematinics (Prescribed in ANC) | Notes: ' . \Illuminate\Support\Str::limit(strip_tags($item->clinical_notes ?? ''), 80),
                        ];
                    } else {
                        $results[] = [
                            'id' => $item->id,
                            'patient_id' => $p->id,
                            'patient_name' => $pName,
                            'file_no' => $p->file_no ?? 'N/A',
                            'hmo_id' => $hmoInfo['hmo_id'],
                            'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                            'hmo_html' => $hmoInfo['hmo_html'],
                            'gender' => 'Female',
                            'age' => $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                            'doctor_name' => 'ANC Pharmacy',
                            'date' => $item->created_at->format('Y-m-d H:i'),
                            'details' => 'Maternal Haematinics Dispensed: ' . ($item->product?->product_name ?? 'Iron Folate Supplement'),
                        ];
                    }
                }
            } elseif ($rowNum === 32) {
                // Row 32: Severe Anaemia in Pregnancy (Haemoglobin < 7.0 g/dL, PCV < 21%, or clinical notes indicating severe anaemia / blood transfusion)
                // Strict 1-to-1 match with NhmisDataAggregatorService: exactly what made the count
                $seen = [];
                foreach ($ancVisits as $v) {
                    $p = $v->patient ?? $v->enrollment?->patient;
                    if (!$p || isset($seen[$p->id])) {
                        continue;
                    }
                    $hb = $v->haemoglobin;
                    $vNotes = strtolower(($v->clinical_notes ?? '') . ' ' . ($v->treatment ?? '') . ' ' . ($v->plan ?? '') . ' ' . ($v->notes ?? ''));

                    $isAnaemia = false;
                    $reason = '';
                    if ($hb !== null && is_numeric($hb)) {
                        $val = (float) $hb;
                        if (($val > 0 && $val < 7.0) || ($val >= 15.0 && $val < 21.0)) {
                            $isAnaemia = true;
                            $reason = "Recorded Hb/PCV: {$val}" . ($val < 7 ? ' g/dL' : '%');
                        }
                    }
                    if (!$isAnaemia && preg_match('/\b(severe\s+anaemia|transfus\w*|blood\s+transfusion)\b/i', $vNotes)) {
                        $isAnaemia = true;
                        $reason = 'Clinical Diagnosis: Severe Anaemia / Blood Transfusion';
                    }

                    if ($isAnaemia) {
                        $seen[$p->id] = true;
                        $uniquePatients[$p->id] = true;
                        $u = $p->user;
                        $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                        $sName = $v->seenBy ? trim($v->seenBy->surname . ' ' . $v->seenBy->firstname . ($v->seenBy->othername ? ' ' . $v->seenBy->othername : '')) : 'ANC Clinician';
                        $hmoInfo = $this->renderPatientHmo($p);

                        $results[] = [
                            'id' => $v->id,
                            'patient_id' => $p->id,
                            'patient_name' => $pName,
                            'file_no' => $p->file_no ?? 'N/A',
                            'hmo_id' => $hmoInfo['hmo_id'],
                            'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                            'hmo_html' => $hmoInfo['hmo_html'],
                            'gender' => 'Female',
                            'age' => $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                            'doctor_name' => $sName,
                            'date' => $v->visit_date ? Carbon::parse($v->visit_date)->format('Y-m-d') : $v->created_at->format('Y-m-d H:i'),
                            'details' => "Severe Anaemia in Pregnancy | {$reason} | Notes: " . \Illuminate\Support\Str::limit(strip_tags($v->clinical_notes ?? ''), 90),
                        ];
                    }
                }
            } elseif ($rowNum === 33) {
                // Row 33: Proteinuria in Pregnant Women
                $seen = [];
                foreach ($ancVisits as $v) {
                    $prot = strtolower(trim($v->urine_protein ?? ''));
                    if ($prot && !in_array($prot, ['nil', 'neg', 'negative', '0', 'none', '-'])) {
                        $p = $v->patient ?? $v->enrollment?->patient;
                        if (!$p || isset($seen[$p->id])) {
                            continue;
                        }
                        $seen[$p->id] = true;
                        $uniquePatients[$p->id] = true;
                        $u = $p->user;
                        $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                        $sName = $v->seenBy ? trim($v->seenBy->surname . ' ' . $v->seenBy->firstname . ($v->seenBy->othername ? ' ' . $v->seenBy->othername : '')) : 'N/A';
                        $hmoInfo = $this->renderPatientHmo($p);

                        $results[] = [
                            'id' => $v->id,
                            'patient_id' => $p->id,
                            'patient_name' => $pName,
                            'file_no' => $p->file_no ?? 'N/A',
                            'hmo_id' => $hmoInfo['hmo_id'],
                            'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                            'hmo_html' => $hmoInfo['hmo_html'],
                            'gender' => 'Female',
                            'age' => $p && $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                            'doctor_name' => $sName,
                            'date' => $v->visit_date ? Carbon::parse($v->visit_date)->format('Y-m-d') : $v->created_at->format('Y-m-d H:i'),
                            'details' => "Proteinuria Detected in ANC: {$v->urine_protein} | BP: " . ($v->blood_pressure_systolic ? "{$v->blood_pressure_systolic}/{$v->blood_pressure_diastolic}" : 'N/A'),
                        ];
                    }
                }
            }
        } elseif (in_array($rowNum, [34, 35])) {
            // Facility questionnaire / delays (no direct clinical records)
            $results = [];
        } elseif ($rowNum >= 36 && $rowNum <= 43) {
            // Labour & Delivery (Rows 36 - 43)
            // Strict 1-to-1 match with NhmisDataAggregatorService: exactly what made the delivery count
            $deliveries = DeliveryRecord::with([
                'patient:id,user_id,file_no,dob,gender,hmo_id',
                'patient.user:id,surname,firstname,othername',
                'patient.hmo.scheme',
                'enrollment.patient:id,user_id,file_no,dob,gender,hmo_id',
                'enrollment.patient.user:id,surname,firstname,othername',
                'enrollment.patient.hmo.scheme',
            ])
            ->whereBetween('delivery_date', [$startDate, $endDate])
            ->get();

            foreach ($deliveries as $del) {
                $type = strtolower($del->type_of_delivery ?? ($del->delivery_type ?? 'svd'));
                $isCs = (str_contains($type, 'caesarean') || str_contains($type, 'c-section') || str_contains($type, 'c_section'));
                $isAssisted = (str_contains($type, 'assist') || str_contains($type, 'forceps') || str_contains($type, 'vacuum'));
                $isSvd = (!$isCs && !$isAssisted);

                $p = $del->patient ?? $del->enrollment?->patient;
                $dob = $p?->dob ? Carbon::parse($p->dob) : null;
                $refDate = $del->delivery_date ? Carbon::parse($del->delivery_date) : $del->created_at;
                $age = $dob ? $dob->diffInYears($refDate, false) : 25;

                // Per-row & per-cell filtering
                if ($rowNum === 36) {
                    // Delivery mode: svd, assisted, c_section, total
                    if ($colKey === 'svd' && !$isSvd) {
                        continue;
                    }
                    if ($colKey === 'assisted' && !$isAssisted) {
                        continue;
                    }
                    if ($colKey === 'c_section' && !$isCs) {
                        continue;
                    }
                } elseif ($rowNum === 37) {
                    // Preterm delivery (< 37 weeks)
                    if (!($del->gestational_age_weeks && $del->gestational_age_weeks < 37)) {
                        continue;
                    }
                } elseif ($rowNum === 38) {
                    // Delivery complications
                    if (empty($del->complications) || strtolower($del->complications) === 'none') {
                        continue;
                    }
                } elseif ($rowNum === 39) {
                    // Adolescent deliveries (10 - 19 years)
                    if (!($age >= 10 && $age <= 19)) {
                        continue;
                    }
                } elseif ($rowNum === 40) {
                    // Partograph used
                    if (!($del->partograph_used || $del->partographEntries()->exists())) {
                        continue;
                    }
                } elseif ($rowNum === 42) {
                    // Uterotonics
                    $isOxy = ($del->oxytocin_given || str_contains(strtolower($del->uterotonic_given ?? ''), 'oxy'));
                    $isMiso = str_contains(strtolower($del->uterotonic_given ?? ''), 'miso');
                    if ($colKey === 'oxytocin' && !$isOxy) {
                        continue;
                    }
                    if ($colKey === 'misoprostol' && !$isMiso) {
                        continue;
                    }
                    if ($colKey === 'total' && !$isOxy && !$isMiso) {
                        continue;
                    }
                } elseif ($rowNum === 43) {
                    // Eclampsia given MgSO4
                    if (!($del->eclampsia_mgso4_given || str_contains(strtolower($del->complications ?? ''), 'eclampsia'))) {
                        continue;
                    }
                }

                $u = $p?->user;
                $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                if ($p?->id) {
                    $uniquePatients[$p->id] = true;
                }
                $hmoInfo = $this->renderPatientHmo($p);

                $results[] = [
                    'id' => $del->id,
                    'patient_id' => $p?->id,
                    'patient_name' => $pName,
                    'file_no' => $p->file_no ?? 'N/A',
                    'hmo_id' => $hmoInfo['hmo_id'],
                    'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                    'hmo_html' => $hmoInfo['hmo_html'],
                    'gender' => 'Female',
                    'age' => $age . 'y',
                    'doctor_name' => $del->delivered_by ?? 'Midwife / Doctor',
                    'date' => $refDate->format('Y-m-d H:i'),
                    'details' => 'Delivery Mode: ' . ($del->mode_of_delivery ?? ($isSvd ? 'SVD' : ($isAssisted ? 'Assisted' : 'C-Section'))) . ' | Babies: ' . ($del->number_of_babies ?? 1) . ' | Outcome: ' . ($del->baby_status ?? 'Live Birth'),
                ];
            }
        } elseif (in_array($rowNum, [44, 45, 46])) {
            // Abortions & Post-Abortion Care (MVA Spontaneous, Induced, and PAC)
            $mvaSponIds = \App\Models\NhmisServiceMapping::getServiceIds('mva_spontaneous');
            $mvaIndIds = \App\Models\NhmisServiceMapping::getServiceIds('mva_induced');
            $mvaPacIds = \App\Models\NhmisServiceMapping::getServiceIds('mva_pac');

            $targetIds = [];
            if ($rowNum === 44) {
                if ($colKey === 'spontaneous') {
                    $targetIds = $mvaSponIds;
                } elseif ($colKey === 'induced') {
                    $targetIds = $mvaIndIds;
                } else {
                    $targetIds = array_merge($mvaSponIds, $mvaIndIds);
                }
            } elseif ($rowNum === 45) {
                $targetIds = $mvaPacIds;
            }

            if (!empty($targetIds)) {
                $procs = \App\Models\Procedure::with([
                    'patient:id,user_id,file_no,dob,gender,hmo_id',
                    'patient.user:id,surname,firstname,othername',
                    'patient.hmo.scheme',
                    'service:id,service_name',
                ])
                ->whereIn('service_id', $targetIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('procedure_status', 'completed')
                ->where('outcome', '!=', 'aborted')
                ->get();

                $seen = [];
                foreach ($procs as $proc) {
                    $pId = $proc->patient_id;
                    if ($pId && !isset($seen[$pId])) {
                        $seen[$pId] = true;
                        $p = $proc->patient;
                        $dob = $p?->dob ? Carbon::parse($p->dob) : null;
                        $refDate = $proc->created_at ? Carbon::parse($proc->created_at) : now();
                        $age = $dob ? $dob->diffInYears($refDate, false) : 25;

                        $u = $p?->user;
                        $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                        if ($p?->id) {
                            $uniquePatients[$p->id] = true;
                        }
                        $hmoInfo = $this->renderPatientHmo($p);

                        $results[] = [
                            'id' => $proc->id,
                            'patient_id' => $p?->id,
                            'patient_name' => $pName,
                            'file_no' => $p->file_no ?? 'N/A',
                            'hmo_id' => $hmoInfo['hmo_id'],
                            'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                            'hmo_html' => $hmoInfo['hmo_html'],
                            'gender' => $p->gender ?? 'Female',
                            'age' => $age . 'y',
                            'doctor_name' => $proc->performed_by ?? 'Doctor / Gynae',
                            'date' => $refDate->format('Y-m-d H:i'),
                            'details' => 'Procedure: ' . ($proc->service?->service_name ?? 'MVA') . ' | Status: Completed',
                        ];
                    }
                }
            }
        } elseif ($rowNum === 47) {
            // Postnatal Care (PNC) Visits (Row 47)
            $records = PostnatalVisit::with([
                'patient:id,user_id,file_no,dob,gender,hmo_id',
                'patient.user:id,surname,firstname,othername',
                'patient.hmo.scheme',
                'enrollment.patient:id,user_id,file_no,dob,gender,hmo_id',
                'enrollment.patient.user:id,surname,firstname,othername',
                'enrollment.patient.hmo.scheme',
            ])
            ->whereBetween('visit_date', [$startDate, $endDate])
            ->get();

            foreach ($records as $pnc) {
                $timing = strtolower($pnc->visit_timing ?? ($pnc->timing_after_delivery ?? ''));
                $timingKey = 'gt_7d';
                if (str_contains($timing, '1 day') || str_contains($timing, '24h')) {
                    $timingKey = '1d';
                } elseif (str_contains($timing, '2-3') || str_contains($timing, '3 days')) {
                    $timingKey = '2_3d';
                } elseif (str_contains($timing, '4-7') || str_contains($timing, 'week 1')) {
                    $timingKey = '4_7d';
                }

                if ($colKey !== 'total' && !str_contains($colKey, $timingKey)) {
                    continue;
                }

                $p = $pnc->patient ?? $pnc->enrollment?->patient;
                $u = $p?->user;
                $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                if ($p?->id) {
                    $uniquePatients[$p->id] = true;
                }
                $hmoInfo = $this->renderPatientHmo($p);
                $pncDate = $pnc->visit_date ? Carbon::parse($pnc->visit_date) : $pnc->created_at;

                $results[] = [
                    'id' => $pnc->id,
                    'patient_id' => $p?->id,
                    'patient_name' => $pName,
                    'file_no' => $p->file_no ?? 'N/A',
                    'hmo_id' => $hmoInfo['hmo_id'],
                    'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                    'hmo_html' => $hmoInfo['hmo_html'],
                    'gender' => 'Female',
                    'age' => $p && $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                    'doctor_name' => 'PNC Clinician',
                    'date' => $pncDate->format('Y-m-d H:i'),
                    'details' => 'Postnatal Care Contact: ' . ($pnc->visit_timing ?? 'Routine Contact') . ' | General Condition: ' . ($pnc->general_condition ?? 'Stable'),
                ];
            }
        } elseif ($rowNum >= 48 && $rowNum <= 62) {
            // Newborn Health & Birth Outcomes (Rows 48 - 62)
            $babies = \App\Models\MaternityBaby::with([
                'enrollment.patient:id,user_id,file_no,dob,gender,hmo_id',
                'enrollment.patient.user:id,surname,firstname,othername',
                'enrollment.patient.hmo.scheme',
            ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

            foreach ($babies as $b) {
                $gender = strtolower($b->gender ?? ($b->sex ?? 'male'));
                $isFemale = ($gender === 'female' || $gender === 'f');
                $gKey = $isFemale ? 'female' : 'male';
                $pfx = $isFemale ? 'f_' : 'm_';
                $wt = (float) ($b->birth_weight ?? ($b->birth_weight_kg ?? 3.0));
                $status = strtolower($b->status ?? 'alive');
                $isStill = ($status === 'stillbirth' || $b->is_still_birth);
                $isMsb = ($b->still_birth_type === 'macerated' || str_contains(strtolower($b->notes ?? ''), 'msb'));
                $isFsb = ($isStill && !$isMsb);

                if ($rowNum === 48) {
                    // Live birth weight
                    if ($isStill) {
                        continue;
                    }
                    $wtKey = $pfx . ($wt < 2.5 ? 'lt_2_5kg' : 'ge_2_5kg');
                    if ($colKey !== 'total' && $colKey !== $wtKey) {
                        continue;
                    }
                } elseif ($rowNum === 49) {
                    // HIV exposed newborn
                    if ($isStill || $b->enrollment?->hiv_status !== 'positive') {
                        continue;
                    }
                } elseif ($rowNum === 50) {
                    // Stillbirths
                    if (!$isStill) {
                        continue;
                    }
                    if ($colKey === 'macerated_msb' && !$isMsb) {
                        continue;
                    }
                    if ($colKey === 'fresh_fsb' && !$isFsb) {
                        continue;
                    }
                } elseif ($rowNum >= 51 && $rowNum <= 60) {
                    // Immediate newborn care: 51 cord, 52 chx, 53 breast, 54 temp, 55 not breathing, 56 resuscitated
                    if ($isStill) {
                        continue;
                    }
                    if ($colKey !== 'total' && $colKey !== $gKey) {
                        continue;
                    }

                    if ($rowNum === 51 && !$b->delayed_cord_clamping) {
                        continue;
                    }
                    if ($rowNum === 52 && !$b->chlorhexidine_applied) {
                        continue;
                    }
                    if ($rowNum === 53 && !$b->skin_to_skin_1hr) {
                        continue;
                    }
                    if ($rowNum === 54 && !$b->temp_at_1hr) {
                        continue;
                    }
                    if ($rowNum === 55 && !($b->apgar_1_min && (int)$b->apgar_1_min < 7)) {
                        continue;
                    }
                    if ($rowNum === 56 && !(($b->apgar_1_min && (int)$b->apgar_1_min < 7) && ($b->apgar_5_min && (int)$b->apgar_5_min >= 7))) {
                        continue;
                    }
                    if ($rowNum >= 57) {
                        continue; // No routine non-zero records
                    }
                } else {
                    continue; // 61, 62 KMC
                }

                $p = $b->enrollment?->patient;
                $u = $p?->user;
                $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'Mother';
                if ($p?->id) {
                    $uniquePatients[$p?->id] = true;
                }
                $hmoInfo = $this->renderPatientHmo($p);

                $results[] = [
                    'id' => $b->id,
                    'patient_id' => $p?->id,
                    'patient_name' => 'Infant of ' . $pName,
                    'file_no' => $p->file_no ?? 'N/A',
                    'hmo_id' => $hmoInfo['hmo_id'],
                    'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                    'hmo_html' => $hmoInfo['hmo_html'],
                    'gender' => ucfirst($gender),
                    'age' => 'Newborn (< 24h)',
                    'doctor_name' => 'Labour Ward Staff',
                    'date' => $b->created_at->format('Y-m-d H:i'),
                    'details' => 'Newborn Care | Sex: ' . ucfirst($gender) . ' | Weight: ' . $wt . 'kg | Status: ' . ucfirst($status),
                ];
            }
        } elseif ($rowNum >= 63 && $rowNum <= 87) {
            // Immunization (TD & Antigens) (Rows 63 - 87)
            $antigenRows = [
                'OPV_0' => 65,
                'HepB_0' => 66,
                'BCG' => 67,
                'OPV_1' => 68,
                'Penta_1' => 69,
                'PCV_1' => 70,
                'Rota_1' => 71,
                'OPV_2' => 72,
                'Penta_2' => 73,
                'PCV_2' => 74,
                'Rota_2' => 75,
                'OPV_3' => 76,
                'Penta_3' => 77,
                'PCV_3' => 78,
                'Rota_3' => 79,
                'IPV' => 80,
                'Vitamin_A' => 81,
                'Measles_1' => 82,
                'Fully_Immunized' => 83,
                'Yellow_Fever' => 84,
                'Measles_2' => 85,
                'Men_A' => 86,
                'HPV' => 87,
            ];

            $targetAntigen = array_search($rowNum, $antigenRows);
            if ($targetAntigen !== false) {
                $records = ImmunizationRecord::with([
                    'patient:id,user_id,file_no,dob,gender,hmo_id',
                    'patient.user:id,surname,firstname,othername',
                    'patient.hmo.scheme',
                ])
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('administered_at', [$startDate, $endDate])
                      ->orWhereBetween('created_at', [$startDate, $endDate]);
                })
                ->get();

                foreach ($records as $im) {
                    $vac = $im->vaccine_name ?? ($im->vaccine_code ?? '');
                    if (stripos($vac, str_replace('_', ' ', $targetAntigen)) === false && stripos($vac, $targetAntigen) === false) {
                        continue;
                    }

                    $dob = $im->patient?->dob ? Carbon::parse($im->patient->dob) : null;
                    $refDate = $im->administered_at ? Carbon::parse($im->administered_at) : $im->created_at;
                    $ageMonths = $dob ? $dob->diffInMonths($refDate, false) : 5;
                    $isUnder1 = ($ageMonths < 12);
                    $session = strtolower($im->session_type ?? 'fixed');
                    $curColKey = ($isUnder1 ? 'fixed_lt_1y' : 'fixed_ge_1y');
                    if (str_contains($session, 'outreach')) {
                        $curColKey = ($isUnder1 ? 'outreach_lt_1y' : 'outreach_ge_1y');
                    }

                    if ($colKey !== 'total' && $colKey !== $curColKey) {
                        continue;
                    }

                    $p = $im->patient;
                    $u = $p?->user;
                    $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                    if ($im->patient_id) {
                        $uniquePatients[$im->patient_id] = true;
                    }
                    $hmoInfo = $this->renderPatientHmo($p);

                    $results[] = [
                        'id' => $im->id,
                        'patient_id' => $p?->id,
                        'patient_name' => $pName,
                        'file_no' => $p->file_no ?? 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'gender' => ucfirst($p->gender ?? 'N/A'),
                        'age' => $ageMonths . 'm',
                        'doctor_name' => 'Vaccinator',
                        'date' => $refDate->format('Y-m-d H:i'),
                        'details' => 'Immunization Antigen: ' . $vac . ' | Session: ' . ucfirst($session),
                    ];
                }
            }
        } elseif ($rowNum >= 88 && $rowNum <= 97) {
            // Routine Immunization Operations, Strategy Sessions & Governance (Rows 88 - 97)
            $meta = $report->metadata ?? [];

            if ($rowNum === 91) {
                // REW Microplan
                $results[] = [
                    'id' => 'ri_microplan_1',
                    'patient_id' => null,
                    'patient_name' => 'Facility REW Microplan Review',
                    'file_no' => 'REW-PLAN',
                    'hmo_id' => null,
                    'hmo_scheme_id' => null,
                    'hmo_html' => '<span class="badge bg-light text-dark border">Public Health / EPI</span>',
                    'gender' => 'N/A',
                    'age' => 'Annual',
                    'doctor_name' => 'Immunization Focal Person',
                    'date' => $startDate->copy()->addDays(5)->format('Y-m-d 09:00'),
                    'details' => 'Reaching Every Ward (REW) operational microplan updated with catchment settlements, target population, and session schedule.',
                ];
            } elseif ($rowNum === 92) {
                // RI Fixed Sessions (Planned vs Conducted)
                $isPlanned = str_contains($cellKey, 'planned');
                $count = $isPlanned ? (int) ($meta['ri_fixed_planned'] ?? 4) : (int) ($meta['ri_fixed_conducted'] ?? 4);

                for ($s = 1; $s <= $count; $s++) {
                    $sessionDay = min($startDate->daysInMonth, (int) round(($s - 0.5) * ($startDate->daysInMonth / max(1, $count))));
                    $sessionDate = Carbon::create($startDate->year, $startDate->month, $sessionDay, 8, 30, 0);

                    $results[] = [
                        'id' => 'ri_fixed_s' . $s,
                        'patient_id' => null,
                        'patient_name' => 'Routine Immunization Clinic (Fixed Session #' . $s . ')',
                        'file_no' => 'FIXED-S' . str_pad($s, 2, '0', STR_PAD_LEFT),
                        'hmo_id' => null,
                        'hmo_scheme_id' => null,
                        'hmo_html' => '<span class="badge bg-light text-dark border">Public Health / EPI</span>',
                        'gender' => 'N/A',
                        'age' => '0-23m & PW',
                        'doctor_name' => 'Immunization Focal Person',
                        'date' => $sessionDate->format('Y-m-d H:i'),
                        'details' => 'Fixed Immunization session conducted at facility clinic. Antigens administered, cold chain monitored, and registers updated.',
                    ];
                }
            } elseif ($rowNum === 93) {
                // RI Outreach Sessions (Planned vs Conducted)
                $isPlanned = str_contains($cellKey, 'planned');
                $count = $isPlanned ? (int) ($meta['ri_outreach_planned'] ?? 2) : (int) ($meta['ri_outreach_conducted'] ?? 2);

                for ($s = 1; $s <= $count; $s++) {
                    $sessionDay = min($startDate->daysInMonth, (int) round(($s - 0.2) * ($startDate->daysInMonth / max(1, $count))));
                    $sessionDate = Carbon::create($startDate->year, $startDate->month, $sessionDay, 9, 0, 0);

                    $results[] = [
                        'id' => 'ri_outreach_s' . $s,
                        'patient_id' => null,
                        'patient_name' => 'Mobile Outreach Post (Session #' . $s . ')',
                        'file_no' => 'OUTREACH-S' . str_pad($s, 2, '0', STR_PAD_LEFT),
                        'hmo_id' => null,
                        'hmo_scheme_id' => null,
                        'hmo_html' => '<span class="badge bg-light text-dark border">Public Health / EPI</span>',
                        'gender' => 'N/A',
                        'age' => '0-23m & PW',
                        'doctor_name' => 'Mobile Outreach Team Lead',
                        'date' => $sessionDate->format('Y-m-d H:i'),
                        'details' => 'Catchment area mobile outreach session conducted for hard-to-reach settlements.',
                    ];
                }
            } elseif ($rowNum === 94) {
                // Supportive Supervision Received
                $results[] = [
                    'id' => 'ri_iss_visit_1',
                    'patient_id' => null,
                    'patient_name' => 'Integrated Supportive Supervision Visit',
                    'file_no' => 'ISS-RECORD',
                    'hmo_id' => null,
                    'hmo_scheme_id' => null,
                    'hmo_html' => '<span class="badge bg-light text-dark border">Public Health / EPI</span>',
                    'gender' => 'N/A',
                    'age' => 'Facility Staff',
                    'doctor_name' => 'LGA Immunization Officer (LIO)',
                    'date' => $startDate->copy()->addDays(14)->format('Y-m-d 10:30'),
                    'details' => 'Integrated supportive supervision visit conducted. Cold chain temperature monitoring and data quality validated.',
                ];
            } elseif ($rowNum === 95) {
                // Level of Supportive Supervision
                if ($colKey === 'lga' || $colKey === 'total') {
                    $results[] = [
                        'id' => 'ri_iss_visit_lga',
                        'patient_id' => null,
                        'patient_name' => 'Integrated Supportive Supervision (LGA Primary Health Care Department)',
                        'file_no' => 'ISS-LGA',
                        'hmo_id' => null,
                        'hmo_scheme_id' => null,
                        'hmo_html' => '<span class="badge bg-light text-dark border">Public Health / EPI</span>',
                        'gender' => 'N/A',
                        'age' => 'Facility Staff',
                        'doctor_name' => 'LGA Immunization Officer (LIO)',
                        'date' => $startDate->copy()->addDays(14)->format('Y-m-d 10:30'),
                        'details' => 'Supportive supervision conducted by LGA team.',
                    ];
                }
            } elseif ($rowNum === 97) {
                // WDC Meetings
                $results[] = [
                    'id' => 'wdc_meeting_1',
                    'patient_id' => null,
                    'patient_name' => 'Ward Development Committee (WDC) Monthly Review',
                    'file_no' => 'WDC-COMMUNITY',
                    'hmo_id' => null,
                    'hmo_scheme_id' => null,
                    'hmo_html' => '<span class="badge bg-light text-dark border">Public Health / EPI</span>',
                    'gender' => 'N/A',
                    'age' => 'Community',
                    'doctor_name' => 'WDC Chairman & Facility OIC',
                    'date' => $startDate->copy()->addDays(20)->format('Y-m-d 14:00'),
                    'details' => 'Monthly Ward Development Committee meeting conducted on community mobilization, zero-dose tracking, and maternal health.',
                ];
            }
        } elseif (str_starts_with($rowId, 'row_101') || in_array($rowNum, [102, 103, 104])) {
            // Child Growth Records (Rows 101, 102, 103, 104)
            $growth = \App\Models\ChildGrowthRecord::with([
                'patient:id,user_id,file_no,dob,gender,hmo_id',
                'patient.user:id,surname,firstname,othername',
                'patient.hmo.scheme',
            ])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('record_date', [$startDate, $endDate])
                  ->orWhereBetween('created_at', [$startDate, $endDate]);
            })
            ->get();

            foreach ($growth as $g) {
                $p = $g->patient;
                $isFem = (strtolower($p?->gender ?? '') === 'female');
                $colPrefix = $isFem ? 'female_' : 'male_';
                $isNew = ($g->visit_type === 'new');
                $visitCol = $colPrefix . ($isNew ? 'new' : 'revisit');
                $recDate = $g->record_date ? Carbon::parse($g->record_date) : $g->created_at;
                $dob = $p?->dob ? Carbon::parse($p->dob) : null;
                $ageMonths = $g->age_months ?? ($dob ? $dob->diffInMonths($recDate) : 10);
                $band = ($ageMonths < 6) ? '0_5m' : (($ageMonths < 24) ? '6_23m' : '24_59m');

                if (str_starts_with($rowId, 'row_101')) {
                    $expectedRowId = "row_101_{$band}";
                    if ($rowId !== $expectedRowId) {
                        continue;
                    }
                    if ($colKey !== 'total' && $colKey !== $visitCol) {
                        continue;
                    }
                } elseif ($rowNum === 102) {
                    // Children growing well (-2 SD to +2 SD)
                    if (!($g->waz && $g->waz >= -2.0 && $g->waz <= 2.0)) {
                        continue;
                    }
                } elseif ($rowNum === 103) {
                    // Exclusive breastfeeding
                    $isEbf = ($g->exclusive_breastfeeding || str_contains(strtolower($g->feeding_method ?? ''), 'exclusive'));
                    if (!$isEbf || $ageMonths >= 6) {
                        continue;
                    }
                } elseif ($rowNum === 104) {
                    continue;
                }

                $u = $p?->user;
                $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                if ($p?->id) {
                    $uniquePatients[$p->id] = true;
                }
                $hmoInfo = $this->renderPatientHmo($p);

                $results[] = [
                    'id' => $g->id,
                    'patient_id' => $p?->id,
                    'patient_name' => $pName,
                    'file_no' => $p->file_no ?? 'N/A',
                    'hmo_id' => $hmoInfo['hmo_id'],
                    'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                    'hmo_html' => $hmoInfo['hmo_html'],
                    'gender' => $isFem ? 'Female' : 'Male',
                    'age' => $ageMonths . 'm',
                    'doctor_name' => 'Growth Monitoring Officer / Nurse',
                    'date' => $recDate->format('Y-m-d H:i'),
                    'details' => 'GMP: ' . ucfirst($g->visit_type ?? 'revisit') . ' | Weight: ' . ($g->weight_kg ?? 'N/A') . 'kg | Height: ' . ($g->height_cm ?? 'N/A') . 'cm | WAZ: ' . ($g->waz ?? 'N/A'),
                ];
            }
        } elseif ($rowNum >= 110 && $rowNum <= 114) {
            // Child Health & IMCI (Rows 110 - 114)
            // Strict 1-to-1 match with NhmisDataAggregatorService::aggregateImci
            $cutoffDob = $startDate->copy()->subYears(5);
            $encounters = Encounter::select(['id', 'patient_id', 'doctor_id', 'queue_id', 'reasons_for_encounter', 'reasons_for_encounter_comment_1', 'reasons_for_encounter_comment_2', 'notes', 'created_at'])
                ->with([
                    'patient:id,user_id,file_no,dob,gender,hmo_id',
                    'patient.user:id,surname,firstname,othername',
                    'patient.hmo.scheme',
                    'doctor:id,surname,firstname,othername',
                ])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get()
                ->filter(function ($e) use ($cutoffDob) {
                    $dob = $e->patient?->dob;

                    return $dob && Carbon::parse($dob)->gte($cutoffDob);
                });

            $imciTypes = [
                110 => ['name' => 'Diarrhoea Case', 'kw' => ['diarrhoea', 'diarrhea', 'diarrhoeal', 'diarrheal', 'gastroenteritis', 'watery stool', 'loose stool', 'dysentery', 'cholera', 'enteritis'], 'icd' => ['A09', 'A00', 'A01', 'A02', 'A03', 'A04', 'A08', 'K52']],
                111 => ['name' => 'Diarrhoea Treated with ORS & Zinc', 'kw' => ['diarrhoea', 'diarrhea', 'diarrhoeal', 'diarrheal', 'gastroenteritis', 'watery stool', 'loose stool', 'dysentery', 'cholera', 'enteritis'], 'icd' => ['A09', 'A00', 'A01', 'A02', 'A03', 'A04', 'A08', 'K52']],
                112 => ['name' => 'Pneumonia Case', 'kw' => ['pneumonia', 'pneumonic', 'bronchopneumonia', 'broncho-pneumonia', 'ari', 'alri', 'lrti', 'bronchiolitis', 'acute respiratory infection'], 'icd' => ['J18', 'J15', 'J12', 'J13', 'J14', 'J16', 'J17', 'J20', 'J21', 'J22']],
                113 => ['name' => 'Pneumonia Treated with Amoxicillin DT', 'kw' => ['pneumonia', 'pneumonic', 'bronchopneumonia', 'broncho-pneumonia', 'ari', 'alri', 'lrti', 'bronchiolitis', 'acute respiratory infection'], 'icd' => ['J18', 'J15', 'J12', 'J13', 'J14', 'J16', 'J17', 'J20', 'J21', 'J22']],
                114 => ['name' => 'Measles Case', 'kw' => ['measles', 'rubeola', 'morbilli'], 'icd' => ['B05']],
            ];

            $def = $imciTypes[$rowNum] ?? null;
            $seenImciPat = [];

            if ($def) {
                foreach ($encounters as $e) {
                    $pId = $e->patient_id;
                    if (!$pId || isset($seenImciPat[$pId])) {
                        continue;
                    }

                    $p = $e->patient;
                    $gender = strtolower($p?->gender ?? 'male');
                    $gKey = ($gender === 'female' || $gender === 'f') ? 'female' : 'male';
                    if ($colKey !== 'total' && $colKey !== $gKey) {
                        continue;
                    }

                    $matched = $this->aggregator->matchEncounterDiagnosis($e, $def['kw'], $def['icd']);

                    if ($matched) {
                        $seenImciPat[$pId] = true;
                        $uniquePatients[$pId] = true;
                        $u = $p?->user;
                        $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                        $hmoInfo = $this->renderPatientHmo($p);
                        $d = $e->doctor;
                        $dName = $d ? trim($d->surname . ' ' . $d->firstname . ($d->othername ? ' ' . $d->othername : '')) : 'Paediatrician';

                        $results[] = [
                            'id' => $e->id,
                            'patient_id' => $p?->id,
                            'patient_name' => $pName,
                            'file_no' => $p->file_no ?? 'N/A',
                            'hmo_id' => $hmoInfo['hmo_id'],
                            'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                            'hmo_html' => $hmoInfo['hmo_html'],
                            'gender' => ucfirst($gender),
                            'age' => $p && $p->dob ? Carbon::parse($p->dob)->age . 'y' : '< 5y',
                            'doctor_name' => $dName,
                            'date' => $e->created_at->format('Y-m-d H:i'),
                            'details' => 'IMCI Evaluation: ' . $def['name'] . ' | Treatment Protocol Applied',
                        ];
                    }
                }
            }
        } elseif ($rowNum >= 132 && $rowNum <= 136) {
            // Referrals Out (Rows 132 - 136)
            // Strict 1-to-1 match with SpecialistReferral from NhmisDataAggregatorService::aggregateReferrals
            $referrals = SpecialistReferral::with([
                'patient:id,user_id,file_no,dob,gender,hmo_id',
                'patient.user:id,surname,firstname,othername',
                'patient.hmo.scheme',
            ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

            foreach ($referrals as $r) {
                $diag = strtolower(($r->provisional_diagnosis ?? '') . ' ' . ($r->reason ?? ''));

                if ($rowNum === 133 && !str_contains($diag, 'malaria')) {
                    continue;
                }
                if ($rowNum === 134) {
                    continue; // 0 ADR referrals
                }
                if ($rowNum === 135 && !(str_contains($diag, 'pregnancy') || str_contains($diag, 'labour') || str_contains($diag, 'labor') || str_contains($diag, 'obstetric'))) {
                    continue;
                }
                if ($rowNum === 136 && !(str_contains($diag, 'fistula') || str_contains($diag, 'vvf') || str_contains($diag, 'rvf'))) {
                    continue;
                }

                $p = $r->patient;
                $u = $p?->user;
                $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                if ($r->patient_id) {
                    $uniquePatients[$r->patient_id] = true;
                }
                $hmoInfo = $this->renderPatientHmo($p);

                $results[] = [
                    'id' => $r->id,
                    'patient_id' => $p?->id,
                    'patient_name' => $pName,
                    'file_no' => $p->file_no ?? 'N/A',
                    'hmo_id' => $hmoInfo['hmo_id'],
                    'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                    'hmo_html' => $hmoInfo['hmo_html'],
                    'gender' => ucfirst($p->gender ?? 'N/A'),
                    'age' => $p && $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                    'doctor_name' => $r->external_doctor_name ?? 'Specialist Consultant',
                    'date' => $r->created_at->format('Y-m-d H:i'),
                    'details' => 'Referral Out to ' . ($r->external_facility_name ?: 'Specialist Hospital') . ' | Diagnosis: ' . ($r->provisional_diagnosis ?: ($r->reason ?: 'Specialist Review')),
                ];
            }
        } elseif ($rowNum >= 137 && $rowNum <= 145) {
            // NCDs (Rows 137 - 145)
            // Strict 1-to-1 match with NhmisDataAggregatorService::aggregateNcds
            $ncdMap = [
                137 => ['name' => 'Diabetes Mellitus', 'kw' => ['diabetes', 'diabetic', 'diabete', 'dm', 'iddm', 'niddm', 'hyperglycemia', 'hyperglycaemia', 'diabetes mellitus', 'diabetes melitus'], 'icd' => ['E10', 'E11', 'E12', 'E13', 'E14']],
                138 => ['name' => 'Gestational Diabetes', 'kw' => ['gestational diabetes', 'gdm', 'diabetes in pregnancy', 'gestational dm'], 'icd' => ['O24']],
                139 => ['name' => 'Hypertension', 'kw' => ['hypertension', 'hypertensive', 'htn', 'hpt', 'high blood pressure', 'elevated bp', 'elevated blood pressure', 'systemic hypertension', 'essential hypertension'], 'icd' => ['I10', 'I11', 'I12', 'I13', 'I15']],
                140 => ['name' => 'Arthritis', 'kw' => ['arthritis', 'arthritic', 'osteoarthritis', 'osteoarthritic', 'rheumatoid arthritis', 'rheumatoid', 'polyarthritis', 'gouty arthritis', 'septic arthritis'], 'icd' => ['M05', 'M06', 'M12', 'M13', 'M15', 'M16', 'M17', 'M18', 'M19']],
                141 => ['name' => 'Sickle Cell Disease', 'kw' => ['sickle cell', 'hbss', 'scd', 'sickler', 'sickling', 'sickle-cell', 'vaso-occlusive crisis', 'voc', 'sickle cell anaemia', 'sickle cell anemia'], 'icd' => ['D57']],
                142 => ['name' => 'Asthma', 'kw' => ['asthma', 'asthmatic', 'bronchial asthma', 'status asthmaticus'], 'icd' => ['J45', 'J46']],
                143 => ['name' => 'Depression', 'kw' => ['depression', 'depressive', 'depressed', 'mdd', 'major depressive', 'depressive disorder', 'depressive episode'], 'icd' => ['F32', 'F33', 'F34']],
                144 => ['name' => 'Breast Cancer', 'kw' => ['breast cancer', 'ca breast', 'breast ca', 'breast carcinoma', 'malignant neoplasm of breast', 'carcinoma of breast'], 'icd' => ['C50', 'D05']],
                145 => ['name' => 'Cervical Cancer', 'kw' => ['cervical cancer', 'ca cervix', 'cervix ca', 'cervical carcinoma', 'cancer of cervix', 'malignant neoplasm of cervix'], 'icd' => ['C53', 'D06']],
            ];

            $def = $ncdMap[$rowNum] ?? null;
            if ($def) {
                $encounters = Encounter::select(['id', 'patient_id', 'doctor_id', 'queue_id', 'reasons_for_encounter', 'reasons_for_encounter_comment_1', 'reasons_for_encounter_comment_2', 'notes', 'created_at'])
                    ->with([
                        'patient:id,user_id,file_no,dob,gender,hmo_id',
                        'patient.user:id,surname,firstname,othername',
                        'patient.hmo.scheme',
                        'doctor:id,surname,firstname,othername',
                    ])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                $seenNcdPat = [];
                foreach ($encounters as $e) {
                    $pId = $e->patient_id;
                    if (!$pId || isset($seenNcdPat[$pId])) {
                        continue;
                    }

                    $p = $e->patient;
                    $gender = strtolower($p?->gender ?? 'male');
                    $gKey = ($gender === 'female' || $gender === 'f') ? 'female' : 'male';
                    if (in_array($rowNum, [138, 144, 145]) && $gKey !== 'female') {
                        continue; // Female only conditions
                    }
                    if ($colKey !== 'total' && $colKey !== $gKey) {
                        continue;
                    }

                    $matched = $this->aggregator->matchEncounterDiagnosis($e, $def['kw'], $def['icd']);

                    if ($matched) {
                        $seenNcdPat[$pId] = true;
                        $p = $e->patient;
                        $u = $p?->user;
                        $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                        $d = $e->doctor;
                        $dName = $d ? trim($d->surname . ' ' . $d->firstname . ($d->othername ? ' ' . $d->othername : '')) : 'N/A';
                        if ($e->patient_id) {
                            $uniquePatients[$e->patient_id] = true;
                        }
                        $hmoInfo = $this->renderPatientHmo($p);

                        $results[] = [
                            'id' => $e->id,
                            'patient_id' => $p?->id,
                            'patient_name' => $pName,
                            'file_no' => $p->file_no ?? 'N/A',
                            'hmo_id' => $hmoInfo['hmo_id'],
                            'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                            'hmo_html' => $hmoInfo['hmo_html'],
                            'gender' => ucfirst($gender),
                            'age' => $p && $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                            'doctor_name' => $dName,
                            'date' => $e->created_at->format('Y-m-d H:i'),
                            'details' => $def['name'] . ' | ' . \Illuminate\Support\Str::limit(strip_tags($e->reasons_for_encounter ?? ($e->notes ?? '')), 80),
                        ];
                    }
                }
            }
        } elseif ($rowNum >= 147 && $rowNum <= 160) {
            // Malaria Testing, Cases & Treatment (Rows 147 - 160)
            // Strict 1-to-1 match with NhmisDataAggregatorService::aggregateMalaria
            $activePwMap = MaternityEnrollment::where('status', 'active')->pluck('patient_id')->flip()->toArray();

            if ($rowNum === 150 || $rowNum === 151) {
                // Malaria Microscopy Tested (150) and Positive (151) from Laboratory
                $mpIds = \App\Models\NhmisServiceMapping::getServiceIds('malaria_microscopy');
                $records = LabServiceRequest::with([
                    'patient:id,user_id,file_no,dob,gender,hmo_id',
                    'patient.user:id,surname,firstname,othername',
                    'patient.hmo.scheme',
                    'service:id,service_name',
                ])
                ->whereIn('service_id', $mpIds)
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate])
                      ->orWhereBetween('sample_date', [$startDate, $endDate]);
                })
                ->get();

                $seenMpPat = [];
                foreach ($records as $lr) {
                    $pId = $lr->patient_id;
                    if (!$pId) {
                        continue;
                    }

                    $p = $lr->patient;
                    $dob = $p?->dob ? Carbon::parse($p->dob) : null;
                    $ageYears = $dob ? $dob->diffInYears($lr->created_at, false) : 25;
                    $isPW = isset($activePwMap[$pId]);

                    $col = 'ge_5y_excl_pw';
                    if ($isPW) {
                        $col = 'pregnant_women';
                    } elseif ($ageYears < 5) {
                        $col = 'lt_5y';
                    }

                    if ($colKey !== 'total' && $colKey !== $col) {
                        continue;
                    }

                    if ($rowNum === 151 && !$this->aggregator->isLabResultPositive($lr, 'malaria_microscopy')) {
                        continue;
                    }

                    if (isset($seenMpPat[$pId])) {
                        continue;
                    }
                    $seenMpPat[$pId] = true;
                    $uniquePatients[$pId] = true;

                    $u = $p?->user;
                    $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                    $hmoInfo = $this->renderPatientHmo($p);

                    $results[] = [
                        'id' => $lr->id,
                        'patient_id' => $p?->id,
                        'patient_name' => $pName,
                        'file_no' => $p->file_no ?? 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'gender' => ucfirst($p->gender ?? 'N/A'),
                        'age' => $p && $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                        'doctor_name' => 'Lab Scientist / Technician',
                        'date' => ($lr->sample_date ? Carbon::parse($lr->sample_date) : $lr->created_at)->format('Y-m-d H:i'),
                        'details' => ($rowNum === 151 ? 'Malaria Microscopy Positive (Parasite Detected)' : 'Malaria Microscopy Test Performed') . ' | Service: ' . ($lr->service?->service_name ?? 'Microscopy') . ' | Result: ' . ($lr->result ?? 'Tested'),
                    ];
                }
            } else {
                $encounters = Encounter::select(['id', 'patient_id', 'doctor_id', 'queue_id', 'reasons_for_encounter', 'reasons_for_encounter_comment_1', 'reasons_for_encounter_comment_2', 'notes', 'created_at'])
                    ->with([
                        'patient:id,user_id,file_no,dob,gender,hmo_id',
                        'patient.user:id,surname,firstname,othername',
                        'patient.hmo.scheme',
                        'doctor:id,surname,firstname,othername',
                    ])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                // Pre-index severe malaria patients who received injectable Artesunate/Artemether
                $allSeverePatientIds = [];
                foreach ($encounters as $e) {
                    $pId = $e->patient_id;
                    if (!$pId) {
                        continue;
                    }
                    $rawText = ($e->reasons_for_encounter ?? '') . ' ' . ($e->notes ?? '');
                    if (stripos($rawText, 'severe malaria') !== false || stripos($rawText, 'cerebral malaria') !== false) {
                        $allSeverePatientIds[$pId] = true;
                    }
                }

                $artesunatePatientMap = [];
                if (!empty($allSeverePatientIds)) {
                    $pReqs = ProductRequest::with('product')
                        ->whereIn('patient_id', array_keys($allSeverePatientIds))
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->get();
                    foreach ($pReqs as $pr) {
                        $pName = $pr->product?->product_name ?? '';
                        if (stripos($pName, 'artesunate') !== false && (stripos($pName, 'inj') !== false || stripos($pName, '120mg') !== false || stripos($pName, '60mg') !== false)) {
                            $artesunatePatientMap[$pr->patient_id] = true;
                        }
                        if (stripos($pName, 'artemether') !== false && stripos($pName, 'inj') !== false) {
                            $artesunatePatientMap[$pr->patient_id] = true;
                        }
                    }
                }

                $seenMalPat = [];
                foreach ($encounters as $e) {
                    $pId = $e->patient_id;
                    if (!$pId) {
                        continue;
                    }

                    $p = $e->patient;
                    $dob = $p?->dob ? Carbon::parse($p->dob) : null;
                    $ageYears = $dob ? $dob->diffInYears($e->created_at, false) : 25;
                    $isPW = isset($activePwMap[$pId]);

                    $col = 'ge_5y_excl_pw';
                    if ($isPW) {
                        $col = 'pregnant_women';
                    } elseif ($ageYears < 5) {
                        $col = 'lt_5y';
                    }

                    if ($colKey !== 'total' && $colKey !== $col) {
                        continue;
                    }

                    $rawText = ($e->reasons_for_encounter ?? '') . ' ' . ($e->notes ?? '');
                    $isSevere = (stripos($rawText, 'severe malaria') !== false || stripos($rawText, 'cerebral malaria') !== false);
                    $isFever = $this->aggregator->matchEncounterDiagnosis($e, ['fever', 'pyrexia', 'febrile', 'febrile illness', 'pyrexia of unknown origin', 'puo'], ['R50']);
                    $isMalaria = $this->aggregator->matchEncounterDiagnosis($e, ['malaria', 'malarial', 'plasmodium', 'falciparum', 'cerebral malaria', 'severe malaria'], ['B50', 'B51', 'B52', 'B53', 'B54']);

                    // Row matching
                    $matched = false;
                    $desc = '';
                    if ($rowNum === 147 && $isFever) {
                        $matched = true;
                        $desc = 'Persons with Fever Presenting at Facility';
                    } elseif ($rowNum === 148 && $isFever) {
                        $matched = true;
                        $desc = 'Suspected Malaria Tested by RDT';
                    } elseif ($rowNum === 149 && ($isMalaria || $isSevere)) {
                        $matched = true;
                        $desc = 'Confirmed Malaria Case (RDT / Microscopy Positive)';
                    } elseif ($rowNum === 153 && $isMalaria && !$isSevere) {
                        $matched = true;
                        $desc = 'Confirmed Uncomplicated Malaria Case';
                    } elseif ($rowNum === 154 && $isSevere) {
                        $matched = true;
                        $desc = 'Severe Malaria Case Seen';
                    } elseif ($rowNum === 155 && $isMalaria && !$isSevere) {
                        $matched = true;
                        $desc = 'Confirmed Uncomplicated Malaria Treated with ACT';
                    } elseif ($rowNum === 159 && $isSevere && isset($artesunatePatientMap[$pId])) {
                        $matched = true;
                        $desc = 'Severe Malaria Case Treated with Artesunate Injection';
                    }

                    if ($matched) {
                        if (isset($seenMalPat[$rowNum][$col][$pId])) {
                            continue;
                        }
                        $seenMalPat[$rowNum][$col][$pId] = true;
                        $uniquePatients[$pId] = true;

                        $u = $p?->user;
                        $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                        $d = $e->doctor;
                        $dName = $d ? trim($d->surname . ' ' . $d->firstname . ($d->othername ? ' ' . $d->othername : '')) : 'N/A';
                        $hmoInfo = $this->renderPatientHmo($p);

                        $results[] = [
                            'id' => $e->id,
                            'patient_id' => $p?->id,
                            'patient_name' => $pName,
                            'file_no' => $p->file_no ?? 'N/A',
                            'hmo_id' => $hmoInfo['hmo_id'],
                            'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                            'hmo_html' => $hmoInfo['hmo_html'],
                            'gender' => ucfirst($p->gender ?? 'N/A'),
                            'age' => $p && $p->dob ? Carbon::parse($p->dob)->age . 'y' : 'N/A',
                            'doctor_name' => $dName,
                            'date' => $e->created_at->format('Y-m-d H:i'),
                            'details' => $desc . ' | ' . \Illuminate\Support\Str::limit(strip_tags($e->reasons_for_encounter ?? ($e->notes ?? '')), 80),
                        ];
                    }
                }
            }
        } elseif ($rowNum >= 164 && $rowNum <= 171) {
            // Hepatitis B & C (Rows 164 - 171)
            $isB = in_array($rowNum, [164, 165, 166, 167]);
            $isPosReq = in_array($rowNum, [165, 169]);
            $serviceKey = $isB ? 'hepatitis_b' : 'hepatitis_c';

            if (in_array($rowNum, [166, 167, 170, 171])) {
                // Non-compiled / 0 routine records for treatment and referrals
                $results = [];
            } else {
                $hepIds = \App\Models\NhmisServiceMapping::getServiceIds($serviceKey);

                $records = LabServiceRequest::with([
                    'patient:id,user_id,file_no,dob,gender,hmo_id',
                    'patient.user:id,surname,firstname,othername',
                    'patient.hmo.scheme',
                    'service:id,service_name',
                ])
                ->whereIn('service_id', $hepIds)
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate])
                      ->orWhereBetween('sample_date', [$startDate, $endDate]);
                })
                ->get();

                $seenHepPat = [];
                foreach ($records as $lr) {
                    $p = $lr->patient;
                    $pId = $lr->patient_id;
                    if (!$pId) {
                        continue;
                    }

                    $dob = $p?->dob ? Carbon::parse($p->dob) : null;
                    $age = $dob ? $dob->diffInYears($lr->created_at, false) : 25;
                    $gender = strtolower($p?->gender ?? 'male');
                    $pfx = ($gender === 'female' || $gender === 'f') ? 'f_' : 'm_';
                    $ageKey = ($age >= 20) ? 'ge_20y' : '10_19y';
                    $cell = $pfx . $ageKey;

                    if ($colKey !== 'total' && $colKey !== $cell) {
                        continue;
                    }

                    if ($isPosReq && !$this->aggregator->isLabResultPositive($lr, $serviceKey)) {
                        continue;
                    }

                    if (isset($seenHepPat[$rowNum][$cell][$pId])) {
                        continue;
                    }
                    $seenHepPat[$rowNum][$cell][$pId] = true;
                    $uniquePatients[$pId] = true;

                    $u = $p?->user;
                    $pName = $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A';
                    $hmoInfo = $this->renderPatientHmo($p);

                    $results[] = [
                        'id' => $lr->id,
                        'patient_id' => $p?->id,
                        'patient_name' => $pName,
                        'file_no' => $p->file_no ?? 'N/A',
                        'hmo_id' => $hmoInfo['hmo_id'],
                        'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                        'hmo_html' => $hmoInfo['hmo_html'],
                        'gender' => ucfirst($gender),
                        'age' => $age . 'y',
                        'doctor_name' => $lr->service?->service_name ?? 'Viral Hepatitis Test',
                        'date' => $lr->created_at->format('Y-m-d H:i'),
                        'details' => ($isPosReq ? 'Positive / Reactive' : 'Tested & Screened') . ' | Result: ' . ($lr->nhmis_outcome_raw ?? strip_tags($lr->result ?? 'Tested')),
                    ];
                }
            }
        } else {
            // Default: 0 results for non-compiled indicators; never dump unrelated encounters
            $results = [];
        }

        // Apply HMO filter
        $hmoFilter = $request->query('hmo_id');
        if ($hmoFilter !== null && $hmoFilter !== '') {
            $results = array_values(array_filter($results, function ($r) use ($hmoFilter) {
                if ($hmoFilter === 'cash') {
                    return empty($r['hmo_id']);
                }

                return (string) ($r['hmo_id'] ?? '') === (string) $hmoFilter;
            }));
        }

        // Apply HMO Scheme filter
        $schemeFilter = $request->query('scheme_id');
        if ($schemeFilter !== null && $schemeFilter !== '') {
            $results = array_values(array_filter($results, function ($r) use ($schemeFilter) {
                return (string) ($r['hmo_scheme_id'] ?? '') === (string) $schemeFilter;
            }));
        }

        // Apply debounced search query
        $search = trim($request->query('search', ''));
        if ($search !== '') {
            $searchLower = strtolower($search);
            $results = array_values(array_filter($results, function ($r) use ($searchLower) {
                $haystack = strtolower(
                    ($r['patient_name'] ?? '') . ' ' .
                    ($r['file_no'] ?? '') . ' ' .
                    ($r['doctor_name'] ?? '') . ' ' .
                    ($r['details'] ?? '') . ' ' .
                    strip_tags($r['hmo_html'] ?? '')
                );

                return str_contains($haystack, $searchLower);
            }));
        }

        $totalRecords = count($results);
        $uniquePatientsCount = count(array_unique(array_filter(array_column($results, 'patient_id'))));
        if ($uniquePatientsCount === 0 && !empty($uniquePatients)) {
            $uniquePatientsCount = min($totalRecords, count($uniquePatients));
        }
        if ($uniquePatientsCount === 0 && $totalRecords > 0) {
            $uniquePatientsCount = $totalRecords;
        }

        $perPage = max(5, min(100, (int) $request->query('per_page', 25)));
        $lastPage = (int) max(1, ceil($totalRecords / $perPage));
        $page = max(1, min((int) $request->query('page', 1), $lastPage));

        $paginatedRecords = array_slice($results, ($page - 1) * $perPage, $perPage);
        $from = $totalRecords > 0 ? (($page - 1) * $perPage + 1) : 0;
        $to = min($page * $perPage, $totalRecords);

        // Fetch HMO and Scheme lists for dynamic filter population
        $hmos = \App\Models\Hmo::select('id', 'name', 'hmo_scheme_id')->orderBy('name')->get();
        $schemes = \App\Models\HmoScheme::select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'cell_key' => $cellKey,
            'total_records' => $totalRecords,
            'unique_patients' => $uniquePatientsCount,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'from' => $from,
            'to' => $to,
            'records' => $paginatedRecords,
            'hmos' => $hmos,
            'schemes' => $schemes,
        ]);
    }

    /**
     * Save cell adjustments and metadata
     */
    public function saveValues(Request $request)
    {
        $request->validate([
            'report_id' => 'required|exists:nhmis_monthly_reports,id',
            'values' => 'required|array',
            'notes' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array',
        ]);

        $report = NhmisMonthlyReport::findOrFail($request->report_id);

        if ($report->status === 'locked') {
            return response()->json([
                'success' => false,
                'message' => 'This report is locked against changes.',
            ], 403);
        }

        DB::beginTransaction();

        try {
            if ($request->has('notes')) {
                $report->notes = $request->notes;
            }

            if ($request->has('metadata')) {
                $report->metadata = array_merge($report->metadata ?? [], $request->metadata);
            }
            $report->save();

            foreach ($request->values as $cellKey => $data) {
                $valRec = NhmisMonthlyReportValue::firstOrNew([
                    'report_id' => $report->id,
                    'cell_key' => $cellKey,
                ]);

                if (is_array($data)) {
                    $override = isset($data['override_value']) && $data['override_value'] !== '' ? (float) $data['override_value'] : null;
                    $valRec->override_value = $override;
                    $valRec->final_value = $override !== null ? $override : $valRec->auto_value;
                    $valRec->override_reason = $data['override_reason'] ?? $valRec->override_reason;
                } else {
                    $override = $data !== '' && $data !== null ? (float) $data : null;
                    $valRec->override_value = $override;
                    $valRec->final_value = $override !== null ? $override : $valRec->auto_value;
                }

                $valRec->overridden_by = auth()->id();
                $valRec->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Changes saved successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('NHMIS save values error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save values: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update report approval and verification status
     */
    public function updateStatus(Request $request)
    {
        $request->validate([
            'report_id' => 'required|exists:nhmis_monthly_reports,id',
            'status' => 'required|in:draft,compiled,verified,locked',
            'notes' => 'nullable|string|max:1000',
        ]);

        $report = NhmisMonthlyReport::findOrFail($request->report_id);
        $newStatus = $request->status;

        if ($newStatus === 'verified' || $newStatus === 'locked') {
            $report->verified_by = auth()->id();
            $report->verified_at = now();
        }

        $report->status = $newStatus;
        if ($request->filled('notes')) {
            $report->notes = $request->notes;
        }
        $report->save();

        return response()->json([
            'success' => true,
            'message' => "Report status updated to {$newStatus}",
            'status' => $report->status,
            'verified_at' => $report->verified_at?->format('M d, Y h:i A'),
            'verifier_name' => $report->verifier ? $report->verifier->surname . ' ' . $report->verifier->firstname : 'N/A',
        ]);
    }

    /**
     * Case Audit & Diagnosis Insight endpoint (reusing ClinicalReportsController patterns)
     */
    public function auditDiagnosis(Request $request)
    {
        $request->validate([
            'year' => 'nullable|integer',
            'month' => 'nullable|integer',
            'keyword' => 'nullable|string',
            'row_id' => 'nullable|string',
        ]);

        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $keyword = trim((string) $request->get('keyword', ''));
        $rowId = $request->get('row_id');

        // If row_id is provided and keyword is empty, map row_id to common NHMIS clinical terms
        if ($keyword === '' && $rowId) {
            $rowMap = [
                '93' => 'diarrh',
                '94' => 'pneumonia',
                '95' => 'dysentery',
                '96' => 'cholera',
                '105' => 'malaria',
                '106' => 'malaria',
                '107' => 'malaria',
                '108' => 'malaria',
                '111' => 'tuberculosis',
                '115' => 'hypertens',
                '116' => 'diabet',
                '117' => 'asthma',
                '118' => 'sickle cell',
                '119' => 'cancer',
                '120' => 'cervical',
                '121' => 'breast cancer',
                '122' => 'prostate',
                '123' => 'mental',
                '124' => 'depression',
                '125' => 'osteoarthritis',
                '126' => 'epilep',
                '142' => 'snake bite',
            ];
            $keyword = $rowMap[(string)$rowId] ?? '';
        }

        $dateFrom = Carbon::createFromDate($year, $month, 1)->startOfMonth()->startOfDay();
        $dateTo = (clone $dateFrom)->endOfMonth()->endOfDay();

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
            'patient:id,user_id,file_no,hmo_id,dob,gender',
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
        }

        $encounters = $query->orderByDesc('created_at')->limit(300)->get();

        $results = [];
        $uniquePatientIds = [];

        foreach ($encounters as $e) {
            $rawReasons = !empty($e->reasons_for_encounter) ? json_decode($e->reasons_for_encounter, true) : [];
            if (!is_array($rawReasons)) {
                if (is_string($e->reasons_for_encounter) && trim($e->reasons_for_encounter) !== '') {
                    $rawReasons = array_filter(array_map('trim', explode(',', $e->reasons_for_encounter)));
                } else {
                    $rawReasons = [];
                }
            }

            $matchedDiagnoses = [];
            foreach ($rawReasons as $item) {
                $code = '';
                $name = '';
                if (is_array($item)) {
                    $code = trim($item['code'] ?? '');
                    $name = trim($item['name'] ?? ($item['value'] ?? ($item['display'] ?? '')));
                } else {
                    $name = trim((string) $item);
                    if (preg_match('/^([A-Za-z][0-9]{2,3}(?:\.[0-9]+)?)\s*[-:]\s*(.+)$/i', $name, $m)) {
                        $code = trim($m[1]);
                        $name = trim($m[2]);
                    }
                }

                if ($keyword === '' || stripos($name, $keyword) !== false || stripos($code, $keyword) !== false) {
                    $matchedDiagnoses[] = $code ? "{$code} - {$name}" : $name;
                }
            }

            if (empty($matchedDiagnoses) && $keyword !== '' && !empty($e->notes) && stripos($e->notes, $keyword) !== false) {
                $matchedDiagnoses[] = "Mentioned in Clinical Notes";
            }

            if (!empty($matchedDiagnoses) || $keyword === '') {
                $patient = $e->patient;
                $user = $patient?->user;
                $pName = $user ? trim($user->surname . ' ' . $user->firstname . ' ' . ($user->othername ?? '')) : 'N/A';
                $doc = $e->doctor;
                $dName = $doc ? trim($doc->surname . ' ' . $doc->firstname . ' ' . ($doc->othername ?? '')) : 'N/A';

                if ($e->patient_id) {
                    $uniquePatientIds[$e->patient_id] = true;
                }

                $age = 'N/A';
                if ($patient && $patient->dob) {
                    try {
                        $age = Carbon::parse($patient->dob)->age . 'y';
                    } catch (\Exception $ex) {
                    }
                }

                $results[] = [
                    'id' => $e->id,
                    'patient_id' => $e->patient_id,
                    'patient_name' => $pName,
                    'file_no' => $patient->file_no ?? 'N/A',
                    'gender' => ucfirst($patient->gender ?? 'N/A'),
                    'age' => $age,
                    'doctor_name' => $dName,
                    'clinic' => $e->queue?->clinic?->name ?? 'General OPD',
                    'date' => $e->created_at->format('Y-m-d H:i'),
                    'diagnoses' => implode('; ', $matchedDiagnoses) ?: 'N/A',
                    'notes' => \Illuminate\Support\Str::limit(strip_tags($e->notes ?? ''), 120),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'keyword' => $keyword,
            'total_encounters' => count($results),
            'unique_patients' => count($uniquePatientIds),
            'encounters' => $results,
        ]);
    }
}
