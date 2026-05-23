<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * OpenAI Chat Completions provider. Compatible with gpt-4o-mini / gpt-4o /
 * o3-mini, etc. Configurable via system_settings (ai_openai_*).
 */
class OpenAiProvider implements AiProvider
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o-mini',
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function complete(string $prompt, int $maxTokens = 1024): string
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->post(self::ENDPOINT, [
                    'model'       => $this->model,
                    'max_tokens'  => $maxTokens,
                    'temperature' => 0.7,
                    'messages'    => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::error('OpenAI request failed', ['error' => $e->getMessage()]);
            throw new RuntimeException('OpenAI request failed: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI HTTP ' . $response->status() . ': ' . $response->body());
        }

        $body = $response->json();
        $text = $body['choices'][0]['message']['content'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('OpenAI returned empty response: ' . json_encode($body));
        }

        return trim($text);
    }
}
