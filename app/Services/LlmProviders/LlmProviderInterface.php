<?php

namespace App\Services\LlmProviders;

/**
 * Contract for all LLM provider adapters.
 * Each adapter normalizes its provider's API into this unified interface.
 */
interface LlmProviderInterface
{
    /**
     * Send a completion request to the LLM provider.
     *
     * @param string $model       Model identifier (e.g., 'claude-sonnet-4-20250514', 'gpt-4o')
     * @param string $systemPrompt System-level instructions for the LLM
     * @param string $userMessage  The user's message / content to process
     * @param array  $options      Additional options: max_tokens, temperature, top_p, etc.
     * @return string The LLM's response text
     * @throws \Exception On API errors or connectivity issues
     */
    public function complete(string $model, string $systemPrompt, string $userMessage, array $options = []): string;

    /**
     * Fetch available models from this provider.
     *
     * @return array Array of ['id' => string, 'name' => string, 'context_window' => int|null]
     */
    public function listModels(): array;

    /**
     * Validate the provider's configuration (API key, connectivity).
     *
     * @return array ['valid' => bool, 'message' => string, 'models_count' => int|null]
     */
    public function validateConfig(): array;

    /**
     * Get the provider's display name.
     */
    public function getDisplayName(): string;

    /**
     * Estimate the token count for a given text.
     * Uses a rough heuristic (words / 0.75) as a universal fallback.
     */
    public function estimateTokens(string $text): int;
}
