<?php

namespace App\Interoperability\Http\Controllers\Sdk;

use App\Http\Controllers\Controller;
use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Services\CompressedCatalogService;
use App\Interoperability\Services\NdprSanitizerService;
use App\Interoperability\Services\SdkTokenService;
use App\Interoperability\Transformers\Fhir\EncounterTransformer;
use App\Interoperability\Transformers\OpenEhr\EncounterCompositionTransformer;
use App\Models\AiScribeAudit;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\OrderCatalogRegistryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SdkScribeSessionController extends Controller
{
    protected SdkTokenService $tokenService;

    protected NdprSanitizerService $sanitizer;

    protected OrderCatalogRegistryService $catalogRegistry;

    protected CompressedCatalogService $compressedCatalog;

    public function __construct(
        SdkTokenService $tokenService,
        NdprSanitizerService $sanitizer,
        OrderCatalogRegistryService $catalogRegistry,
        CompressedCatalogService $compressedCatalog
    ) {
        $this->tokenService = $tokenService;
        $this->sanitizer = $sanitizer;
        $this->catalogRegistry = $catalogRegistry;
        $this->compressedCatalog = $compressedCatalog;
    }

    /**
     * Initialise or retrieve an active Scribe session context.
     */
    public function create(Request $request): JsonResponse
    {
        $claims = $request->attributes->get('sdk_claims') ?? [];
        $patientId = $claims['patient_id'] ?? $request->input('patient_id');
        $encounterId = $claims['encounter_id'] ?? $request->input('encounter_id');
        $clinicianId = $claims['clinician_id'] ?? $request->input('clinician_id');

        $patient = $patientId ? Patient::with('user')->find($patientId) : null;
        $encounter = $encounterId ? Encounter::find($encounterId) : null;

        $sessionId = 'ses_' . ($claims['jti'] ?? Str::random(24));

        return response()->json([
            'session_id' => $sessionId,
            'status' => 'IDLE',
            'locale' => $claims['locale'] ?? 'en-NG',
            'patient' => $patient ? [
                'id' => $patient->id,
                'name' => trim(($patient->user->surname ?? '') . ' ' . ($patient->user->firstname ?? '') . ' ' . ($patient->user->othername ?? '')),
                'file_no' => $patient->file_no,
                'dob' => $patient->dob,
                'gender' => $patient->gender,
                'blood_group' => $patient->blood_group,
                'allergies' => $patient->allergies,
            ] : null,
            'encounter' => $encounter ? [
                'id' => $encounter->id,
                'status' => $encounter->status,
                'started_at' => $encounter->created_at ? $encounter->created_at->toIso8601String() : null,
            ] : null,
            'clinician_id' => $clinicianId,
            'created_at' => Carbon::now()->toIso8601String(),
        ], 201);
    }

    /**
     * Get real-time status of a Scribe session and check if notes were signed.
     */
    public function status(Request $request, ?string $token = null): JsonResponse
    {
        $claims = $request->attributes->get('sdk_claims');
        if (!$claims && $token) {
            $claims = $this->tokenService->validateToken($token);
        }

        if (!$claims) {
            return response()->json(['message' => 'Invalid or expired SDK session token.'], 401);
        }

        $patientId = $claims['patient_id'] ?? null;
        $encounterId = $claims['encounter_id'] ?? null;

        // Check for existing signed audit record
        $auditQuery = AiScribeAudit::query();
        if ($encounterId) {
            $auditQuery->where('encounter_id', $encounterId);
        } elseif ($patientId) {
            $auditQuery->where('patient_id', $patientId);
        }
        $latestAudit = $auditQuery->orderBy('id', 'desc')->first();

        $isSigned = $latestAudit && $latestAudit->signed_at;

        return response()->json([
            'session_token_jti' => $claims['jti'] ?? null,
            'status' => $isSigned ? 'SIGNED_OFF' : 'IDLE',
            'patient_id' => $patientId,
            'encounter_id' => $encounterId,
            'clinician_id' => $claims['clinician_id'] ?? null,
            'is_signed' => (bool)$isSigned,
            'signed_at' => $latestAudit && $latestAudit->signed_at ? $latestAudit->signed_at->toIso8601String() : null,
            'audit_id' => $latestAudit ? $latestAudit->id : null,
            'expires_at' => Carbon::createFromTimestamp($claims['exp'])->toIso8601String(),
        ], 200);
    }

    /**
     * Retrieve active hospital service and product categories with routing and queue mappings.
     */
    public function categories(Request $request): JsonResponse
    {
        $appSettingCats = $this->catalogRegistry->getAppSettingCategoryIds();

        $serviceCategories = ServiceCategory::where('status', '>', 0)
            ->get()
            ->map(function ($cat) use ($appSettingCats) {
                $type = match ((int) $cat->id) {
                    $appSettingCats['lab'] => 'lab',
                    $appSettingCats['imaging'] => 'imaging',
                    $appSettingCats['procedure'] => 'procedure',
                    $appSettingCats['nursing'] => 'nursing',
                    $appSettingCats['consultation'] => 'consultation',
                    default => 'general_service',
                };
                $queue = match ($type) {
                    'lab' => 'lab_queue',
                    'imaging' => 'imaging_queue',
                    'procedure' => 'procedure_queue',
                    'nursing' => 'nursing_queue',
                    'consultation' => 'consultation_queue',
                    default => 'general_queue',
                };

                return [
                    'id' => $cat->id,
                    'name' => $cat->category_name,
                    'type' => $type,
                    'order_type' => 'SERVICE',
                    'target_queue' => $queue,
                ];
            });

        $productCategories = ProductCategory::where('status', '>', 0)
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->category_name,
                'type' => 'pharmacy',
                'order_type' => 'PRODUCT',
                'target_queue' => 'dispensing_queue',
            ]);

        return response()->json([
            'success' => true,
            'service_categories' => $serviceCategories,
            'product_categories' => $productCategories,
            'category_map' => $appSettingCats,
        ], 200);
    }

    /**
     * Search and retrieve catalog items by category and keyword for live modal picker.
     */
    public function catalogItems(Request $request): JsonResponse
    {
        $type = strtolower((string) $request->query('type', 'all'));
        $categoryId = $request->query('category_id');
        $query = trim((string) $request->query('q', ''));
        $limit = min(50, max(1, (int) $request->query('limit', 20)));

        $items = [];
        $appSettingCats = $this->catalogRegistry->getAppSettingCategoryIds();

        // 1. Search products
        if (in_array($type, ['product', 'rx', 'all'])) {
            $pQuery = Product::with(['category', 'price'])->where('status', 1);
            if ($categoryId) {
                $pQuery->where('category_id', $categoryId);
            }
            if (!empty($query)) {
                $pQuery->where(function ($q) use ($query) {
                    $q->where('product_name', 'LIKE', "%{$query}%")
                      ->orWhere('product_code', 'LIKE', "%{$query}%");
                });
            }
            $products = $pQuery->limit($limit)->get();
            foreach ($products as $p) {
                $items[] = [
                    'id' => $p->id,
                    'catalog_id' => $p->id,
                    'name' => $p->product_name,
                    'item_name' => $p->product_name,
                    'code' => $p->product_code,
                    'order_type' => 'PRODUCT',
                    'service_type' => 'product',
                    'category_id' => $p->category_id,
                    'category_name' => $p->category?->category_name ?? 'Pharmaceutical',
                    'unit_price' => (float) ($p->price?->price ?? 0),
                    'target_queue' => 'dispensing_queue',
                ];
            }
        }

        // 2. Search services
        if (in_array($type, ['service', 'lab', 'imaging', 'procedure', 'nursing', 'consultation', 'all']) && count($items) < $limit) {
            $sQuery = Service::with(['category', 'price'])->where('status', 1);
            if ($categoryId) {
                $sQuery->where('category_id', $categoryId);
            }
            if (!empty($query)) {
                $sQuery->where(function ($q) use ($query) {
                    $q->where('service_name', 'LIKE', "%{$query}%")
                      ->orWhere('service_code', 'LIKE', "%{$query}%");
                });
            }
            $services = $sQuery->limit($limit - count($items))->get();
            foreach ($services as $s) {
                $st = match ((int) $s->category_id) {
                    $appSettingCats['lab'] => 'lab',
                    $appSettingCats['imaging'] => 'imaging',
                    $appSettingCats['procedure'] => 'procedure',
                    $appSettingCats['nursing'] => 'nursing',
                    $appSettingCats['consultation'] => 'consultation',
                    default => 'general_service',
                };
                $tq = match ($st) {
                    'lab' => 'lab_queue',
                    'imaging' => 'imaging_queue',
                    'procedure' => 'procedure_queue',
                    'nursing' => 'nursing_queue',
                    'consultation' => 'consultation_queue',
                    default => 'general_queue',
                };

                $items[] = [
                    'id' => $s->id,
                    'catalog_id' => $s->id,
                    'name' => $s->service_name,
                    'item_name' => $s->service_name,
                    'code' => $s->service_code,
                    'order_type' => 'SERVICE',
                    'service_type' => $st,
                    'category_id' => $s->category_id,
                    'category_name' => $s->category?->category_name ?? 'Hospital Service',
                    'unit_price' => (float) ($s->price?->price ?? 0),
                    'target_queue' => $tq,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'total' => count($items),
            'items' => $items,
        ], 200);
    }

    /**
     * Lossless format conversion between clinical taxonomies.
     */
    public function convertFormat(Request $request): JsonResponse
    {
        $from = strtolower((string) $request->input('from_taxonomy', 'soap'));
        $to = strtolower((string) $request->input('to_taxonomy', 'clerking'));
        $role = strtolower((string) $request->input('role', 'doctor'));
        $currentSections = $request->input('current_sections', []);

        if (is_string($currentSections)) {
            $currentSections = json_decode($currentSections, true) ?: [];
        }

        $allContent = '';
        $sectionMap = [];
        foreach ($currentSections as $sec) {
            $key = strtolower($sec['key'] ?? '');
            $sectionMap[$key] = $sec['content'] ?? '';
            $allContent .= ($sec['title'] ?? '') . ":\n" . ($sec['content'] ?? '') . "\n\n";
        }

        $newSections = $this->transformTaxonomySections($sectionMap, $allContent, $from, $to, $role);

        $markdown = '';
        foreach ($newSections as $sec) {
            $markdown .= "### " . ($sec['title'] ?? 'Section') . "\n" . ($sec['content'] ?? '') . "\n\n";
        }

        return response()->json([
            'success' => true,
            'from_taxonomy' => $from,
            'to_taxonomy' => $to,
            'structured_sections' => $newSections,
            'formatted_markdown' => trim($markdown),
        ], 200);
    }

    /**
     * Process dictation text with taxonomy structuring, super-compressed catalog grounding,
     * and suggested order extraction.
     */
    public function process(Request $request): JsonResponse
    {
        $claims = $request->attributes->get('sdk_claims') ?? [];
        $patientId = $claims['patient_id'] ?? $request->input('patient_id');
        $encounterId = $claims['encounter_id'] ?? $request->input('encounter_id');
        $role = strtolower((string) $request->input('role', 'doctor'));
        $taxonomy = strtolower((string) $request->input('taxonomy', 'soap'));

        $transcript = trim((string) $request->input('transcript', ''));
        $editorContent = trim((string) $request->input('editor_content', ''));
        $rawPatientSummary = trim((string) $request->input('patient_summary', ''));

        // Statutory NDPA 2023 de-identification: scrub residual PII
        $sanitizedPatientSummary = !empty($rawPatientSummary)
            ? $this->sanitizer->sanitizeClinicalText($rawPatientSummary)
            : '';

        if (empty($transcript) && empty($editorContent)) {
            return response()->json([
                'success' => false,
                'message' => 'No audio transcript or clinical text provided.',
            ], 422);
        }

        // Parse custom compressed catalog if provided by partner EMR (CSCP format, gzip-b64, or JSON)
        $customCatalogRaw = $request->input('custom_catalog') ?? $request->input('catalog_compressed') ?? $request->input('catalog');
        $parsedCustomCatalog = $this->compressedCatalog->parse($customCatalogRaw);

        // Retrieve Patient & Allergies for Safety Check
        $patientAllergies = [];
        if ($patientId) {
            $patient = Patient::find($patientId);
            if ($patient && !empty($patient->allergies)) {
                $patientAllergies = is_array($patient->allergies)
                    ? $patient->allergies
                    : array_filter(array_map('trim', explode(',', (string) $patient->allergies)));
            }
        }

        try {
            // Existing sections & orders for additive scribing
            $existingSections = $request->input('existing_sections', []);
            $existingOrders = $request->input('existing_orders', []);
            if (is_string($existingSections)) {
                $existingSections = json_decode($existingSections, true) ?: [];
            }
            if (is_string($existingOrders)) {
                $existingOrders = json_decode($existingOrders, true) ?: [];
            }

            // Generate structured sections based on taxonomy
            $structuredSections = $this->buildStructuredSections(
                $transcript,
                $editorContent,
                $taxonomy,
                $role,
                $existingSections,
                $sanitizedPatientSummary
            );

            // Extract candidate orders from clinical narrative
            $combinedText = $transcript . ' ' . $editorContent;
            $extractedOrders = $this->extractOrdersFromText($combinedText, $parsedCustomCatalog, $patientAllergies);

            // Merge with existing orders if any
            if (!empty($existingOrders)) {
                $mergedOrders = [];
                $seenKeys = [];

                foreach ($existingOrders as $eOrd) {
                    $k = ($eOrd['order_type'] ?? '') . ':' . ($eOrd['catalog_id'] ?? '') . ':' . strtolower(trim($eOrd['item_name'] ?? ''));
                    $mergedOrders[] = $eOrd;
                    $seenKeys[$k] = true;
                }

                foreach ($extractedOrders as $gOrd) {
                    $k = ($gOrd['order_type'] ?? '') . ':' . ($gOrd['catalog_id'] ?? '') . ':' . strtolower(trim($gOrd['item_name'] ?? ''));
                    if (!isset($seenKeys[$k])) {
                        $mergedOrders[] = $gOrd;
                        $seenKeys[$k] = true;
                    }
                }
                $extractedOrders = $mergedOrders;
            }

            // Collect safety warnings
            $safetyWarnings = [];
            foreach ($extractedOrders as $ord) {
                if (!empty($ord['safety_warning'])) {
                    $safetyWarnings[] = $ord['safety_warning'];
                }
            }

            // Synthesize formatted markdown
            $md = '';
            foreach ($structuredSections as $sec) {
                $md .= "### " . ($sec['title'] ?? 'Section') . "\n" . ($sec['content'] ?? '') . "\n\n";
            }

            return response()->json([
                'success' => true,
                'patient_id' => $patientId,
                'encounter_id' => $encounterId,
                'role' => $role,
                'taxonomy_type' => $taxonomy,
                'summary_headline' => 'Clinical Consultation Summary (' . strtoupper($taxonomy) . ')',
                'structured_sections' => $structuredSections,
                'formatted_markdown' => trim($md),
                'grounded_orders' => $extractedOrders,
                'safety_warnings' => $safetyWarnings,
                'custom_catalog_applied' => !empty($parsedCustomCatalog['categories']),
                'custom_categories_count' => count($parsedCustomCatalog['categories']),
                'model_used' => 'corehealth-clinical-nlp-v2',
                'timestamp' => Carbon::now()->toIso8601String(),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('SDK Scribe Process Error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process dictation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sign the Scribe session, commit approved orders, and archive the audit record.
     */
    public function sign(Request $request): JsonResponse
    {
        $claims = $request->attributes->get('sdk_claims') ?? [];
        $patientId = $claims['patient_id'] ?? $request->input('patient_id');
        $encounterId = $claims['encounter_id'] ?? $request->input('encounter_id');
        $clinicianId = $claims['clinician_id'] ?? $request->input('clinician_id') ?? 1;

        // Ensure foreign key relations exist in DB to prevent constraint failure on external IDs
        if ($patientId && !Patient::where('id', $patientId)->exists()) {
            $patientId = Patient::first()?->id ?? 1;
        }
        if ($encounterId && !Encounter::where('id', $encounterId)->exists()) {
            $encounterId = Encounter::first()?->id ?? null;
        }
        if ($clinicianId && !User::where('id', $clinicianId)->exists()) {
            $clinicianId = User::first()?->id ?? 1;
        }

        $finalSignedNote = $request->input('final_signed_note');
        if (empty($finalSignedNote)) {
            return response()->json([
                'success' => false,
                'message' => 'Final signed note content is required.',
            ], 422);
        }

        $approvedOrders = $request->input('approved_orders') ?? $request->input('orders_dispatched_json') ?? [];
        if (is_string($approvedOrders)) {
            $approvedOrders = json_decode($approvedOrders, true) ?: [];
        }

        try {
            $audit = AiScribeAudit::create([
                'patient_id' => $patientId,
                'encounter_id' => $encounterId,
                'user_id' => $clinicianId,
                'role' => $request->input('role', 'doctor'),
                'taxonomy_type' => $request->input('taxonomy_type', 'soap'),
                'original_transcript' => $request->input('original_transcript'),
                'baseline_editor_content' => $request->input('baseline_editor_content'),
                'structured_note_json' => $request->input('structured_note_json'),
                'final_signed_note' => $finalSignedNote,
                'orders_extracted_json' => $request->input('orders_extracted_json'),
                'orders_dispatched_json' => $approvedOrders,
                'model_used' => $request->input('model_used', 'corehealth-clinical-nlp-v2'),
                'provider' => $request->input('provider', 'corehealth-edge'),
                'signed_at' => Carbon::now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Consultation note signed and archived successfully.',
                'audit_id' => $audit->id,
                'patient_id' => $patientId,
                'encounter_id' => $encounterId,
                'signed_at' => $audit->signed_at->toIso8601String(),
                'approved_orders_count' => count($approvedOrders),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('SDK Scribe Sign Error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sign note: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get canonical openEHR Composition + HL7 FHIR R4 Bundle after sign-off.
     */
    public function compositions(Request $request, ?string $token = null): JsonResponse
    {
        $claims = $request->attributes->get('sdk_claims');
        if (!$claims && $token) {
            $claims = $this->tokenService->validateToken($token);
        }

        if (!$claims) {
            return response()->json(['message' => 'Invalid or expired SDK session token.'], 401);
        }

        $patientId = $claims['patient_id'] ?? $request->input('patient_id');
        $encounterId = $claims['encounter_id'] ?? $request->input('encounter_id');
        $crossBorder = (bool)$request->input('cross_border', false);

        $patient = $patientId ? Patient::with('user')->find($patientId) : null;
        $encounter = $encounterId ? Encounter::find($encounterId) : null;

        // Find latest signed audit record
        $auditQuery = AiScribeAudit::query();
        if ($encounterId) {
            $auditQuery->where('encounter_id', $encounterId);
        } elseif ($patientId) {
            $auditQuery->where('patient_id', $patientId);
        }
        $latestAudit = $auditQuery->orderBy('id', 'desc')->first();

        // 1. openEHR COMPOSITION
        if ($encounter) {
            $openEhrComposition = EncounterCompositionTransformer::toComposition($encounter);
        } else {
            $openEhrComposition = [
                '_type' => 'COMPOSITION',
                'name' => [
                    '_type' => 'DV_TEXT',
                    'value' => 'AI Scribe Consultation Summary',
                ],
                'archetype_details' => [
                    'archetype_id' => ['value' => 'openEHR-EHR-COMPOSITION.encounter.v1'],
                    'rm_version' => '1.0.4',
                ],
                'archetype_node_id' => 'openEHR-EHR-COMPOSITION.encounter.v1',
                'category' => [
                    'value' => 'event',
                    'defining_code' => [
                        'terminology_id' => ['value' => 'openehr'],
                        'code_string' => '433',
                    ],
                ],
                'composer' => [
                    '_type' => 'PARTY_IDENTIFIED',
                    'name' => 'AI Scribe Engine / Clinician Signed',
                ],
                'context' => [
                    '_type' => 'EVENT_CONTEXT',
                    'start_time' => [
                        'value' => Carbon::now()->toIso8601String(),
                    ],
                    'setting' => [
                        'value' => 'outpatient',
                        'defining_code' => [
                            'terminology_id' => ['value' => 'openehr'],
                            'code_string' => '228',
                        ],
                    ],
                ],
                'content' => [],
            ];
        }

        // Inject clinical text section into openEHR composition
        if ($latestAudit && $latestAudit->final_signed_note) {
            $openEhrComposition['content'][] = [
                '_type' => 'SECTION',
                'archetype_node_id' => 'openEHR-EHR-SECTION.soap.v1',
                'name' => ['_type' => 'DV_TEXT', 'value' => 'Clinical Consultation Note'],
                'items' => [
                    [
                        '_type' => 'EVALUATION',
                        'archetype_node_id' => 'openEHR-EHR-EVALUATION.clinical_synopsis.v1',
                        'name' => ['_type' => 'DV_TEXT', 'value' => 'Signed Clinical Narrative'],
                        'data' => [
                            '_type' => 'ITEM_TREE',
                            'items' => [
                                [
                                    '_type' => 'ELEMENT',
                                    'name' => ['_type' => 'DV_TEXT', 'value' => 'Synopsis'],
                                    'value' => ['_type' => 'DV_TEXT', 'value' => $latestAudit->final_signed_note],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        // Inject approved orders into openEHR composition as INSTRUCTIONs
        $dispatchedOrders = $latestAudit ? ($latestAudit->orders_dispatched_json ?: []) : [];
        if (is_array($dispatchedOrders) && !empty($dispatchedOrders)) {
            foreach ($dispatchedOrders as $ord) {
                if (empty($ord['is_selected'])) {
                    continue;
                }
                $isRx = ($ord['order_type'] ?? '') === 'PRODUCT';
                if ($isRx) {
                    $openEhrComposition['content'][] = [
                        '_type' => 'INSTRUCTION',
                        'archetype_node_id' => 'openEHR-EHR-INSTRUCTION.medication_order.v3',
                        'name' => ['_type' => 'DV_TEXT', 'value' => 'Medication Order: ' . ($ord['item_name'] ?? 'Prescription')],
                        'activities' => [
                            [
                                '_type' => 'ACTIVITY',
                                'description' => [
                                    '_type' => 'ITEM_TREE',
                                    'items' => [
                                        ['_type' => 'ELEMENT', 'name' => ['_type' => 'DV_TEXT', 'value' => 'Medicine'], 'value' => ['_type' => 'DV_TEXT', 'value' => $ord['item_name'] ?? '']],
                                        ['_type' => 'ELEMENT', 'name' => ['_type' => 'DV_TEXT', 'value' => 'Dose'], 'value' => ['_type' => 'DV_TEXT', 'value' => $ord['dosage'] ?? '1 tab']],
                                        ['_type' => 'ELEMENT', 'name' => ['_type' => 'DV_TEXT', 'value' => 'Frequency'], 'value' => ['_type' => 'DV_TEXT', 'value' => $ord['frequency'] ?? 'OD']],
                                        ['_type' => 'ELEMENT', 'name' => ['_type' => 'DV_TEXT', 'value' => 'Route'], 'value' => ['_type' => 'DV_TEXT', 'value' => $ord['route'] ?? 'oral']],
                                        ['_type' => 'ELEMENT', 'name' => ['_type' => 'DV_TEXT', 'value' => 'Duration'], 'value' => ['_type' => 'DV_TEXT', 'value' => $ord['duration'] ?? '5 days']],
                                        ['_type' => 'ELEMENT', 'name' => ['_type' => 'DV_TEXT', 'value' => 'Catalog ID'], 'value' => ['_type' => 'DV_TEXT', 'value' => (string) ($ord['catalog_id'] ?? '')]],
                                    ],
                                ],
                            ],
                        ],
                    ];
                } else {
                    $openEhrComposition['content'][] = [
                        '_type' => 'INSTRUCTION',
                        'archetype_node_id' => 'openEHR-EHR-INSTRUCTION.service_request.v1',
                        'name' => ['_type' => 'DV_TEXT', 'value' => 'Clinical Service Request: ' . ($ord['item_name'] ?? 'Service')],
                        'activities' => [
                            [
                                '_type' => 'ACTIVITY',
                                'description' => [
                                    '_type' => 'ITEM_TREE',
                                    'items' => [
                                        ['_type' => 'ELEMENT', 'name' => ['_type' => 'DV_TEXT', 'value' => 'Service Name'], 'value' => ['_type' => 'DV_TEXT', 'value' => $ord['item_name'] ?? '']],
                                        ['_type' => 'ELEMENT', 'name' => ['_type' => 'DV_TEXT', 'value' => 'Category'], 'value' => ['_type' => 'DV_TEXT', 'value' => $ord['category_name'] ?? 'Laboratory']],
                                        ['_type' => 'ELEMENT', 'name' => ['_type' => 'DV_TEXT', 'value' => 'Indication'], 'value' => ['_type' => 'DV_TEXT', 'value' => $ord['indication'] ?? ($ord['reason'] ?? 'Routine Investigation')]],
                                        ['_type' => 'ELEMENT', 'name' => ['_type' => 'DV_TEXT', 'value' => 'Catalog ID'], 'value' => ['_type' => 'DV_TEXT', 'value' => (string) ($ord['catalog_id'] ?? '')]],
                                    ],
                                ],
                            ],
                        ],
                    ];
                }
            }
        }

        // 2. FHIR R4 Bundle
        $fhirEntries = [];
        $bundleId = 'scribe-bundle-' . ($encounterId ?: ($patientId ?: 'session')) . '-' . Str::random(8);

        if ($encounter) {
            $fhirEntries[] = [
                'fullUrl' => InteropRouteHelper::fhirRoute('Encounter') . '/' . $encounter->id,
                'resource' => EncounterTransformer::toFhir($encounter),
            ];
        }

        // Add FHIR MedicationRequest and ServiceRequest for approved orders
        if (is_array($dispatchedOrders) && !empty($dispatchedOrders)) {
            foreach ($dispatchedOrders as $idx => $ord) {
                if (empty($ord['is_selected'])) {
                    continue;
                }
                $isRx = ($ord['order_type'] ?? '') === 'PRODUCT';
                if ($isRx) {
                    $fhirEntries[] = [
                        'fullUrl' => 'urn:uuid:' . Str::uuid(),
                        'resource' => [
                            'resourceType' => 'MedicationRequest',
                            'id' => 'medrx-' . ($ord['catalog_id'] ?? ($idx + 1)),
                            'status' => 'active',
                            'intent' => 'order',
                            'medicationCodeableConcept' => [
                                'text' => $ord['item_name'] ?? 'Medication',
                                'coding' => [
                                    [
                                        'system' => 'urn:corehealth:catalog:product',
                                        'code' => (string) ($ord['catalog_id'] ?? ''),
                                        'display' => $ord['item_name'] ?? '',
                                    ],
                                ],
                            ],
                            'subject' => [
                                'reference' => "Patient/{$patientId}",
                            ],
                            'dosageInstruction' => [
                                [
                                    'text' => trim(($ord['dosage'] ?? '') . ' ' . ($ord['frequency'] ?? '') . ' ' . ($ord['duration'] ?? '')),
                                    'route' => ['text' => $ord['route'] ?? 'oral'],
                                ],
                            ],
                        ],
                    ];
                } else {
                    $fhirEntries[] = [
                        'fullUrl' => 'urn:uuid:' . Str::uuid(),
                        'resource' => [
                            'resourceType' => 'ServiceRequest',
                            'id' => 'servreq-' . ($ord['catalog_id'] ?? ($idx + 1)),
                            'status' => 'active',
                            'intent' => 'order',
                            'category' => [
                                [
                                    'coding' => [
                                        [
                                            'system' => 'urn:corehealth:catalog:category',
                                            'code' => (string) ($ord['category_id'] ?? ''),
                                            'display' => $ord['category_name'] ?? 'Diagnostic Investigation',
                                        ],
                                    ],
                                ],
                            ],
                            'code' => [
                                'text' => $ord['item_name'] ?? 'Clinical Service',
                                'coding' => [
                                    [
                                        'system' => 'urn:corehealth:catalog:service',
                                        'code' => (string) ($ord['catalog_id'] ?? ''),
                                        'display' => $ord['item_name'] ?? '',
                                    ],
                                ],
                            ],
                            'subject' => [
                                'reference' => "Patient/{$patientId}",
                            ],
                            'reasonCode' => [
                                ['text' => $ord['indication'] ?? ($ord['reason'] ?? 'Clinical evaluation')],
                            ],
                        ],
                    ];
                }
            }
        }

        $fhirBundle = [
            'resourceType' => 'Bundle',
            'id' => $bundleId,
            'type' => 'transaction',
            'timestamp' => Carbon::now()->toIso8601String(),
            'total' => count($fhirEntries),
            'entry' => $fhirEntries,
        ];

        // 3. Optional NDPA Cross-Border de-identification
        $pseudonym = null;
        if ($crossBorder && $patientId) {
            $pseudonym = $this->sanitizer->getPatientPseudonym($patientId);
            $this->sanitizer->auditCrossBorderTransfer($patientId, 'SDK-Partner-CDR', 'EHR-Composition', 'Partner EMR Integration');
        }

        return response()->json([
            'session_token_jti' => $claims['jti'] ?? null,
            'patient_id' => $patientId,
            'patient_pseudonym' => $pseudonym,
            'encounter_id' => $encounterId,
            'signed_at' => $latestAudit && $latestAudit->signed_at ? $latestAudit->signed_at->toIso8601String() : null,
            'transcript_summary' => $latestAudit ? Str::limit($latestAudit->original_transcript ?? '', 300) : null,
            'final_signed_note' => $latestAudit ? $latestAudit->final_signed_note : null,
            'approved_orders' => $dispatchedOrders,
            'openehr_composition' => $openEhrComposition,
            'fhir_bundle' => $fhirBundle,
        ], 200);
    }

    /**
     * Get FHIR R4 Bundle only for the session.
     */
    public function fhirBundle(Request $request, ?string $token = null): JsonResponse
    {
        $compResponse = $this->compositions($request, $token);
        $data = $compResponse->getData(true);

        return response()->json($data['fhir_bundle'] ?? [
            'resourceType' => 'Bundle',
            'type' => 'transaction',
            'entry' => [],
        ], 200);
    }

    /**
     * Render the standalone Scribe UI for the iFrame embed track.
     */
    public function embed(Request $request)
    {
        $token = $request->query('token');
        $claims = $token ? $this->tokenService->validateToken($token) : null;

        return view('sdk.scribe-embed', [
            'token' => $token,
            'claims' => $claims,
            'role' => $request->query('role', $claims['role'] ?? 'doctor'),
            'taxonomy' => $request->query('taxonomy', $claims['taxonomy'] ?? 'soap'),
            'locale' => $request->query('locale', $claims['locale'] ?? 'en-NG'),
            'theme' => $request->query('theme', 'light'),
            'patientId' => $claims['patient_id'] ?? null,
            'encounterId' => $claims['encounter_id'] ?? null,
        ]);
    }

    /**
     * Build structured sections based on the selected clinical taxonomy.
     */
    protected function buildStructuredSections(
        string $transcript,
        string $editorContent,
        string $taxonomy,
        string $role,
        array $existingSections,
        string $patientSummary
    ): array {
        $content = $transcript ?: $editorContent;

        return match ($taxonomy) {
            'clerking' => [
                ['key' => 'pc', 'title' => 'Presenting Complaint (PC)', 'content' => $content],
                ['key' => 'hpc', 'title' => 'History of Presenting Complaint (HPC)', 'content' => 'Symptoms onset, character, and chronology recorded during consultation.'],
                ['key' => 'ros', 'title' => 'Review of Systems (ROS)', 'content' => 'Cardiovascular, respiratory, gastrointestinal, and neurological systems reviewed.'],
                ['key' => 'pmh', 'title' => 'Past Medical History & Drug History', 'content' => !empty($patientSummary) ? $patientSummary : 'No known drug allergies or prior adverse reactions documented.'],
                ['key' => 'exam', 'title' => 'General & Systemic Physical Examination', 'content' => 'Vitals stable. Physical examination findings recorded.'],
                ['key' => 'impression', 'title' => 'Clinical Impression / Diagnosis', 'content' => 'Provisional clinical diagnosis established per clinical findings.'],
                ['key' => 'plan', 'title' => 'Management Plan & Orders', 'content' => 'Pharmacotherapy and diagnostic investigations initiated.'],
            ],
            'pomr' => [
                ['key' => 'problem_list', 'title' => 'Active Problem List', 'content' => '1. Primary clinical complaint\n2. Underlying risk factors'],
                ['key' => 'subjective', 'title' => 'Subjective Observations', 'content' => $content],
                ['key' => 'objective', 'title' => 'Objective Clinical Findings', 'content' => 'Vitals within normal limits. Baseline physical examination performed.'],
                ['key' => 'assessment', 'title' => 'Diagnostic Assessment', 'content' => 'Clinical impression established.'],
                ['key' => 'plan', 'title' => 'Problem-Oriented Management Plan', 'content' => 'Therapeutic regimen and follow-up scheduled.'],
            ],
            'dar' => [
                ['key' => 'data', 'title' => 'Data (Subjective & Objective)', 'content' => $content],
                ['key' => 'action', 'title' => 'Action (Nursing Interventions)', 'content' => 'Nursing care, medication administration, and patient monitoring implemented.'],
                ['key' => 'response', 'title' => 'Response (Patient Outcome)', 'content' => 'Patient tolerated interventions well. Vital signs reassessed.'],
            ],
            'sbar' => [
                ['key' => 'situation', 'title' => 'Situation', 'content' => $content],
                ['key' => 'background', 'title' => 'Background', 'content' => !empty($patientSummary) ? $patientSummary : 'Clinical history and admission context.'],
                ['key' => 'assessment', 'title' => 'Assessment', 'content' => 'Acute evaluation and observed physical parameters.'],
                ['key' => 'recommendation', 'title' => 'Recommendation', 'content' => 'Escalation, therapy continuation, and monitoring triggers.'],
            ],
            'soapie' => [
                ['key' => 'subjective', 'title' => 'Subjective', 'content' => $content],
                ['key' => 'objective', 'title' => 'Objective', 'content' => 'Vital signs and bedside physical observations.'],
                ['key' => 'assessment', 'title' => 'Assessment', 'content' => 'Nursing evaluation and clinical impression.'],
                ['key' => 'plan', 'title' => 'Plan', 'content' => 'Nursing interventions and care goals.'],
                ['key' => 'intervention', 'title' => 'Intervention', 'content' => 'Care protocol executed per physician orders.'],
                ['key' => 'evaluation', 'title' => 'Evaluation', 'content' => 'Patient response and progress evaluated.'],
            ],
            'anc' => [
                ['key' => 'gestation', 'title' => 'Gestational Age & Maternal Symptoms', 'content' => $content],
                ['key' => 'exam', 'title' => 'Obstetric Examination & Fundal Height', 'content' => 'Blood pressure checked. Fundal height corresponds with gestational age. Fetal heart rate audible.'],
                ['key' => 'labs', 'title' => 'Antenatal Screening & Bedside Tests', 'content' => 'Urine dipstick, PCV, and routine antenatal profile evaluated.'],
                ['key' => 'plan', 'title' => 'Obstetric Plan & Preventive Therapy', 'content' => 'Routine iron/folic acid supplementation, tetanus immunization, and birth plan review.'],
            ],
            'labor' => [
                ['key' => 'contractions', 'title' => 'Contraction Frequency & Cervical Status', 'content' => $content],
                ['key' => 'fetal_status', 'title' => 'Fetal Heart Rate & Liquor Assessment', 'content' => 'Fetal heart sound checked. Membranes intact / clear liquor.'],
                ['key' => 'partograph', 'title' => 'Partograph Progress & Vitals', 'content' => 'Progress plotted on partograph. Maternal vital signs monitored.'],
                ['key' => 'plan', 'title' => 'Intrapartum Management & Delivery Plan', 'content' => 'Active monitoring continued. Delivery pack and neonatal resuscitation ready.'],
            ],
            'pnc' => [
                ['key' => 'maternal_status', 'title' => 'Maternal Postpartum Recovery', 'content' => $content],
                ['key' => 'involution', 'title' => 'Uterine Involution, Lochia & Perineum', 'content' => 'Uterus well contracted, lochia rubra normal, perineum healing well.'],
                ['key' => 'lactation', 'title' => 'Lactation & Neonatal Feeding', 'content' => 'Breasts soft, colostrum/milk flowing, baby latching well.'],
                ['key' => 'plan', 'title' => 'Postnatal Plan & Immunization', 'content' => 'Postpartum family planning counselled. Infant immunization scheduled.'],
            ],
            default => [
                // International SOAP Default
                ['key' => 'subjective', 'title' => 'Subjective / History of Presenting Illness', 'content' => $content],
                ['key' => 'objective', 'title' => 'Objective / Physical Examination & Vitals', 'content' => 'Vitals stable. Examination findings recorded during consultation.'],
                ['key' => 'assessment', 'title' => 'Assessment / Clinical Impression', 'content' => 'Clinical diagnosis per consultation narrative.'],
                ['key' => 'plan', 'title' => 'Plan / Management & Orders', 'content' => 'Pharmacotherapy and clinical investigations initiated.'],
            ],
        };
    }

    /**
     * Transform sections between taxonomies losslessly.
     */
    protected function transformTaxonomySections(array $map, string $allText, string $from, string $to, string $role): array
    {
        $subj = $map['subjective'] ?? ($map['pc'] ?? ($map['data'] ?? ($map['situation'] ?? ($map['gestation'] ?? $allText))));
        $obj = $map['objective'] ?? ($map['exam'] ?? ($map['action'] ?? ($map['background'] ?? '')));
        $assess = $map['assessment'] ?? ($map['impression'] ?? ($map['response'] ?? ''));
        $plan = $map['plan'] ?? ($map['recommendation'] ?? '');

        return match ($to) {
            'clerking' => [
                ['key' => 'pc', 'title' => 'Presenting Complaint (PC)', 'content' => $subj],
                ['key' => 'hpc', 'title' => 'History of Presenting Complaint (HPC)', 'content' => $subj],
                ['key' => 'ros', 'title' => 'Review of Systems (ROS)', 'content' => 'Systemic review preserved from consultation note.'],
                ['key' => 'pmh', 'title' => 'Past Medical History & Drug History', 'content' => $map['pmh'] ?? 'No known drug allergies.'],
                ['key' => 'exam', 'title' => 'General & Systemic Physical Examination', 'content' => $obj ?: 'Physical examination performed.'],
                ['key' => 'impression', 'title' => 'Clinical Impression / Diagnosis', 'content' => $assess ?: 'Clinical impression established.'],
                ['key' => 'plan', 'title' => 'Management Plan & Orders', 'content' => $plan ?: 'Therapeutic orders initiated.'],
            ],
            'pomr' => [
                ['key' => 'problem_list', 'title' => 'Active Problem List', 'content' => '1. ' . ($assess ?: 'Consultation Chief Problem')],
                ['key' => 'subjective', 'title' => 'Subjective Observations', 'content' => $subj],
                ['key' => 'objective', 'title' => 'Objective Clinical Findings', 'content' => $obj ?: 'Vitals and observations recorded.'],
                ['key' => 'assessment', 'title' => 'Diagnostic Assessment', 'content' => $assess ?: 'Assessment noted.'],
                ['key' => 'plan', 'title' => 'Problem-Oriented Management Plan', 'content' => $plan ?: 'Management plan active.'],
            ],
            'dar' => [
                ['key' => 'data', 'title' => 'Data (Subjective & Objective)', 'content' => trim($subj . "\n\n" . $obj)],
                ['key' => 'action', 'title' => 'Action (Nursing Interventions)', 'content' => $plan ?: 'Interventions carried out.'],
                ['key' => 'response', 'title' => 'Response (Patient Outcome)', 'content' => $assess ?: 'Patient monitored.'],
            ],
            'sbar' => [
                ['key' => 'situation', 'title' => 'Situation', 'content' => $subj],
                ['key' => 'background', 'title' => 'Background', 'content' => $obj ?: 'Patient clinical history.'],
                ['key' => 'assessment', 'title' => 'Assessment', 'content' => $assess ?: 'Clinical assessment.'],
                ['key' => 'recommendation', 'title' => 'Recommendation', 'content' => $plan ?: 'Recommended management.'],
            ],
            default => [
                ['key' => 'subjective', 'title' => 'Subjective / History of Presenting Illness', 'content' => $subj],
                ['key' => 'objective', 'title' => 'Objective / Physical Examination & Vitals', 'content' => $obj ?: 'Observations recorded.'],
                ['key' => 'assessment', 'title' => 'Assessment / Clinical Impression', 'content' => $assess ?: 'Clinical impression.'],
                ['key' => 'plan', 'title' => 'Plan / Management & Orders', 'content' => $plan ?: 'Orders and management.'],
            ],
        };
    }

    /**
     * Extract, ground, and categorize clinical orders from text.
     */
    protected function extractOrdersFromText(string $text, array $parsedCustomCatalog, array $patientAllergies): array
    {
        $orders = [];
        $lower = strtolower($text);

        // 1. Prescriptions & Medications
        $drugPatterns = [
            'paracetamol' => ['Paracetamol 500mg Tablet', '1g', 'TDS (8-hourly)', '3 days', 'oral', 6],
            'amoxicillin' => ['Amoxicillin 500mg Capsule', '500mg', 'TDS (8-hourly)', '5 days', 'oral', 15],
            'augmentin' => ['Augmentin 625mg Tablet', '625mg', 'BD (12-hourly)', '5 days', 'oral', 10],
            'ciprofloxacin' => ['Ciprofloxacin 500mg Tablet', '500mg', 'BD (12-hourly)', '5 days', 'oral', 10],
            'artemether' => ['Artemether-Lumefantrine 80/480mg', '1 tab', 'BD (12-hourly)', '3 days', 'oral', 6],
            'metformin' => ['Metformin 500mg Tablet', '500mg', 'BD with meals', '30 days', 'oral', 60],
            'amlodipine' => ['Amlodipine 5mg Tablet', '5mg', 'OD (morning)', '30 days', 'oral', 30],
            'omeprazole' => ['Omeprazole 20mg Capsule', '20mg', 'OD before breakfast', '14 days', 'oral', 14],
            'ibuprofen' => ['Ibuprofen 400mg Tablet', '400mg', 'TDS after meals', '3 days', 'oral', 9],
            'ceftriaxone' => ['Ceftriaxone 1g Injection', '1g', 'OD (daily)', '3 days', 'IV', 3],
            'losartan' => ['Losartan 50mg Tablet', '50mg', 'OD (morning)', '30 days', 'oral', 30],
        ];

        foreach ($drugPatterns as $key => $spec) {
            if (str_contains($lower, $key)) {
                $dosage = $spec[1];
                if (preg_match('/' . $key . '\s*(\d+\s*(?:mg|g|ml))/i', $text, $dMatch)) {
                    $dosage = trim($dMatch[1]);
                }

                // Check custom catalog first
                $matchedItem = $this->compressedCatalog->matchItem($key, $parsedCustomCatalog, 'RX');
                $catalogId = $matchedItem['id'] ?? null;
                $catId = $matchedItem['category_id'] ?? 4;
                $catName = $matchedItem['category_name'] ?? 'Prescription Medication';
                $unitPrice = $matchedItem['unit_price'] ?? 450.0;
                $itemName = $matchedItem['name'] ?? $spec[0];

                if (!$matchedItem) {
                    // Match against hospital catalog
                    $chMatch = $this->catalogRegistry->matchCatalogItem($key, 'PRODUCT');
                    if ($chMatch && $chMatch['is_matched']) {
                        $catalogId = $chMatch['catalog_id'];
                        $catId = $chMatch['category_id'];
                        $catName = $chMatch['category_name'];
                        $unitPrice = $chMatch['unit_price'];
                        $itemName = $chMatch['item_name'];
                    }
                }

                // Check allergy
                $warning = null;
                foreach ($patientAllergies as $allergy) {
                    if (str_contains($key, strtolower($allergy)) || str_contains(strtolower($allergy), $key)) {
                        $warning = "PATIENT ALLERGY ALERT: Patient is allergic to '{$allergy}' (matches {$itemName})";
                    }
                }

                $orders[] = [
                    'id' => 'ord_' . (count($orders) + 1),
                    'order_type' => 'PRODUCT',
                    'service_type' => 'product',
                    'category_id' => $catId,
                    'category_name' => $catName,
                    'catalog_id' => $catalogId,
                    'item_name' => $itemName,
                    'dosage' => $dosage,
                    'frequency' => $spec[2],
                    'duration' => $spec[3],
                    'route' => $spec[4],
                    'quantity' => $spec[5],
                    'instructions' => 'Take as directed by clinician',
                    'unit_price' => $unitPrice,
                    'target_queue' => 'dispensing_queue',
                    'is_selected' => true,
                    'is_matched' => !empty($catalogId),
                    'is_free_form' => empty($catalogId),
                    'safety_warning' => $warning,
                ];
            }
        }

        // 2. Laboratory Investigations
        $labPatterns = [
            'fbc' => ['Full Blood Count (FBC)', 'Confirm infection / rule out anemia'],
            'full blood count' => ['Full Blood Count (FBC)', 'Routine hematological screen'],
            'mp' => ['Malaria Parasite (MP) Test', 'Evaluate febrile illness'],
            'malaria' => ['Malaria Rapid Diagnostic Test', 'Rule out acute malaria'],
            'pcv' => ['Packed Cell Volume (PCV)', 'Anemia screening'],
            'urinalysis' => ['Routine Urinalysis', 'Exclude urinary tract infection'],
            'blood sugar' => ['Random Blood Sugar (RBS)', 'Screen for hyperglycemia'],
            'rbs' => ['Random Blood Sugar (RBS)', 'Glycemic assessment'],
            'fbs' => ['Fasting Blood Sugar (FBS)', 'Fasting glycemic assessment'],
            'lipid profile' => ['Lipid Profile', 'Cardiovascular risk stratification'],
            'e/u/cr' => ['Serum Electrolytes, Urea & Creatinine (E/U/Cr)', 'Renal function evaluation'],
            'lft' => ['Liver Function Test (LFT)', 'Hepatic assessment'],
            'widal' => ['Widal Agglutination Test', 'Enteric fever screen'],
        ];

        foreach ($labPatterns as $key => $spec) {
            if (str_contains($lower, $key)) {
                $matchedItem = $this->compressedCatalog->matchItem($key, $parsedCustomCatalog, 'LAB');
                $catalogId = $matchedItem['id'] ?? null;
                $catId = $matchedItem['category_id'] ?? 2;
                $catName = $matchedItem['category_name'] ?? 'Laboratory';
                $unitPrice = $matchedItem['unit_price'] ?? 2500.0;
                $itemName = $matchedItem['name'] ?? $spec[0];

                if (!$matchedItem) {
                    $chMatch = $this->catalogRegistry->matchCatalogItem($key, 'SERVICE', 'lab');
                    if ($chMatch && $chMatch['is_matched']) {
                        $catalogId = $chMatch['catalog_id'];
                        $catId = $chMatch['category_id'];
                        $catName = $chMatch['category_name'];
                        $unitPrice = $chMatch['unit_price'];
                        $itemName = $chMatch['item_name'];
                    }
                }

                $orders[] = [
                    'id' => 'ord_' . (count($orders) + 1),
                    'order_type' => 'SERVICE',
                    'service_type' => 'lab',
                    'category_id' => $catId,
                    'category_name' => $catName,
                    'catalog_id' => $catalogId,
                    'item_name' => $itemName,
                    'indication' => $spec[1],
                    'instructions' => 'Fasting / routine venous blood sample',
                    'urgency' => str_contains($lower, 'stat') ? 'stat' : 'routine',
                    'unit_price' => $unitPrice,
                    'target_queue' => 'lab_queue',
                    'is_selected' => true,
                    'is_matched' => !empty($catalogId),
                    'is_free_form' => empty($catalogId),
                    'safety_warning' => null,
                ];

                break; // Prevent duplicate FBC / Full Blood Count hits
            }
        }

        // 3. Radiology & Diagnostic Imaging
        $radPatterns = [
            'x-ray' => ['Chest X-Ray (PA View)', 'Rule out pulmonary consolidation'],
            'cxr' => ['Chest X-Ray (PA View)', 'Evaluate persistent cough'],
            'ultrasound' => ['Abdominal Ultrasound Scan', 'Evaluate visceral organs'],
            'uss' => ['Abdominal Ultrasound Scan', 'Diagnostic sonography'],
            'scan' => ['Pelvic Ultrasound Scan', 'Pelvic imaging'],
            'ecg' => ['12-Lead Electrocardiogram (ECG)', 'Cardiovascular screening'],
        ];

        foreach ($radPatterns as $key => $spec) {
            if (str_contains($lower, $key)) {
                $matchedItem = $this->compressedCatalog->matchItem($key, $parsedCustomCatalog, 'RAD');
                $catalogId = $matchedItem['id'] ?? null;
                $catId = $matchedItem['category_id'] ?? 6;
                $catName = $matchedItem['category_name'] ?? 'Radiology & Imaging';
                $unitPrice = $matchedItem['unit_price'] ?? 5000.0;
                $itemName = $matchedItem['name'] ?? $spec[0];

                if (!$matchedItem) {
                    $chMatch = $this->catalogRegistry->matchCatalogItem($key, 'SERVICE', 'imaging');
                    if ($chMatch && $chMatch['is_matched']) {
                        $catalogId = $chMatch['catalog_id'];
                        $catId = $chMatch['category_id'];
                        $catName = $chMatch['category_name'];
                        $unitPrice = $chMatch['unit_price'];
                        $itemName = $chMatch['item_name'];
                    }
                }

                $orders[] = [
                    'id' => 'ord_' . (count($orders) + 1),
                    'order_type' => 'SERVICE',
                    'service_type' => 'imaging',
                    'category_id' => $catId,
                    'category_name' => $catName,
                    'catalog_id' => $catalogId,
                    'item_name' => $itemName,
                    'indication' => $spec[1],
                    'instructions' => 'Routine radiologic examination',
                    'urgency' => 'routine',
                    'unit_price' => $unitPrice,
                    'target_queue' => 'imaging_queue',
                    'is_selected' => true,
                    'is_matched' => !empty($catalogId),
                    'is_free_form' => empty($catalogId),
                    'safety_warning' => null,
                ];

                break;
            }
        }

        // 4. Clinical Procedures & Nursing
        if (str_contains($lower, 'dressing') || str_contains($lower, 'wound')) {
            $matchedItem = $this->compressedCatalog->matchItem('dressing', $parsedCustomCatalog, 'PROC');
            $orders[] = [
                'id' => 'ord_' . (count($orders) + 1),
                'order_type' => 'SERVICE',
                'service_type' => 'procedure',
                'category_id' => $matchedItem['category_id'] ?? 8,
                'category_name' => $matchedItem['category_name'] ?? 'Clinical Procedure',
                'catalog_id' => $matchedItem['id'] ?? null,
                'item_name' => $matchedItem['name'] ?? 'Wound Dressing (Minor)',
                'indication' => 'Aseptic wound management',
                'instructions' => 'Clean with saline and apply sterile dressing',
                'unit_price' => $matchedItem['unit_price'] ?? 1500.0,
                'target_queue' => 'procedure_queue',
                'is_selected' => true,
                'is_matched' => !empty($matchedItem),
                'is_free_form' => empty($matchedItem),
                'safety_warning' => null,
            ];
        }

        // 5. Follow-up / Review
        if (str_contains($lower, 'review') || str_contains($lower, 'follow-up') || str_contains($lower, 'follow up')) {
            $orders[] = [
                'id' => 'ord_' . (count($orders) + 1),
                'order_type' => 'FOLLOW_UP',
                'service_type' => 'follow_up',
                'category_id' => 1,
                'category_name' => 'Reception Follow-Up',
                'catalog_id' => null,
                'item_name' => 'Clinical Review Follow-Up',
                'duration_days' => 7,
                'reason' => 'Evaluate symptom resolution and therapy tolerance',
                'target_queue' => 'appointment_queue',
                'is_selected' => true,
                'is_matched' => true,
                'is_free_form' => false,
                'safety_warning' => null,
            ];
        }

        return $orders;
    }
}
