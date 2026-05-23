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
     * Providers translate the inputs into their native shapes:
     *   - OpenAI / Anthropic: system + user messages
     *   - Gemini: systemInstruction + contents[user]
     *
     * @param string      $userPrompt    The user-facing turn.
     * @param int         $maxTokens     Output token budget.
     * @param float       $temperature   0.0 (deterministic) → 1.0 (creative).
     * @param string|null $systemPrompt  Persona / rules; null for plain completion.
     *
     * @throws \RuntimeException on transport, HTTP, or empty-response failures.
     */
    public function complete(
        string $userPrompt,
        int $maxTokens = 1024,
        float $temperature = 0.7,
        ?string $systemPrompt = null,
    ): string;
}
