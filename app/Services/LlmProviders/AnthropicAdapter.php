<?php

namespace App\Services\LlmProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicAdapter implements LlmProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.anthropic.com')
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function complete(string $model, string $systemPrompt, string $userMessage, array $options = []): string
    {
        $maxTokens = $options['max_tokens'] ?? 2048;
        $temperature = $options['temperature'] ?? 0.3;

        $response = Http::timeout(120)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/v1/messages", [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message', $response->body());
            Log::error('Anthropic API error', ['status' => $response->status(), 'error' => $error]);
            throw new \Exception("Anthropic API error: {$error}");
        }

        $data = $response->json();
        return $data['content'][0]['text'] ?? '';
    }

    public function listModels(): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->get("{$this->baseUrl}/v1/models");

            if ($response->failed()) {
                Log::warning('Anthropic listModels failed', ['status' => $response->status()]);
                return $this->getFallbackModels();
            }

            $models = $response->json('data', []);
            return collect($models)->map(fn($m) => [
                'id' => $m['id'],
                'name' => $m['display_name'] ?? $m['id'],
                'context_window' => null,
            ])->toArray();
        } catch (\Exception $e) {
            Log::warning('Anthropic listModels exception', ['error' => $e->getMessage()]);
            return $this->getFallbackModels();
        }
    }

    public function validateConfig(): array
    {
        try {
            $models = $this->listModels();
            return [
                'valid' => count($models) > 0,
                'message' => count($models) > 0 ? 'Connected successfully' : 'Connected but no models found',
                'models_count' => count($models),
            ];
        } catch (\Exception $e) {
            return ['valid' => false, 'message' => $e->getMessage(), 'models_count' => 0];
        }
    }

    public function getDisplayName(): string
    {
        return 'Anthropic (Claude)';
    }

    public function estimateTokens(string $text): int
    {
        // ~4 chars per token for Claude
        return (int) ceil(mb_strlen($text) / 4);
    }

    protected function getFallbackModels(): array
    {
        return [
            ['id' => 'claude-sonnet-4-20250514', 'name' => 'Claude Sonnet 4', 'context_window' => 200000],
            ['id' => 'claude-3-5-sonnet-20241022', 'name' => 'Claude 3.5 Sonnet', 'context_window' => 200000],
            ['id' => 'claude-3-5-haiku-20241022', 'name' => 'Claude 3.5 Haiku', 'context_window' => 200000],
            ['id' => 'claude-3-opus-20240229', 'name' => 'Claude 3 Opus', 'context_window' => 200000],
        ];
    }
}
