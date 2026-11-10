<?php

namespace App\Services\LlmProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAdapter implements LlmProviderInterface
{
    protected string $apiKey;

    protected string $baseUrl;

    public function __construct(string $apiKey, string $baseUrl = 'https://generativelanguage.googleapis.com')
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function complete(string $model, string $systemPrompt, string $userMessage, array $options = []): string
    {
        $maxTokens = $options['max_tokens'] ?? 8192;
        $temperature = $options['temperature'] ?? 0.2;

        $generationConfig = [
            'maxOutputTokens' => $maxTokens,
            'temperature' => $temperature,
        ];

        // Enable structured JSON mode if requested
        if (!empty($options['response_mime_type'])) {
            $generationConfig['responseMimeType'] = $options['response_mime_type'];
        } elseif (!empty($options['json_mode'])) {
            $generationConfig['responseMimeType'] = 'application/json';
        }

        // On Gemini 2.5 / reasoning models, thinkingBudget: 0 disables reasoning latency
        // and prevents output truncation caused by thought token starvation.
        if (isset($options['thinking_budget'])) {
            $generationConfig['thinkingConfig'] = [
                'thinkingBudget' => (int) $options['thinking_budget'],
            ];
        } elseif (str_contains(strtolower($model), '2.5') || str_contains(strtolower($model), 'flash')) {
            $generationConfig['thinkingConfig'] = [
                'thinkingBudget' => 0,
            ];
        }

        $response = Http::timeout(120)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->baseUrl}/v1beta/models/{$model}:generateContent?key={$this->apiKey}", [
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $userMessage]],
                    ],
                ],
                'generationConfig' => $generationConfig,
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message', $response->body());
            Log::error('Gemini API error', ['status' => $response->status(), 'error' => $error]);

            throw new \Exception("Gemini API error: {$error}");
        }

        $data = $response->json();

        // Concatenate all text parts, skipping internal thoughts if returned
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $text = '';
        foreach ($parts as $part) {
            if (!empty($part['thought'])) {
                continue;
            }
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }

        $finishReason = $data['candidates'][0]['finishReason'] ?? null;
        if ($finishReason && $finishReason !== 'STOP') {
            Log::warning('Gemini completion finished with non-STOP status', [
                'model' => $model,
                'finish_reason' => $finishReason,
                'length' => mb_strlen($text),
            ]);
        }

        return $text;
    }

    public function listModels(): array
    {
        try {
            $response = Http::timeout(15)
                ->get("{$this->baseUrl}/v1beta/models?key={$this->apiKey}");

            if ($response->failed()) {
                return $this->getFallbackModels();
            }

            $models = $response->json('models', []);

            return collect($models)->filter(function ($m) {
                // Only include generative models
                $methods = $m['supportedGenerationMethods'] ?? [];

                return in_array('generateContent', $methods);
            })->map(fn ($m) => [
                'id' => str_replace('models/', '', $m['name']),
                'name' => $m['displayName'] ?? $m['name'],
                'context_window' => $m['inputTokenLimit'] ?? null,
            ])->values()->toArray();
        } catch (\Exception $e) {
            Log::warning('Gemini listModels exception', ['error' => $e->getMessage()]);

            return $this->getFallbackModels();
        }
    }

    public function validateConfig(): array
    {
        try {
            $models = $this->listModels();

            return [
                'valid' => count($models) > 0,
                'message' => count($models) > 0 ? 'Connected successfully' : 'No models found',
                'models_count' => count($models),
            ];
        } catch (\Exception $e) {
            return ['valid' => false, 'message' => $e->getMessage(), 'models_count' => 0];
        }
    }

    public function getDisplayName(): string
    {
        return 'Google (Gemini)';
    }

    public function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    protected function getFallbackModels(): array
    {
        return [
            ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash', 'context_window' => 1048576],
            ['id' => 'gemini-2.5-pro', 'name' => 'Gemini 2.5 Pro', 'context_window' => 1048576],
            ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash', 'context_window' => 1048576],
            ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro', 'context_window' => 2097152],
        ];
    }
}
