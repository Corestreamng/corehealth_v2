<?php

namespace App\Services;

use App\Services\LlmProviders\LlmProviderInterface;
use App\Services\LlmProviders\AnthropicAdapter;
use App\Services\LlmProviders\OpenAiAdapter;
use App\Services\LlmProviders\GeminiAdapter;
use App\Services\LlmProviders\OllamaAdapter;
use App\Services\LlmProviders\HuggingFaceAdapter;
use Illuminate\Support\Facades\Log;

/**
 * Multi-provider LLM Gateway Service.
 * Routes completion requests to the configured provider with graceful fallback.
 */
class LlmGatewayService
{
    protected array $config;

    public function __construct()
    {
        $this->config = $this->loadConfig();
    }

    /**
     * Send a completion request to the active LLM provider.
     */
    public function complete(string $systemPrompt, string $userMessage, array $options = []): array
    {
        $provider = $options['provider'] ?? $this->config['active_provider'] ?? null;
        $model = $options['model'] ?? $this->config['active_model'] ?? ($provider ? ($this->config['providers'][$provider]['default_model'] ?? null) : null);

        if (!$provider || !$model) {
            throw new \Exception('No LLM provider or model configured. Please configure in Hospital Settings → AI/LLM.');
        }

        $adapter = $this->getAdapter($provider);
        $startTime = microtime(true);

        try {
            $response = $adapter->complete($model, $systemPrompt, $userMessage, $options);
            $elapsed = round((microtime(true) - $startTime) * 1000);

            Log::info('LLM completion', [
                'provider' => $provider,
                'model' => $model,
                'input_tokens' => $adapter->estimateTokens($systemPrompt . $userMessage),
                'output_tokens' => $adapter->estimateTokens($response),
                'elapsed_ms' => $elapsed,
            ]);

            return [
                'content' => $response,
                'provider' => $provider,
                'model' => $model,
                'elapsed_ms' => $elapsed,
                'input_tokens_est' => $adapter->estimateTokens($systemPrompt . $userMessage),
                'output_tokens_est' => $adapter->estimateTokens($response),
            ];
        } catch (\Exception $e) {
            Log::error('LLM completion failed', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * List available models for a given provider.
     */
    public function listModels(string $provider): array
    {
        $adapter = $this->getAdapter($provider);
        return $adapter->listModels();
    }

    /**
     * Test a provider's connection with the given credentials.
     */
    public function testConnection(string $provider, string $apiKey = '', string $baseUrl = ''): array
    {
        $adapter = $this->createAdapter($provider, $apiKey, $baseUrl);
        return $adapter->validateConfig();
    }

    /**
     * Get all configured providers and their status.
     */
    public function getProviderStatuses(): array
    {
        $statuses = [];
        $providers = $this->config['providers'] ?? [];

        foreach ($providers as $key => $providerConfig) {
            $statuses[$key] = [
                'name' => $this->getProviderDisplayName($key),
                'enabled' => $providerConfig['enabled'] ?? false,
                'has_api_key' => !empty($providerConfig['api_key']),
                'default_model' => $providerConfig['default_model'] ?? null,
                'base_url' => $providerConfig['base_url'] ?? null,
                'is_active' => ($this->config['active_provider'] ?? null) === $key,
            ];
        }

        return $statuses;
    }

    /**
     * Check if LLM features are enabled and configured.
     */
    public function isEnabled(): bool
    {
        $provider = $this->config['active_provider'] ?? null;
        if (!$provider) return false;
        
        $model = $this->config['active_model'] ?? ($this->config['providers'][$provider]['default_model'] ?? null);

        return ($this->config['enabled'] ?? false) && !empty($model);
    }

    /**
     * Get the full LLM config.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Estimate token count for a text.
     */
    public function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Get an adapter for the specified provider using stored config.
     */
    protected function getAdapter(string $provider): LlmProviderInterface
    {
        $providerConfig = $this->config['providers'][$provider] ?? null;
        if (!$providerConfig) {
            throw new \Exception("LLM provider '{$provider}' is not configured.");
        }

        return $this->createAdapter(
            $provider,
            $providerConfig['api_key'] ?? '',
            $providerConfig['base_url'] ?? ''
        );
    }

    /**
     * Create an adapter instance for a provider.
     */
    protected function createAdapter(string $provider, string $apiKey, string $baseUrl): LlmProviderInterface
    {
        return match ($provider) {
            'anthropic' => new AnthropicAdapter($apiKey, $baseUrl ?: 'https://api.anthropic.com'),
            'openai' => new OpenAiAdapter($apiKey, $baseUrl ?: 'https://api.openai.com/v1'),
            'gemini' => new GeminiAdapter($apiKey, $baseUrl ?: 'https://generativelanguage.googleapis.com'),
            'ollama' => new OllamaAdapter($apiKey, $baseUrl ?: 'http://localhost:11434'),
            'huggingface' => new HuggingFaceAdapter($apiKey, $baseUrl ?: 'https://api-inference.huggingface.co'),
            default => throw new \Exception("Unknown LLM provider: {$provider}"),
        };
    }

    protected function getProviderDisplayName(string $key): string
    {
        return match ($key) {
            'anthropic' => 'Anthropic (Claude)',
            'openai' => 'OpenAI (GPT)',
            'gemini' => 'Google (Gemini)',
            'ollama' => 'Ollama (Local)',
            'huggingface' => 'Hugging Face',
            default => ucfirst($key),
        };
    }

    /**
     * Load LLM config from the application_status table.
     */
    protected function loadConfig(): array
    {
        $settings = appsettings();
        $raw = $settings->llm_config ?? null;

        if (is_string($raw)) {
            return json_decode($raw, true) ?: [];
        }

        if (is_array($raw)) {
            return $raw;
        }

        return [];
    }
}
