<?php

namespace App\Services\Ai;

use App\Models\SystemSetting;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Providers\AnthropicProvider;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\OpenAiProvider;
use RuntimeException;

/**
 * Resolves the active AI provider. Super-admin picks the provider + supplies
 * keys via /app/admin/system-settings (group=ai). DB wins; env is a fallback
 * for fresh installs that haven't seeded the settings yet.
 *
 * Default provider: gemini (Gemini 2.5 Flash).
 */
class AiProviderFactory
{
    public const PROVIDER_GEMINI    = 'gemini';
    public const PROVIDER_OPENAI    = 'openai';
    public const PROVIDER_ANTHROPIC = 'anthropic';

    public const SUPPORTED = [
        self::PROVIDER_GEMINI,
        self::PROVIDER_OPENAI,
        self::PROVIDER_ANTHROPIC,
    ];

    /**
     * Build the configured provider.
     *
     * @throws RuntimeException when the active provider has no API key set.
     */
    public function make(): AiProvider
    {
        $name = $this->activeProviderName();
        $key  = $this->apiKeyFor($name);

        if (! $key) {
            throw new RuntimeException(sprintf(
                'AI provider [%s] has no API key set. Configure it in /app/admin/system-settings (group=ai) or via env.',
                $name,
            ));
        }

        return match ($name) {
            self::PROVIDER_OPENAI    => new OpenAiProvider($key, $this->modelFor($name)),
            self::PROVIDER_ANTHROPIC => new AnthropicProvider($key, $this->modelFor($name)),
            default                  => new GeminiProvider($key, $this->modelFor($name)),
        };
    }

    /** True when the active provider has a usable API key. */
    public function isConfigured(): bool
    {
        return (bool) $this->apiKeyFor($this->activeProviderName());
    }

    /** Active provider id — gemini | openai | anthropic. */
    public function activeProviderName(): string
    {
        $raw = (string) (SystemSetting::get('ai_provider') ?? config('services.ai.default', self::PROVIDER_GEMINI));
        $raw = strtolower(trim($raw));

        return in_array($raw, self::SUPPORTED, true) ? $raw : self::PROVIDER_GEMINI;
    }

    private function apiKeyFor(string $provider): ?string
    {
        $dbKey = SystemSetting::get("ai_{$provider}_api_key");
        if (is_string($dbKey) && $dbKey !== '') {
            return $dbKey;
        }

        // Env fallback — keep legacy ANTHROPIC_API_KEY working out of the box.
        return match ($provider) {
            self::PROVIDER_GEMINI    => config('services.gemini.key'),
            self::PROVIDER_OPENAI    => config('services.openai.key'),
            self::PROVIDER_ANTHROPIC => config('services.anthropic.key'),
            default                  => null,
        };
    }

    private function modelFor(string $provider): string
    {
        $dbModel = SystemSetting::get("ai_{$provider}_model");
        if (is_string($dbModel) && $dbModel !== '') {
            return $dbModel;
        }

        return match ($provider) {
            self::PROVIDER_GEMINI    => config('services.gemini.model', 'gemini-2.5-flash'),
            self::PROVIDER_OPENAI    => config('services.openai.model', 'gpt-4o-mini'),
            self::PROVIDER_ANTHROPIC => config('services.anthropic.model', 'claude-sonnet-4-6'),
            default                  => 'gemini-2.5-flash',
        };
    }
}
