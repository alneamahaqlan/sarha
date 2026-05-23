<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Anthropic Claude provider. Mirrors the request shape the original
 * AnthropicService used (Messages API v2023-06-01) so the existing API key
 * format and rate limits keep working.
 */
class AnthropicProvider implements AiProvider
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-4-6',
    ) {}

    public function name(): string
    {
        return 'anthropic';
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
        $body = [
            'model'       => $this->model,
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
            'messages'    => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        // Claude takes the system prompt as a sibling field, not a message.
        if ($systemPrompt !== null && $systemPrompt !== '') {
            $body['system'] = $systemPrompt;
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'content-type'      => 'application/json',
                ])
                ->post(self::ENDPOINT, $body);
        } catch (\Throwable $e) {
            Log::error('Anthropic request failed', ['error' => $e->getMessage()]);
            throw new RuntimeException('Anthropic request failed: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            throw new RuntimeException('Anthropic HTTP ' . $response->status() . ': ' . $response->body());
        }

        $payload = $response->json();
        $text = $payload['content'][0]['text'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Anthropic returned empty response: ' . json_encode($payload));
        }

        return trim($text);
    }
}
