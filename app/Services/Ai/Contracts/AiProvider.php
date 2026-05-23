<?php

namespace App\Services\Ai\Contracts;

/**
 * One LLM provider — Gemini / OpenAI / Anthropic share this surface so the
 * rest of the app (article generation, chat replies, Excel column mapping)
 * stays provider-agnostic. Pick the active one through AiProviderFactory.
 */
interface AiProvider
{
    /** Short identifier used in settings: 'gemini' | 'openai' | 'anthropic'. */
    public function name(): string;

    /** Active model name (for diagnostics + logs). */
    public function model(): string;

    /**
     * Run a single-turn prompt and return the model's text response.
     *
     * @throws \RuntimeException on transport, HTTP, or empty-response failures.
     */
    public function complete(string $prompt, int $maxTokens = 1024): string;
}
