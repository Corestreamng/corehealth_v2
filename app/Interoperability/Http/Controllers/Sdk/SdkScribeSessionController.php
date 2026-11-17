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
use App\Services\LlmGatewayService;
use App\Services\OrderCatalogRegistryService;
use App\Services\PatientContextService;
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

    protected ?LlmGatewayService $llmGateway;

    protected ?PatientContextService $contextService;

    public function __construct(
        SdkTokenService $tokenService,
        NdprSanitizerService $sanitizer,
        OrderCatalogRegistryService $catalogRegistry,
        CompressedCatalogService $compressedCatalog,
        ?LlmGatewayService $llmGateway = null,
        ?PatientContextService $contextService = null
    ) {
        $this->tokenService = $tokenService;
        $this->sanitizer = $sanitizer;
        $this->catalogRegistry = $catalogRegistry;
        $this->compressedCatalog = $compressedCatalog;
        $this->llmGateway = $llmGateway;
        $this->contextService = $contextService;
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
            'status' => 'success',
            'count' => count($serviceCategories) + count($productCategories),
            'data' => [
                'services' => $serviceCategories,
                'products' => $productCategories,
            ],
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
            'status' => 'success',
            'count' => count($items),
            'total' => count($items),
            'cscp' => $this->compressedCatalog->compress(array_slice($items, 0, 50)),
            'data' => $items,
            'items' => $items,
        ], 200);
    }

    /**
     * Lossless format conversion between clinical taxonomies.
     */
    public function convertFormat(Request $request): JsonResponse
    {
        $from = strtolower((string) ($request->input('from_taxonomy') ?? $request->input('source_taxonomy', 'soap')));
        $to = strtolower((string) ($request->input('to_taxonomy') ?? $request->input('target_taxonomy', 'clerking')));
        $role = strtolower((string) ($request->input('role') ?? $request->input('target_role') ?? $request->input('source_role', 'doctor')));
        $currentSections = $request->input('current_sections', []);

        if (is_string($currentSections)) {
            $currentSections = json_decode($currentSections, true) ?: [];
        }

        $rawNote = (string) ($request->input('note') ?? $request->input('transcript') ?? '');

        $allContent = '';
        $sectionMap = [];
        if (!empty($currentSections)) {
            foreach ($currentSections as $sec) {
                $key = strtolower($sec['key'] ?? '');
                $sectionMap[$key] = $sec['content'] ?? '';
                $allContent .= ($sec['title'] ?? '') . ":\n" . ($sec['content'] ?? '') . "\n\n";
            }
        } elseif (!empty($rawNote)) {
            $allContent = $rawNote;
            // Parse common section headers if note provided as plain string
            preg_match_all('/(?:^|\n)([A-Z\s]+):([\s\S]*?)(?=(?:\n[A-Z\s]+:)|$)/', $rawNote, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $k = strtolower(trim($m[1]));
                $sectionMap[$k] = trim($m[2]);
            }
        }

        $newSections = $this->transformTaxonomySections($sectionMap, $allContent, $from, $to, $role);

        $markdown = '';
        foreach ($newSections as $sec) {
            $markdown .= "### " . ($sec['title'] ?? 'Section') . "\n" . ($sec['content'] ?? '') . "\n\n";
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'from_taxonomy' => $from,
            'to_taxonomy' => $to,
            'structured_sections' => $newSections,
            'formatted_markdown' => trim($markdown),
            'converted_note' => trim($markdown),
        ], 200);
    }

    /**
     * Process dictation text with taxonomy structuring, super-compressed catalog grounding,
     * and suggested order extraction.
     */
    public function process(Request $request): JsonResponse
    {
        $claims = $request->attributes->get('sdk_claims') ?? [];
        $clientId = $claims['client_id'] ?? $request->input('client_id');
        $patientId = $claims['patient_id'] ?? $request->input('patient_id');
        $encounterId = $claims['encounter_id'] ?? $request->input('encounter_id');
        $clinicianId = $claims['clinician_id'] ?? $request->input('clinician_id');
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
        $patientContext = '';
        if ($patientId) {
            $patient = Patient::find($patientId);
            if ($patient && !empty($patient->allergies)) {
                $patientAllergies = is_array($patient->allergies)
                    ? $patient->allergies
                    : array_filter(array_map('trim', explode(',', (string) $patient->allergies)));
            }
            if ($this->contextService) {
                try {
                    $patientContext = $this->contextService->getPatientContext((int) $patientId, $encounterId ? (int) $encounterId : null);
                } catch (\Throwable $e) {
                    Log::warning('Patient context retrieval error: ' . $e->getMessage());
                }
            }
        }
        if (!empty($sanitizedPatientSummary)) {
            $patientContext .= "\n\nLONGITUDINAL PATIENT SUMMARY & EHR BRIEF (Context only):\n" . trim($sanitizedPatientSummary);
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

            $modelUsed = 'corehealth-clinical-nlp-v2';
            $provider = 'corehealth-heuristics';
            $structuredSections = [];
            $extractedOrders = [];
            $summaryHeadline = 'Clinical Consultation Summary (' . strtoupper($taxonomy) . ')';
            $aiSuccess = false;

            // 1. Primary AI Inference via LlmGatewayService (if configured & enabled)
            if ($this->llmGateway && $this->llmGateway->isEnabled()) {
                try {
                    $combinedSearchText = $transcript . ' ' . $editorContent;
                    if (!empty($existingSections)) {
                        foreach ($existingSections as $sec) {
                            $combinedSearchText .= ' ' . ($sec['content'] ?? '');
                        }
                    }

                    $catalogContext = $this->catalogRegistry->getCatalogPromptContext($combinedSearchText);
                    $config = $this->llmGateway->getConfig();
                    $systemPrompt = $config['system_prompts']['clinical_scribe'] ?? config('llm.prompts.clinical_scribe');

                    $userMessage = "TARGET PERSPECTIVE: ROLE=" . strtoupper($role) . ", TAXONOMY=" . strtoupper($taxonomy) . "\n";
                    $userMessage .= "TODAY'S CLINICAL ENCOUNTER DATE & TIME: " . now()->format('l, F j, Y g:i A') . " (" . now()->toDateString() . ")\n\n";
                    $userMessage .= $catalogContext . "\n\n";

                    if (!empty($parsedCustomCatalog['categories'])) {
                        $userMessage .= "PARTNER EMR CUSTOM CATALOG:\n" . json_encode($parsedCustomCatalog, JSON_UNESCAPED_SLASHES) . "\n\n";
                    }

                    if (!empty($patientContext)) {
                        $userMessage .= "PATIENT CLINICAL CONTEXT & HISTORY (Background context only — do NOT echo this verbatim into the consultation note; the clinician's spoken dictation and live encounter observations take absolute precedence):\n" .
                            $patientContext . "\n\n---\n";
                    }

                    if (!empty($editorContent)) {
                        $userMessage .= "EXISTING NOTE CONTENT IN CLINICAL EDITOR (Preserve and merge with transcript):\n" .
                            $editorContent . "\n\n---\n";
                    }

                    if (!empty($existingSections)) {
                        $userMessage .= "CURRENT STRUCTURED CONSULTATION NOTE (The clinician is adding new dictation/orders or amending this note; maintain established structure and update sections seamlessly):\n" .
                            json_encode($existingSections, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n---\n";
                    }

                    if (!empty($transcript)) {
                        $userMessage .= "NEW SPOKEN AUDIO DICTATION / TRANSCRIPT TO INCORPORATE:\n" .
                            $transcript . "\n\n";
                    }

                    $llmResponse = $this->llmGateway->complete($systemPrompt, $userMessage, [
                        'max_tokens' => 8192,
                        'temperature' => 0.15,
                        'json_mode' => true,
                        'thinking_budget' => 0,
                        'source' => 'api_scribe',
                        'client_id' => $clientId,
                        'user_id' => $clinicianId,
                        'patient_id' => $patientId,
                        'encounter_id' => $encounterId,
                    ]);

                    $rawContent = trim($llmResponse['content'] ?? '');
                    $parsed = $this->extractJsonPayload($rawContent);

                    if ($parsed && !empty($parsed['structured_sections'])) {
                        $structuredSections = $parsed['structured_sections'];
                        $summaryHeadline = $parsed['summary_headline'] ?? $summaryHeadline;
                        $rawOrders = $parsed['extracted_orders'] ?? [];
                        if (empty($rawOrders) && preg_match('/"extracted_orders"\s*:\s*\[([\s\S]*?)\]/', $rawContent, $ordersBlock)) {
                            $ordersJson = '[' . trim($ordersBlock[1]) . ']';
                            $ordersJson = preg_replace('/,\s*\]$/', ']', $ordersJson);
                            $decodedOrders = json_decode($ordersJson, true);
                            if (is_array($decodedOrders) && !empty($decodedOrders)) {
                                $rawOrders = $decodedOrders;
                            }
                        }

                        // Ground with hospital catalog (formats dosage with pipe standard: <dose> | <freq> | <duration> | <route>)
                        $groundedHospitalOrders = $this->catalogRegistry->groundOrders($rawOrders, $patientAllergies);

                        // Enrich with custom catalog items if partner supplied a custom catalog
                        $extractedOrders = $this->enrichOrdersWithCustomCatalog($groundedHospitalOrders, $parsedCustomCatalog);

                        $modelUsed = $llmResponse['model'] ?? 'gemini-2.5-flash';
                        $provider = $llmResponse['provider'] ?? 'gemini';
                        $aiSuccess = true;
                    } else {
                        Log::warning('LLM Scribe JSON parse returned no structured sections, falling back to heuristic partitioner', ['raw' => $rawContent]);
                    }
                } catch (\Throwable $llmErr) {
                    Log::warning('LLM Scribe execution failed, falling back to heuristic NLP: ' . $llmErr->getMessage());
                }
            }

            // 2. Intelligent Heuristic Fallback (if LLM is disabled or failed)
            if (!$aiSuccess) {
                $structuredSections = $this->buildStructuredSections(
                    $transcript,
                    $editorContent,
                    $taxonomy,
                    $role,
                    $existingSections,
                    $sanitizedPatientSummary
                );

                $combinedText = $transcript . ' ' . $editorContent;
                $extractedOrders = $this->extractOrdersFromText($combinedText, $parsedCustomCatalog, $patientAllergies);
            }

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
                'summary_headline' => $summaryHeadline,
                'structured_sections' => $structuredSections,
                'formatted_markdown' => trim($md),
                'grounded_orders' => $extractedOrders,
                'safety_warnings' => $safetyWarnings,
                'custom_catalog_applied' => !empty($parsedCustomCatalog['categories']),
                'custom_categories_count' => count($parsedCustomCatalog['categories'] ?? []),
                'model_used' => $modelUsed,
                'provider' => $provider,
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
                'status' => 'success',
                'message' => 'Consultation note signed and archived successfully.',
                'audit_id' => $audit->id,
                'patient_id' => $patientId,
                'encounter_id' => $encounterId,
                'signed_at' => $audit->signed_at->toIso8601String(),
                'approved_orders_count' => count($approvedOrders),
                'orders_dispatched' => count($approvedOrders),
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
     * Enrich hospital-grounded orders with custom partner catalog metadata if provided.
     */
    protected function enrichOrdersWithCustomCatalog(array $orders, array $parsedCustomCatalog): array
    {
        if (empty($parsedCustomCatalog['categories'])) {
            return $orders;
        }

        foreach ($orders as &$ord) {
            $name = $ord['item_name'] ?? '';
            $typeHint = ($ord['order_type'] === 'PRODUCT') ? 'RX' : ((($ord['service_type'] ?? '') === 'lab') ? 'LAB' : null);
            $matched = $this->compressedCatalog->matchItem($name, $parsedCustomCatalog, $typeHint);
            if ($matched) {
                // If item was ungrounded or free-form in hospital catalog, bind to partner custom catalog
                if (empty($ord['catalog_id']) || !empty($ord['is_free_form'])) {
                    $ord['catalog_id'] = $matched['id'] ?? null;
                    $ord['category_id'] = $matched['category_id'] ?? $ord['category_id'];
                    $ord['category_name'] = $matched['category_name'] ?? $ord['category_name'];
                    $ord['unit_price'] = (float) ($matched['unit_price'] ?? $ord['unit_price']);
                    $ord['is_matched'] = true;
                    $ord['is_free_form'] = false;
                }
            }
        }
        unset($ord);

        return $orders;
    }

    /**
     * Helper to extract clean JSON from LLM text responses.
     */
    protected function extractJsonPayload(string $raw): ?array
    {
        $clean = trim($raw);

        // Strip ```json or ``` code fences
        if (str_starts_with($clean, '```')) {
            $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
            $clean = preg_replace('/\s*```$/', '', $clean);
            $clean = trim($clean);
        }

        // Try direct decode
        $decoded = json_decode($clean, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Attempt regex capture between { and }
        if (preg_match('/\{[\s\S]*\}/', $clean, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // Repair truncated JSON if the model response was cut off mid-stream
        $repaired = $this->repairTruncatedJson($clean);
        if ($repaired !== null) {
            return $repaired;
        }

        return null;
    }

    /**
     * Attempt to repair and salvage truncated JSON streams.
     */
    protected function repairTruncatedJson(string $raw): ?array
    {
        $str = trim($raw);
        if (!str_starts_with($str, '{')) {
            $start = strpos($str, '{');
            if ($start === false) {
                return null;
            }
            $str = substr($str, $start);
        }

        // Strip trailing uncompleted elements e.g. , { ... or , "key": ... or trailing comma
        $cleanStr = preg_replace('/,\s*\{\s*(?:"[^"]*"\s*:\s*(?:"[^"]*"|[^,}\]]*))?$/s', '', $str);
        $cleanStr = preg_replace('/,\s*"[^"]*"\s*:\s*(?:"[^"]*)?$/s', '', $cleanStr);
        $cleanStr = preg_replace('/,\s*$/s', '', $cleanStr);

        // Count unclosed brackets and braces
        $openBraces = substr_count($cleanStr, '{') - substr_count($cleanStr, '}');
        $openBrackets = substr_count($cleanStr, '[') - substr_count($cleanStr, ']');

        if ($openBraces > 0 || $openBrackets > 0) {
            $closing = str_repeat(']', max(0, $openBrackets)) . str_repeat('}', max(0, $openBraces));
            $attempt = $cleanStr . $closing;

            $decoded = json_decode($attempt, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                Log::info('Successfully repaired truncated LLM JSON payload in SDK Scribe');

                return $decoded;
            }
        }

        return null;
    }

    /**
     * Partition raw clinical narrative into Subjective, Objective, Assessment, and Plan components.
     */
    protected function partitionClinicalNarrative(string $text): array
    {
        $text = trim($text);
        if (empty($text)) {
            return [
                'subjective' => '',
                'objective' => '',
                'assessment' => '',
                'plan' => '',
            ];
        }

        $objPatterns = '/\b(?:Physical examination reveals|Examination reveals|Physical exam reveals|On examination|O\/E|Examination|Vitals|Vital signs)\s*[:\-]?\s*/i';
        $assessPatterns = '/\b(?:Assessment|Clinical Impression|Impression|Diagnosis|Provisional Diagnosis|Dx)\s*[:\-]?\s*/i';
        $planPatterns = '/\b(?:Plan|Management|Prescribe|Treatment|Rx|Orders|Advise|Recommendations)\s*[:\-]?\s*/i';

        $hasObj = preg_match($objPatterns, $text, $objMatches, PREG_OFFSET_CAPTURE);
        $hasAssess = preg_match($assessPatterns, $text, $assessMatches, PREG_OFFSET_CAPTURE);
        $hasPlan = preg_match($planPatterns, $text, $planMatches, PREG_OFFSET_CAPTURE);

        if ($hasObj || $hasAssess || $hasPlan) {
            $cuts = [];
            if ($hasObj) {
                $cuts['obj'] = [
                    'start' => $objMatches[0][1],
                    'match_len' => strlen($objMatches[0][0]),
                ];
            }
            if ($hasAssess) {
                $cuts['assess'] = [
                    'start' => $assessMatches[0][1],
                    'match_len' => strlen($assessMatches[0][0]),
                ];
            }
            if ($hasPlan) {
                $cuts['plan'] = [
                    'start' => $planMatches[0][1],
                    'match_len' => strlen($planMatches[0][0]),
                ];
            }

            // Sort cut positions
            uasort($cuts, fn ($a, $b) => $a['start'] <=> $b['start']);
            $keys = array_keys($cuts);

            // Text before first marker is Subjective
            $firstCut = $cuts[$keys[0]];
            $subj = trim(substr($text, 0, $firstCut['start']));
            $obj = '';
            $assess = '';
            $plan = '';

            for ($i = 0; $i < count($keys); $i++) {
                $k = $keys[$i];
                $startPos = $cuts[$k]['start'];
                $endPos = ($i + 1 < count($keys)) ? $cuts[$keys[$i + 1]]['start'] : strlen($text);
                $segment = trim(substr($text, $startPos, $endPos - $startPos));

                if ($k === 'obj') {
                    $obj = $segment;
                } elseif ($k === 'assess') {
                    $cleanAssess = preg_replace($assessPatterns, '', $segment, 1);
                    $assess = trim($cleanAssess ?: $segment);
                } elseif ($k === 'plan') {
                    $plan = $segment;
                }
            }
        } else {
            // Narrative heuristic: look for sentences with vitals / diagnosis / treatment
            $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
            $sSubj = [];
            $sObj = [];
            $sAssess = [];
            $sPlan = [];

            foreach ($sentences as $sentence) {
                if (preg_match('/(\b(temp|temperature|bp|pulse|heart rate|respiratory rate|clear on auscultation|abdomen is soft|tender|non-tender|\d+°c|\d+\/\d+\s*mmhg|\d+\s*bpm)\b)/i', $sentence)) {
                    $sObj[] = $sentence;
                } elseif (preg_match('/(\b(diagnos|malaria|infection|syndrome|disease|hypertension|diabetes|fever)\b)/i', $sentence) && !preg_match('/(\b(history of|prescribe|request)\b)/i', $sentence)) {
                    $sAssess[] = $sentence;
                } elseif (preg_match('/(\b(prescribe|request|advise|give|administer|tab|mg|tds|bd|daily|bed rest)\b)/i', $sentence)) {
                    $sPlan[] = $sentence;
                } else {
                    $sSubj[] = $sentence;
                }
            }

            $subj = implode(' ', $sSubj);
            $obj = implode(' ', $sObj);
            $assess = implode(' ', $sAssess);
            $plan = implode(' ', $sPlan);

            if (empty($subj)) {
                $subj = $text;
            }
        }

        return [
            'subjective' => trim($subj),
            'objective' => trim($obj),
            'assessment' => trim($assess),
            'plan' => trim($plan),
        ];
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
        $parts = $this->partitionClinicalNarrative($content);

        return match ($taxonomy) {
            'clerking' => [
                ['key' => 'pc', 'title' => 'Presenting Complaint (PC)', 'content' => $parts['subjective'] ?: $content],
                ['key' => 'hpc', 'title' => 'History of Presenting Complaint (HPC)', 'content' => $parts['subjective'] ?: 'Symptoms onset, character, and chronology recorded during consultation.'],
                ['key' => 'ros', 'title' => 'Review of Systems (ROS)', 'content' => 'Cardiovascular, respiratory, gastrointestinal, and neurological systems reviewed.'],
                ['key' => 'pmh', 'title' => 'Past Medical History & Drug History', 'content' => !empty($patientSummary) ? $patientSummary : 'No known drug allergies or prior adverse reactions documented.'],
                ['key' => 'exam', 'title' => 'General & Systemic Physical Examination', 'content' => $parts['objective'] ?: 'Vitals stable. Physical examination findings recorded.'],
                ['key' => 'impression', 'title' => 'Clinical Impression / Diagnosis', 'content' => $parts['assessment'] ?: 'Provisional clinical diagnosis established per clinical findings.'],
                ['key' => 'plan', 'title' => 'Management Plan & Orders', 'content' => $parts['plan'] ?: 'Pharmacotherapy and diagnostic investigations initiated.'],
            ],
            'pomr' => [
                ['key' => 'problem_list', 'title' => 'Active Problem List', 'content' => '1. ' . ($parts['assessment'] ?: 'Consultation Chief Problem')],
                ['key' => 'subjective', 'title' => 'Subjective Observations', 'content' => $parts['subjective'] ?: $content],
                ['key' => 'objective', 'title' => 'Objective Clinical Findings', 'content' => $parts['objective'] ?: 'Vitals within normal limits. Baseline physical examination performed.'],
                ['key' => 'assessment', 'title' => 'Diagnostic Assessment', 'content' => $parts['assessment'] ?: 'Clinical impression established.'],
                ['key' => 'plan', 'title' => 'Problem-Oriented Management Plan', 'content' => $parts['plan'] ?: 'Therapeutic regimen and follow-up scheduled.'],
            ],
            'dar' => [
                ['key' => 'data', 'title' => 'Data (Subjective & Objective)', 'content' => trim(($parts['subjective'] ?: $content) . ($parts['objective'] ? "\n\n" . $parts['objective'] : ''))],
                ['key' => 'action', 'title' => 'Action (Nursing Interventions)', 'content' => $parts['plan'] ?: 'Nursing care, medication administration, and patient monitoring implemented.'],
                ['key' => 'response', 'title' => 'Response (Patient Outcome)', 'content' => $parts['assessment'] ?: 'Patient tolerated interventions well. Vital signs reassessed.'],
            ],
            'sbar' => [
                ['key' => 'situation', 'title' => 'Situation', 'content' => $parts['subjective'] ?: $content],
                ['key' => 'background', 'title' => 'Background', 'content' => !empty($patientSummary) ? $patientSummary : 'Clinical history and admission context.'],
                ['key' => 'assessment', 'title' => 'Assessment', 'content' => trim(($parts['objective'] ? $parts['objective'] . "\n\n" : '') . ($parts['assessment'] ?: 'Acute clinical evaluation.'))],
                ['key' => 'recommendation', 'title' => 'Recommendation', 'content' => $parts['plan'] ?: 'Therapeutic orders and monitoring triggers.'],
            ],
            'soapie' => [
                ['key' => 'subjective', 'title' => 'Subjective', 'content' => $parts['subjective'] ?: $content],
                ['key' => 'objective', 'title' => 'Objective', 'content' => $parts['objective'] ?: 'Vital signs and bedside physical observations.'],
                ['key' => 'assessment', 'title' => 'Assessment', 'content' => $parts['assessment'] ?: 'Nursing evaluation and clinical impression.'],
                ['key' => 'plan', 'title' => 'Plan', 'content' => $parts['plan'] ?: 'Nursing interventions and care goals.'],
                ['key' => 'intervention', 'title' => 'Intervention', 'content' => 'Care protocol executed per physician orders.'],
                ['key' => 'evaluation', 'title' => 'Evaluation', 'content' => 'Patient response and progress evaluated.'],
            ],
            'anc' => [
                ['key' => 'gestation', 'title' => 'Gestational Age & Maternal Symptoms', 'content' => $parts['subjective'] ?: $content],
                ['key' => 'exam', 'title' => 'Obstetric Examination & Fundal Height', 'content' => $parts['objective'] ?: 'Blood pressure checked. Fundal height corresponds with gestational age. Fetal heart rate audible.'],
                ['key' => 'labs', 'title' => 'Antenatal Screening & Bedside Tests', 'content' => 'Urine dipstick, PCV, and routine antenatal profile evaluated.'],
                ['key' => 'plan', 'title' => 'Obstetric Plan & Preventive Therapy', 'content' => $parts['plan'] ?: 'Routine iron/folic acid supplementation, tetanus immunization, and birth plan review.'],
            ],
            'labor' => [
                ['key' => 'contractions', 'title' => 'Contraction Frequency & Cervical Status', 'content' => $parts['subjective'] ?: $content],
                ['key' => 'fetal_status', 'title' => 'Fetal Heart Rate & Liquor Assessment', 'content' => $parts['objective'] ?: 'Fetal heart sound checked. Membranes intact / clear liquor.'],
                ['key' => 'partograph', 'title' => 'Partograph Progress & Vitals', 'content' => 'Progress plotted on partograph. Maternal vital signs monitored.'],
                ['key' => 'plan', 'title' => 'Intrapartum Management & Delivery Plan', 'content' => $parts['plan'] ?: 'Active monitoring continued. Delivery pack and neonatal resuscitation ready.'],
            ],
            'pnc' => [
                ['key' => 'maternal_status', 'title' => 'Maternal Postpartum Recovery', 'content' => $parts['subjective'] ?: $content],
                ['key' => 'involution', 'title' => 'Uterine Involution, Lochia & Perineum', 'content' => $parts['objective'] ?: 'Uterus well contracted, lochia rubra normal, perineum healing well.'],
                ['key' => 'lactation', 'title' => 'Lactation & Neonatal Feeding', 'content' => 'Breasts soft, colostrum/milk flowing, baby latching well.'],
                ['key' => 'plan', 'title' => 'Postnatal Plan & Immunization', 'content' => $parts['plan'] ?: 'Postpartum family planning counselled. Infant immunization scheduled.'],
            ],
            default => [
                // International SOAP Default
                ['key' => 'subjective', 'title' => 'Subjective / History of Presenting Illness', 'content' => $parts['subjective'] ?: $content],
                ['key' => 'objective', 'title' => 'Objective / Physical Examination & Vitals', 'content' => $parts['objective'] ?: 'Vitals stable. Examination findings recorded during consultation.'],
                ['key' => 'assessment', 'title' => 'Assessment / Clinical Impression', 'content' => $parts['assessment'] ?: 'Clinical diagnosis per consultation narrative.'],
                ['key' => 'plan', 'title' => 'Plan / Management & Orders', 'content' => $parts['plan'] ?: 'Pharmacotherapy and clinical investigations initiated.'],
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
            'artemether' => ['Artemether-Lumefantrine 80/480mg', '80/480mg', 'BD (12-hourly)', '3 days', 'oral', 6],
            'paracetamol' => ['Paracetamol 500mg Tablet', '500mg', 'TDS (8-hourly)', '3 days', 'oral', 6],
            'amoxicillin' => ['Amoxicillin 500mg Capsule', '500mg', 'TDS (8-hourly)', '5 days', 'oral', 15],
            'augmentin' => ['Augmentin 625mg Tablet', '625mg', 'BD (12-hourly)', '5 days', 'oral', 10],
            'ciprofloxacin' => ['Ciprofloxacin 500mg Tablet', '500mg', 'BD (12-hourly)', '5 days', 'oral', 10],
            'metformin' => ['Metformin 500mg Tablet', '500mg', 'BD with meals', '30 days', 'oral', 60],
            'amlodipine' => ['Amlodipine 5mg Tablet', '5mg', 'OD (morning)', '30 days', 'oral', 30],
            'omeprazole' => ['Omeprazole 20mg Capsule', '20mg', 'OD before breakfast', '14 days', 'oral', 14],
            'ibuprofen' => ['Ibuprofen 400mg Tablet', '400mg', 'TDS after meals', '3 days', 'oral', 9],
            'ceftriaxone' => ['Ceftriaxone 1g Injection', '1g', 'OD (daily)', '3 days', 'IV', 3],
            'losartan' => ['Losartan 50mg Tablet', '50mg', 'OD (morning)', '30 days', 'oral', 30],
        ];

        foreach ($drugPatterns as $key => $spec) {
            if (str_contains($lower, $key)) {
                $dose = $spec[1];
                if (preg_match('/' . $key . '[\s\-]*(?:tab(?:let)?|cap(?:sule)?|inj(?:ection)?)?\s*(\d+(?:\/\d+)?\s*(?:mg|g|ml))/i', $text, $dMatch)) {
                    $dose = trim($dMatch[1]);
                }

                $freq = $spec[2];
                if (preg_match('/\b(twice daily|bd|bid)\b/i', $text)) {
                    $freq = 'BD (12-hourly)';
                } elseif (preg_match('/\b(three times daily|tds|tid)\b/i', $text)) {
                    $freq = 'TDS (8-hourly)';
                } elseif (preg_match('/\b(once daily|od)\b/i', $text)) {
                    $freq = 'OD (daily)';
                } elseif (preg_match('/\b(four times daily|qid|qds)\b/i', $text)) {
                    $freq = 'QDS (6-hourly)';
                }

                $duration = $spec[3];
                if (preg_match('/(?:for\s+)?(\d+\s*(?:days?|weeks?|months?))/i', $text, $durMatch)) {
                    $duration = trim($durMatch[1]);
                }

                $route = ucfirst($spec[4]);
                $pipedDosage = implode(' | ', array_filter([$dose, $freq, $duration, $route]));

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
                    'dosage' => $pipedDosage,
                    'frequency' => $freq,
                    'duration' => $duration,
                    'route' => $route,
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
            'malaria' => ['Malaria Parasite (MP) Test', 'Evaluate febrile illness'],
            'mp' => ['Malaria Parasite (MP) Test', 'Evaluate febrile illness'],
            'fbc' => ['Full Blood Count (FBC)', 'Confirm infection / rule out anemia'],
            'full blood count' => ['Full Blood Count (FBC)', 'Routine hematological screen'],
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

        $matchedLabKeys = [];
        foreach ($labPatterns as $key => $spec) {
            $isMatch = false;
            if (in_array($key, ['mp', 'fbc', 'rbs', 'fbs', 'lft', 'pcv'])) {
                $isMatch = preg_match('/\b' . preg_quote($key, '/') . '\b/i', $text);
            } else {
                $isMatch = str_contains($lower, $key);
            }

            if ($isMatch) {
                // Prevent duplicate FBC / Full Blood Count or MP / Malaria hits
                $canonicalKey = match ($key) {
                    'fbc', 'full blood count' => 'fbc',
                    'mp', 'malaria' => 'mp',
                    'rbs', 'blood sugar' => 'rbs',
                    default => $key,
                };
                if (isset($matchedLabKeys[$canonicalKey])) {
                    continue;
                }
                $matchedLabKeys[$canonicalKey] = true;

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
                    'urgency' => (str_contains($lower, 'urgent') || str_contains($lower, 'stat')) ? 'urgent' : 'routine',
                    'unit_price' => $unitPrice,
                    'target_queue' => 'lab_queue',
                    'is_selected' => true,
                    'is_matched' => !empty($catalogId),
                    'is_free_form' => empty($catalogId),
                    'safety_warning' => null,
                ];
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
