<?php

namespace App\Services\LlmProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HuggingFaceAdapter implements LlmProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct(string $apiKey, string $baseUrl = 'https://api-inference.huggingface.co')
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function complete(string $model, string $systemPrompt, string $userMessage, array $options = []): string
    {
        $maxTokens = $options['max_tokens'] ?? 2048;
        $temperature = $options['temperature'] ?? 0.3;

        $prompt = "<s>[INST] <<SYS>>\n{$systemPrompt}\n<</SYS>>\n\n{$userMessage} [/INST]";

        $response = Http::timeout(120)
            ->withHeaders(['Authorization' => "Bearer {$this->apiKey}"])
            ->post("{$this->baseUrl}/models/{$model}", [
                'inputs' => $prompt,
                'parameters' => [
                    'max_new_tokens' => $maxTokens,
                    'temperature' => $temperature,
                    'return_full_text' => false,
                ],
            ]);

        if ($response->failed()) {
            $error = $response->json('error', $response->body());
            Log::error('HuggingFace API error', ['status' => $response->status(), 'error' => $error]);
            throw new \Exception("HuggingFace API error: {$error}");
        }

        $data = $response->json();
        if (is_array($data) && isset($data[0]['generated_text'])) {
            return $data[0]['generated_text'];
        }
        return is_string($data) ? $data : json_encode($data);
    }

    public function listModels(): array
    {
        // HuggingFace has thousands of models — return curated medical/clinical models
        return [
            ['id' => 'mistralai/Mistral-7B-Instruct-v0.3', 'name' => 'Mistral 7B Instruct v0.3', 'context_window' => 32768],
            ['id' => 'meta-llama/Meta-Llama-3.1-8B-Instruct', 'name' => 'Llama 3.1 8B Instruct', 'context_window' => 131072],
            ['id' => 'microsoft/Phi-3-mini-4k-instruct', 'name' => 'Phi-3 Mini 4K', 'context_window' => 4096],
            ['id' => 'google/gemma-2-9b-it', 'name' => 'Gemma 2 9B IT', 'context_window' => 8192],
            ['id' => 'Qwen/Qwen2.5-7B-Instruct', 'name' => 'Qwen 2.5 7B Instruct', 'context_window' => 131072],
        ];
    }

    public function validateConfig(): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Authorization' => "Bearer {$this->apiKey}"])
                ->get("{$this->baseUrl}/api/whoami-v2");

            if ($response->successful()) {
                $name = $response->json('name', 'Unknown');
                return ['valid' => true, 'message' => "Authenticated as: {$name}", 'models_count' => 5];
            }
            return ['valid' => false, 'message' => 'Invalid API token', 'models_count' => 0];
        } catch (\Exception $e) {
            return ['valid' => false, 'message' => $e->getMessage(), 'models_count' => 0];
        }
    }

    public function getDisplayName(): string { return 'Hugging Face'; }

    public function estimateTokens(string $text): int { return (int) ceil(mb_strlen($text) / 4); }
}
