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
        $maxTokens = $options['max_tokens'] ?? 2048;
        $temperature = $options['temperature'] ?? 0.3;

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
                'generationConfig' => [
                    'maxOutputTokens' => $maxTokens,
                    'temperature' => $temperature,
                ],
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message', $response->body());
            Log::error('Gemini API error', ['status' => $response->status(), 'error' => $error]);
            throw new \Exception("Gemini API error: {$error}");
        }

        $data = $response->json();
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
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
            })->map(fn($m) => [
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
