<?php

namespace App\Services\LlmProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaAdapter implements LlmProviderInterface
{
    protected string $baseUrl;

    public function __construct(string $apiKey = '', string $baseUrl = 'http://localhost:11434')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function complete(string $model, string $systemPrompt, string $userMessage, array $options = []): string
    {
        $temperature = $options['temperature'] ?? 0.3;

        $response = Http::timeout(300)
            ->post("{$this->baseUrl}/api/chat", [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'stream' => false,
                'options' => [
                    'temperature' => $temperature,
                    'num_predict' => $options['max_tokens'] ?? 2048,
                ],
            ]);

        if ($response->failed()) {
            Log::error('Ollama API error', ['status' => $response->status()]);
            throw new \Exception("Ollama API error: " . $response->body());
        }

        return $response->json('message.content', '');
    }

    public function listModels(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/tags");
            if ($response->failed()) return [];

            return collect($response->json('models', []))->map(fn($m) => [
                'id' => $m['name'] ?? 'unknown',
                'name' => $m['name'] ?? 'Unknown',
                'context_window' => null,
            ])->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function validateConfig(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/tags");
            if ($response->successful()) {
                $count = count($response->json('models', []));
                return ['valid' => true, 'message' => "Connected. {$count} model(s).", 'models_count' => $count];
            }
            return ['valid' => false, 'message' => 'Ollama not responding', 'models_count' => 0];
        } catch (\Exception $e) {
            return ['valid' => false, 'message' => 'Cannot connect to Ollama at ' . $this->baseUrl, 'models_count' => 0];
        }
    }

    public function getDisplayName(): string { return 'Ollama (Local)'; }

    public function estimateTokens(string $text): int { return (int) ceil(mb_strlen($text) / 4); }
}
