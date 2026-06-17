<?php

namespace App\Http\Controllers;

use App\Services\LlmGatewayService;
use App\Services\PatientContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class LlmController extends Controller
{
    protected LlmGatewayService $gateway;
    protected PatientContextService $contextService;

    public function __construct(LlmGatewayService $gateway, PatientContextService $contextService)
    {
        $this->gateway = $gateway;
        $this->contextService = $contextService;
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
            'patient_id' => 'required|integer',
            'encounter_id' => 'nullable|integer',
        ]);

        if (!$this->gateway->isEnabled()) {
            return response()->json(['success' => false, 'message' => 'LLM features are disabled.'], 403);
        }

        try {
            $config = $this->gateway->getConfig();
            $systemPrompt = $config['system_prompts']['polish_note'] ?? config('llm.prompts.polish_note');

            // Generate full context to help LLM understand abbreviations and clinical history
            $context = $this->contextService->getPatientContext($request->patient_id, $request->encounter_id);

            $userMessage = "PATIENT CONTEXT (Do not include this in your output, just use it for understanding abbreviations/history):\n" .
                $context . "\n\n" .
                "---\nNOTE TO POLISH:\n" . $request->note_content;

            $response = $this->gateway->complete($systemPrompt, $userMessage, [
                'max_tokens' => 2000,
                'temperature' => 0.3,
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
            'providers' => $this->gateway->getProviderStatuses()
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
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
