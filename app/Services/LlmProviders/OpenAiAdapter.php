<?php

namespace App\Services\LlmProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiAdapter implements LlmProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.openai.com/v1')
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
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message', $response->body());
            Log::error('OpenAI API error', ['status' => $response->status(), 'error' => $error]);
            throw new \Exception("OpenAI API error: {$error}");
        }

        $data = $response->json();
        return $data['choices'][0]['message']['content'] ?? '';
    }

    public function listModels(): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['Authorization' => "Bearer {$this->apiKey}"])
                ->get("{$this->baseUrl}/models");

            if ($response->failed()) {
                return $this->getFallbackModels();
            }

            $models = $response->json('data', []);
            // Filter to chat models only
            $chatModels = collect($models)->filter(function ($m) {
                $id = $m['id'] ?? '';
                return str_starts_with($id, 'gpt-') || str_contains($id, 'o1') || str_contains($id, 'o3');
            })->map(fn($m) => [
                'id' => $m['id'],
                'name' => $m['id'],
                'context_window' => null,
            ])->sortBy('id')->values()->toArray();

            return count($chatModels) > 0 ? $chatModels : $this->getFallbackModels();
        } catch (\Exception $e) {
            Log::warning('OpenAI listModels exception', ['error' => $e->getMessage()]);
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
        return 'OpenAI (GPT)';
    }

    public function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    protected function getFallbackModels(): array
    {
        return [
            ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'context_window' => 128000],
            ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini', 'context_window' => 128000],
            ['id' => 'gpt-4-turbo', 'name' => 'GPT-4 Turbo', 'context_window' => 128000],
            ['id' => 'gpt-3.5-turbo', 'name' => 'GPT-3.5 Turbo', 'context_window' => 16385],
        ];
    }
}
