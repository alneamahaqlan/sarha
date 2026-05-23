<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Google Gemini provider — default for Saerha. Uses the v1beta REST endpoint
 * with an API key (no OAuth). Models: gemini-2.5-flash (fast/cheap),
 * gemini-2.5-pro (smarter, slower).
 */
class GeminiProvider implements AiProvider
{
    private const ENDPOINT_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gemini-2.5-flash',
    ) {}

    public function name(): string
    {
        return 'gemini';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function complete(
        string $userPrompt,
        int $maxTokens = 1024,
        float $temperature = 0.7,
        ?string $systemPrompt = null,
    ): string {
        $url = sprintf(self::ENDPOINT_TEMPLATE, urlencode($this->model));

        $body = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [['text' => $userPrompt]],
                ],
            ],
            'generationConfig' => [
                'maxOutputTokens' => $maxTokens,
                'temperature'     => $temperature,
                // Gemini 2.5 family eats `maxOutputTokens` with internal thinking
                // tokens before emitting the visible reply — disabling thinking
                // keeps chat snappy and prevents truncated mid-sentence answers.
                // Field is ignored by older models, safe to send unconditionally.
                'thinkingConfig'  => ['thinkingBudget' => 0],
            ],
        ];

        // Gemini supports `systemInstruction` natively (v1beta).
        if ($systemPrompt !== null && $systemPrompt !== '') {
            $body['systemInstruction'] = [
                'parts' => [['text' => $systemPrompt]],
            ];
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->post($url, $body);
        } catch (\Throwable $e) {
            Log::error('Gemini request failed', ['error' => $e->getMessage()]);
            throw new RuntimeException('Gemini request failed: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            throw new RuntimeException('Gemini HTTP ' . $response->status() . ': ' . $response->body());
        }

        $payload = $response->json();

        // Standard shape: candidates[0].content.parts[0].text
        $text = $payload['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            // Safety-blocked replies come back with promptFeedback instead of candidates.
            $reason = $payload['promptFeedback']['blockReason']
                ?? $payload['candidates'][0]['finishReason']
                ?? 'empty';
            throw new RuntimeException('Gemini returned empty response (' . $reason . '): ' . json_encode($payload));
        }

        return trim($text);
    }
}
