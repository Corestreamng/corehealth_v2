<?php

namespace App\Http\Controllers;

use App\Models\AiScribeAudit;
use App\Models\Patient;
use App\Services\LlmGatewayService;
use App\Services\OrderCatalogRegistryService;
use App\Services\PatientContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LlmController extends Controller
{
    protected LlmGatewayService $gateway;

    protected PatientContextService $contextService;

    protected OrderCatalogRegistryService $catalogRegistry;

    public function __construct(
        LlmGatewayService $gateway,
        PatientContextService $contextService,
        OrderCatalogRegistryService $catalogRegistry
    ) {
        $this->gateway = $gateway;
        $this->contextService = $contextService;
        $this->catalogRegistry = $catalogRegistry;
    }

    /**
     * Generate a patient summary for the overlay.
     */
    public function generatePatientSummary(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|integer',
            'encounter_id' => 'nullable|integer',
        ]);

        if (!$this->gateway->isEnabled()) {
            return response()->json(['success' => false, 'message' => 'LLM features are disabled.'], 403);
        }

        try {
            $config = $this->gateway->getConfig();
            $systemPrompt = $config['system_prompts']['patient_summary'] ?? config('llm.prompts.patient_summary');

            // Generate full context
            $context = $this->contextService->getPatientContext($request->patient_id, $request->encounter_id);
            $contextHash = md5($context);
            $cacheKey = "llm_summary_{$request->patient_id}";
            $forceRegenerate = filter_var($request->input('force_regenerate', false), FILTER_VALIDATE_BOOLEAN);

            // Check Cache
            if (!$forceRegenerate && Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                if (isset($cachedData['hash']) && $cachedData['hash'] === $contextHash) {
                    return response()->json([
                        'success' => true,
                        'summary_text' => $cachedData['summary_text'],
                        'model_used' => $cachedData['model_used'] ?? 'cached',
                        'provider' => $cachedData['provider'] ?? 'cached',
                        'cached' => true,
                    ]);
                }
            }

            $response = $this->gateway->complete($systemPrompt, $context, [
                'max_tokens' => 4096,
                'temperature' => 0.2, // Low temp for clinical facts
            ]);

            $summaryData = [
                'hash' => $contextHash,
                'summary_text' => $response['content'],
                'model_used' => $response['model'],
                'provider' => $response['provider'],
            ];

            // Cache indefinitely until context changes
            Cache::forever($cacheKey, $summaryData);

            return response()->json([
                'success' => true,
                'summary_text' => $response['content'],
                'model_used' => $response['model'],
                'provider' => $response['provider'],
                'cached' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Patient Summary Error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to generate summary: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Polish a clinical note.
     */
    public function polishNote(Request $request)
    {
        $request->validate([
            'note_content' => 'required|string',
            'patient_id' => 'nullable|integer',
            'encounter_id' => 'nullable|integer',
            'note_type' => 'nullable|string',
        ]);

        if (!$this->gateway->isEnabled()) {
            return response()->json(['success' => false, 'message' => 'LLM features are disabled.'], 403);
        }

        try {
            $config = $this->gateway->getConfig();
            $systemPrompt = !empty(trim($config['system_prompts']['polish_note'] ?? ''))
                ? $config['system_prompts']['polish_note']
                : config('llm.prompts.polish_note');

            $context = '';
            if ($request->filled('patient_id') && (int) $request->patient_id > 0) {
                $context = $this->contextService->getPatientContext((int) $request->patient_id, $request->encounter_id ? (int) $request->encounter_id : null);
            }

            // Clean any raw HTML passed from rich-text editors (e.g. CKEditor)
            $rawContent = (string) $request->note_content;
            if (preg_match('/<[a-z][\s\S]*>/i', $rawContent)) {
                $rawContent = preg_replace('/<br\s*\/?>/i', "\n", $rawContent);
                $rawContent = preg_replace('/<\/(p|div|h[1-6]|li)>/i', "\n\n", $rawContent);
                $rawContent = strip_tags($rawContent);
                $rawContent = html_entity_decode($rawContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $rawContent = preg_replace("/\n{3,}/", "\n\n", trim($rawContent));
            }

            $userMessage = '';
            if (!empty($context)) {
                $userMessage .= "PATIENT CONTEXT (Do not include this in your output, just use it for understanding abbreviations/history):\n" .
                    $context . "\n\n---\n";
            }

            $noteType = trim($request->input('note_type', 'auto'));
            if (!empty($noteType) && $noteType !== 'auto') {
                $userMessage .= "TARGET CLINICAL PERSPECTIVE / NOTE TYPE: " . strtoupper($noteType) . "\n\n";
            }

            $userMessage .= "NOTE TO POLISH:\n" . $rawContent;

            $response = $this->gateway->complete($systemPrompt, $userMessage, [
                'max_tokens' => 4096,
                'temperature' => 0.2,
            ]);

            return response()->json([
                'success' => true,
                'polished_content' => $response['content'],
                'model_used' => $response['model'],
            ]);
        } catch (\Exception $e) {
            Log::error('Polish Note Error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to polish note: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get statuses of all configured providers (Admin UI).
     */
    public function getProviders()
    {
        // Require admin access, logic handled by middleware normally
        return response()->json([
            'success' => true,
            'providers' => $this->gateway->getProviderStatuses(),
        ]);
    }

    /**
     * Fetch models from a specific provider.
     */
    public function getModels(Request $request, $provider)
    {
        try {
            $models = $this->gateway->listModels($provider);

            return response()->json([
                'success' => true,
                'models' => $models,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Test connection to a specific provider.
     */
    public function testConnection(Request $request)
    {
        $request->validate([
            'provider' => 'required|string',
            'api_key' => 'nullable|string',
            'base_url' => 'nullable|string',
        ]);

        try {
            $result = $this->gateway->testConnection(
                $request->provider,
                $request->api_key ?? '',
                $request->base_url ?? ''
            );

            return response()->json(array_merge(['success' => true], $result));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process an ambient clinical scribe session.
     * Unifies voice dictation, existing CKEditor content, taxonomy structuring,
     * and grounded hospital order extraction.
     */
    public function processScribeSession(Request $request)
    {
        $request->validate([
            'patient_id' => 'nullable|integer',
            'encounter_id' => 'nullable|integer',
            'role' => 'nullable|string|in:doctor,nurse,maternity',
            'taxonomy' => 'nullable|string',
            'transcript' => 'nullable|string',
            'editor_content' => 'nullable|string',
            'language' => 'nullable|string',
        ]);

        if (!$this->gateway->isEnabled()) {
            return response()->json(['success' => false, 'message' => 'LLM features are currently disabled.'], 403);
        }

        try {
            $role = $request->input('role', 'doctor');
            $taxonomy = $request->input('taxonomy');
            if (empty($taxonomy)) {
                $taxonomy = match ($role) {
                    'nurse' => 'dar',
                    'maternity' => 'anc',
                    default => 'clerking', // Traditional Nigerian Clerking as default for doctors
                };
            }

            $transcript = trim((string) $request->input('transcript', ''));
            $editorContent = trim((string) $request->input('editor_content', ''));

            // Clean HTML tags from editor content if passed from CKEditor
            if (preg_match('/<[a-z][\s\S]*>/i', $editorContent)) {
                $editorContent = preg_replace('/<br\s*\/?>/i', "\n", $editorContent);
                $editorContent = preg_replace('/<\/(p|div|h[1-6]|li)>/i', "\n\n", $editorContent);
                $editorContent = strip_tags($editorContent);
                $editorContent = html_entity_decode($editorContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $editorContent = preg_replace("/\n{3,}/", "\n\n", trim($editorContent));
            }

            if (empty($transcript) && empty($editorContent)) {
                return response()->json(['success' => false, 'message' => 'No audio transcript or clinical text provided.'], 422);
            }

            // Retrieve Patient Context & Allergies
            $patientId = $request->input('patient_id') ? (int) $request->input('patient_id') : null;
            $encounterId = $request->input('encounter_id') ? (int) $request->input('encounter_id') : null;
            $patientContext = '';
            $patientAllergies = [];

            if ($patientId) {
                $patientContext = $this->contextService->getPatientContext($patientId, $encounterId);
                $patient = Patient::find($patientId);
                if ($patient && !empty($patient->allergies)) {
                    $patientAllergies = is_array($patient->allergies) ? $patient->allergies : array_filter(array_map('trim', explode(',', (string) $patient->allergies)));
                }
            }

            // Retrieve Catalog Categories context
            $catalogContext = $this->catalogRegistry->getCategoryPromptContext();

            // Build Prompts
            $config = $this->gateway->getConfig();
            $systemPrompt = $config['system_prompts']['clinical_scribe'] ?? config('llm.prompts.clinical_scribe');

            $userMessage = "TARGET PERSPECTIVE: ROLE=" . strtoupper($role) . ", TAXONOMY=" . strtoupper($taxonomy) . "\n\n";
            $userMessage .= $catalogContext . "\n\n";

            if (!empty($patientContext)) {
                $userMessage .= "PATIENT CLINICAL CONTEXT (Use for history/abbreviations, do not echo verbatim):\n" .
                    $patientContext . "\n\n---\n";
            }

            if (!empty($editorContent)) {
                $userMessage .= "EXISTING NOTE CONTENT IN CLINICAL EDITOR (Preserve and merge with transcript):\n" .
                    $editorContent . "\n\n---\n";
            }

            if (!empty($transcript)) {
                $userMessage .= "NEW SPOKEN AUDIO DICTATION / TRANSCRIPT TO INCORPORATE:\n" .
                    $transcript . "\n\n";
            }

            $response = $this->gateway->complete($systemPrompt, $userMessage, [
                'max_tokens' => 4096,
                'temperature' => 0.15,
            ]);

            $content = trim($response['content']);

            // Parse JSON response (safely handle markdown fence wrapping if emitted)
            $parsed = $this->extractJsonPayload($content);
            if (!$parsed || !isset($parsed['structured_sections'])) {
                Log::warning('LLM Scribe returned malformed JSON, constructing fallback', ['raw' => $content]);
                $parsed = [
                    'summary_headline' => 'Clinical Note (' . strtoupper($taxonomy) . ')',
                    'taxonomy_type' => $taxonomy,
                    'structured_sections' => [
                        ['key' => 'NOTE', 'title' => 'Clinical Note', 'content' => $content],
                    ],
                    'formatted_markdown' => $content,
                    'extracted_orders' => [],
                ];
            }

            // Ground extracted orders against CoreHealth Products and Services Catalogs
            $rawOrders = $parsed['extracted_orders'] ?? [];
            $groundedOrders = $this->catalogRegistry->groundOrders($rawOrders, $patientAllergies);

            // Filter any safety warnings
            $safetyWarnings = [];
            foreach ($groundedOrders as $gOrder) {
                if (!empty($gOrder['safety_warning'])) {
                    $safetyWarnings[] = $gOrder['safety_warning'] . " ({$gOrder['item_name']})";
                }
            }

            return response()->json([
                'success' => true,
                'summary_headline' => $parsed['summary_headline'] ?? 'Clinical Summary',
                'taxonomy_type' => $parsed['taxonomy_type'] ?? $taxonomy,
                'structured_sections' => $parsed['structured_sections'] ?? [],
                'formatted_markdown' => $parsed['formatted_markdown'] ?? '',
                'grounded_orders' => $groundedOrders,
                'safety_warnings' => $safetyWarnings,
                'model_used' => $response['model'],
                'provider' => $response['provider'],
            ]);
        } catch (\Exception $e) {
            Log::error('Clinical Scribe Session Error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to process scribe session: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Convert an already structured note losslessly to another taxonomy.
     */
    public function convertFormat(Request $request)
    {
        $request->validate([
            'current_sections' => 'required',
            'from_taxonomy' => 'required|string',
            'to_taxonomy' => 'required|string',
            'role' => 'nullable|string',
        ]);

        if (!$this->gateway->isEnabled()) {
            return response()->json(['success' => false, 'message' => 'LLM features are currently disabled.'], 403);
        }

        try {
            $sections = is_array($request->current_sections)
                ? $request->current_sections
                : json_decode($request->current_sections, true);

            $systemPrompt = config('llm.prompts.convert_taxonomy');
            $userMessage = "CONVERT CLINICAL NOTE FROM TAXONOMY '" . strtoupper($request->from_taxonomy) .
                "' TO TARGET TAXONOMY '" . strtoupper($request->to_taxonomy) . "'.\n\n" .
                "CURRENT STRUCTURED SECTIONS (PRESERVE 100% OF DATA):\n" .
                json_encode($sections, JSON_PRETTY_PRINT);

            $response = $this->gateway->complete($systemPrompt, $userMessage, [
                'max_tokens' => 4096,
                'temperature' => 0.1,
            ]);

            $parsed = $this->extractJsonPayload($response['content']);

            return response()->json([
                'success' => true,
                'taxonomy_type' => $request->to_taxonomy,
                'structured_sections' => $parsed['structured_sections'] ?? $sections,
                'formatted_markdown' => $parsed['formatted_markdown'] ?? '',
                'summary_headline' => $parsed['summary_headline'] ?? 'Converted Clinical Note',
            ]);
        } catch (\Exception $e) {
            Log::error('Clinical Scribe Taxonomy Conversion Error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to convert format: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sign and commit an AI Scribe session into the audit log.
     */
    public function signScribeSession(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|integer',
            'encounter_id' => 'nullable|integer',
            'role' => 'nullable|string',
            'taxonomy_type' => 'nullable|string',
            'final_signed_note' => 'required|string',
            'original_transcript' => 'nullable|string',
            'baseline_editor_content' => 'nullable|string',
            'structured_note_json' => 'nullable',
            'orders_dispatched_json' => 'nullable',
            'model_used' => 'nullable|string',
            'provider' => 'nullable|string',
        ]);

        try {
            $audit = AiScribeAudit::create([
                'patient_id' => $request->patient_id,
                'encounter_id' => $request->encounter_id,
                'user_id' => Auth::id() ?? 1,
                'role' => $request->input('role', 'doctor'),
                'taxonomy_type' => $request->input('taxonomy_type', 'clerking'),
                'original_transcript' => $request->original_transcript,
                'baseline_editor_content' => $request->baseline_editor_content,
                'structured_note_json' => is_array($request->structured_note_json) ? $request->structured_note_json : json_decode($request->structured_note_json ?? '[]', true),
                'final_signed_note' => $request->final_signed_note,
                'orders_extracted_json' => is_array($request->orders_extracted_json) ? $request->orders_extracted_json : json_decode($request->orders_extracted_json ?? '[]', true),
                'orders_dispatched_json' => is_array($request->orders_dispatched_json) ? $request->orders_dispatched_json : json_decode($request->orders_dispatched_json ?? '[]', true),
                'model_used' => $request->model_used,
                'provider' => $request->provider,
                'signed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Clinical note signed and audit record archived.',
                'audit_id' => $audit->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Sign Scribe Session Error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to save audit record: ' . $e->getMessage()], 500);
        }
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

        return null;
    }
}
