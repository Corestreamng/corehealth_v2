<?php

namespace App\Http\Controllers;

use App\Models\Encounter;
use App\Models\NhmisMonthlyReport;
use App\Models\NhmisMonthlyReportValue;
use App\Models\NhmisServiceMapping;
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
        $this->middleware(function ($request, $next) {
            $this->authorizeAccess();

            return $next($request);
        });
    }

    /**
     * Authorize access: accessible to Admins and Receptionists who are unit or department heads
     */
    public function authorizeAccess(): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        $isAdmin = $user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']);
        $staff = $user->staff_profile;
        $isQualifiedReceptionist = $user->hasAnyRole(['RECEPTIONIST', 'Receptionist']) && $staff && ($staff->is_unit_head || $staff->is_dept_head);

        if (!$isAdmin && !$isQualifiedReceptionist) {
            abort(403, 'Unauthorized access to NHMIS Monthly Summary Workbench. Access is restricted to Administrators and Health Records / Reception unit or department heads.');
        }
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
                    'compiler_name' => $report->compiler ? trim($report->compiler->surname . ' ' . $report->compiler->firstname . ' ' . ($report->compiler->othername ?? '')) : 'System',
                ],
                'values' => $freshValues->map(function ($val) {
                    return [
                        'auto' => $val->auto_value,
                        'override' => $val->override_value,
                        'final' => $val->final_value,
                        'is_overridden' => $val->is_overridden,
                        'unplaced' => (int) ($val->metadata['unplaced'] ?? 0),
                        'manual_reason' => $val->metadata['manual_reason'] ?? null,
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
        $mapping = null;
        if ((int) $serviceId > 0) {
            $mapping = NhmisServiceMapping::where('service_id', (int) $serviceId)->first();
        }

        if (!$mapping && request()->filled('service_name')) {
            $sName = trim(request('service_name'));
            $mapping = NhmisServiceMapping::whereHas('service', function ($q) use ($sName) {
                $q->where('service_name', $sName);
            })->first();

            // Keyword guessing is only for free-text items with no catalogue service
            // (they cannot be linked). Catalogue services are governed solely by the
            // Service Linking window, matching what the result-save controllers enforce.
            $code = (!$mapping && (int) $serviceId <= 0) ? NhmisServiceMapping::matchIndicatorByName($sName) : null;
            if ($code) {
                $meta = NhmisServiceMapping::INDICATORS[$code];
                $presets = NhmisServiceMapping::getPresetsForIndicator($code);

                return response()->json([
                    'success' => true,
                    'is_mapped' => true,
                    'indicator_code' => $code,
                    'indicator_label' => $meta['label'] ?? $code,
                    'service_type' => $meta['service_type'] ?? 'investigation',
                    'supported_outcomes' => $presets['supported'],
                    'positive_outcomes' => $presets['positive'],
                ]);
            }
        }

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
     * Drill Down: the exact evidence records behind a cell, produced by the same build that
     * computed the form value (so the record count always equals the number on the form).
     */
    public function drillDown(Request $request)
    {
        $request->validate([
            'cell_key' => 'required|string',
            'report_id' => 'required|exists:nhmis_monthly_reports,id',
        ]);

        $report = NhmisMonthlyReport::findOrFail($request->report_id);
        $cellKey = $request->cell_key;
        $evidence = $this->aggregator->getCellEvidence($report, $cellKey);
        $items = $evidence['records'];

        $patientIds = array_values(array_unique(array_filter(array_column($items, 'patient_id'))));
        $patients = \App\Models\Patient::with(['user:id,surname,firstname,othername', 'hmo.scheme'])
            ->whereIn('id', $patientIds ?: [0])
            ->get(['id', 'user_id', 'file_no', 'dob', 'gender', 'hmo_id'])
            ->keyBy('id');

        $results = [];
        foreach ($items as $it) {
            $p = $it['patient_id'] ? $patients->get($it['patient_id']) : null;
            $u = $p?->user;
            $hmoInfo = $this->renderPatientHmo($p);
            $results[] = [
                'id' => $it['id'],
                'patient_id' => $p?->id,
                'patient_name' => $u ? trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : '')) : 'N/A',
                'file_no' => $p->file_no ?? 'N/A',
                'hmo_id' => $hmoInfo['hmo_id'],
                'hmo_scheme_id' => $hmoInfo['hmo_scheme_id'],
                'hmo_html' => $hmoInfo['hmo_html'],
                'gender' => $p && $p->gender ? ucfirst($p->gender) : 'Not recorded',
                'age' => $p && $p->dob && $it['date'] ? Carbon::parse($p->dob)->diffInYears(Carbon::parse($it['date'])) . 'y' : 'DOB not recorded',
                'doctor_name' => $it['src'] . ' #' . $it['id'],
                'date' => $it['date'],
                'details' => e($it['detail']) . (($it['qty'] ?? 1) != 1 ? ' (qty ' . $it['qty'] . ')' : ''),
            ];
        }

        $hmoFilter = $request->query('hmo_id');
        if ($hmoFilter !== null && $hmoFilter !== '') {
            $results = array_values(array_filter($results, fn ($r) => $hmoFilter === 'cash' ? empty($r['hmo_id']) : (string) ($r['hmo_id'] ?? '') === (string) $hmoFilter));
        }
        $schemeFilter = $request->query('scheme_id');
        if ($schemeFilter !== null && $schemeFilter !== '') {
            $results = array_values(array_filter($results, fn ($r) => (string) ($r['hmo_scheme_id'] ?? '') === (string) $schemeFilter));
        }
        $search = strtolower(trim($request->query('search', '')));
        if ($search !== '') {
            $results = array_values(array_filter($results, fn ($r) => str_contains(strtolower($r['patient_name'] . ' ' . $r['file_no'] . ' ' . $r['doctor_name'] . ' ' . $r['details'] . ' ' . strip_tags($r['hmo_html'])), $search)));
        }

        $totalRecords = count($results);
        $perPage = max(5, min(100, (int) $request->query('per_page', 25)));
        $lastPage = (int) max(1, ceil($totalRecords / $perPage));
        $page = max(1, min((int) $request->query('page', 1), $lastPage));

        return response()->json([
            'success' => true,
            'cell_key' => $cellKey,
            'total_records' => $totalRecords,
            'unique_patients' => count(array_unique(array_filter(array_column($results, 'patient_id')))),
            'manual_reason' => $evidence['manual_reason'],
            'unplaced' => $evidence['unplaced'],
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'from' => $totalRecords > 0 ? (($page - 1) * $perPage + 1) : 0,
            'to' => min($page * $perPage, $totalRecords),
            'records' => array_slice($results, ($page - 1) * $perPage, $perPage),
            'hmos' => \App\Models\Hmo::select('id', 'name', 'hmo_scheme_id')->orderBy('name')->get(),
            'schemes' => \App\Models\HmoScheme::select('id', 'name')->orderBy('name')->get(),
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
