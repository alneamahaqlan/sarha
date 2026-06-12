<?php

namespace App\Services;

use App\Enums\ImpressionSource;
use App\Jobs\SummarizeUserInteractionJob;
use App\Models\AiAssistantLog;
use App\Models\AiRestriction;
use App\Models\Clinic;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Wraps AiAssistantService::ask with the admin-managed safety net:
 *
 *  ┌─ preflight() ──────────────────────────────────────────────────────┐
 *  │  • Match query against banned_topic restrictions → return rejected │
 *  │  • Match query against emergency_keyword restrictions → return     │
 *  │    emergency (canned response + admin-configured ambulance number) │
 *  └────────────────────────────────────────────────────────────────────┘
 *  ┌─ postflight() ─────────────────────────────────────────────────────┐
 *  │  • Strip blocklisted clinics + categories from the assistant reply │
 *  │  • Append admin tone hint / system_prompt_extension if relevant    │
 *  │  • Log everything to ai_assistant_logs (+ pivots)                  │
 *  │  • Bump each surfaced clinic's clinic_impressions under source=ai  │
 *  │    — blends silently into the impressions total, by design         │
 *  └────────────────────────────────────────────────────────────────────┘
 *
 * Side-effects (logging, stat bumping) are wrapped in try/catch so a
 * partial failure can never break the assistant's reply to the user.
 */
class AiAssistantInterceptor
{
    public function __construct(private readonly AiAssistantService $ai) {}

    /**
     * Single-entry chat call: runs preflight, calls the underlying
     * service if not short-circuited, runs postflight. Returns the
     * shape the AiAssistantService::ask call would have returned, so
     * controllers can drop this in without changing their response handling.
     */
    public function handle(string $query, ?int $cityId, Request $request): array
    {
        // 1) Resolve current actor (auth() guard cascade + visitor uuid fallback).
        $actor = $this->resolveActor($request);

        // 2) Preflight — short-circuit banned topics and emergencies.
        $shortCircuit = $this->preflight($query);
        if ($shortCircuit !== null) {
            $result = $shortCircuit['result'];
            $this->safelyLog($query, $result, $actor, [
                'blocked_by_restriction_id' => $shortCircuit['restriction_id'] ?? null,
                'was_blocked' => $shortCircuit['was_blocked'] ?? false,
                'was_emergency' => $shortCircuit['was_emergency'] ?? false,
                'response_ms' => 0,
            ]);

            return $result;
        }

        // 3) Hand off to the underlying assistant + time it.
        $startedAt = microtime(true);
        $result = $this->ai->ask($query, $cityId);
        $responseMs = (int) round((microtime(true) - $startedAt) * 1000);

        // 4) Postflight — filter blocklist + log + stats + (async) summarize.
        $result = $this->applyBlocklist($result);
        $logId = $this->safelyLog($query, $result, $actor, ['response_ms' => $responseMs]);

        // 5) Fire the summarization job for authenticated users only,
        //    and only for kinds that carry actual content worth analyzing.
        if ($logId && $actor['user_id'] && in_array($result['kind'] ?? '', ['matched', 'details', 'follow_up', 'freeform'], true)) {
            try {
                SummarizeUserInteractionJob::dispatch($logId);
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch SummarizeUserInteractionJob', ['err' => $e->getMessage()]);
            }
        }

        return $result;
    }

    // ── Preflight ────────────────────────────────────────────────────────

    /**
     * Returns ['result' => [...], ...meta] when the query is short-circuited,
     * or null when the underlying assistant should run.
     */
    private function preflight(string $query): ?array
    {
        $normalized = $this->normalize($query);
        $restrictions = $this->restrictions();

        // 1) Emergency keywords — admin-added. These augment the built-in
        //    detectEmergency() that already lives in AiAssistantService.
        foreach ($restrictions->where('type', AiRestriction::TYPE_EMERGENCY_KEYWORD) as $r) {
            if ($this->matches($normalized, $r->value)) {
                return [
                    'result' => $this->emergencyResponse(),
                    'restriction_id' => $r->id,
                    'was_emergency' => true,
                ];
            }
        }

        // 2) Banned topics. response_override gives the canned reply; if
        //    the admin didn't set one, use the platform-wide refusal.
        foreach ($restrictions->where('type', AiRestriction::TYPE_BANNED_TOPIC) as $r) {
            if ($this->matches($normalized, $r->value)) {
                $reply = $r->response_override
                    ?: __('ai_center.default_rejection');

                return [
                    'result' => $this->cannedReply($reply, 'rejected'),
                    'restriction_id' => $r->id,
                    'was_blocked' => true,
                ];
            }
        }

        return null;
    }

    /**
     * Active rules, cached for `ai_restrictions_cache_ttl` seconds (default
     * 60) to spare every chat turn from a roundtrip.
     */
    public function restrictions(): EloquentCollection
    {
        $ttl = max(1, (int) SystemSetting::get('ai_restrictions_cache_ttl', 60));

        return Cache::remember(
            AiRestriction::CACHE_KEY,
            $ttl,
            fn () => AiRestriction::active()->get(),
        );
    }

    // ── Postflight ───────────────────────────────────────────────────────

    /**
     * Strip blocklisted clinics + categories from the reply payload.
     * AiAssistantService::ask returns a `clinics` collection; we filter
     * it in place. Categories show up as part of the clinic relations,
     * so we strip them from each surviving clinic's eager-loaded relation.
     */
    private function applyBlocklist(array $result): array
    {
        $clinics = $result['clinics'] ?? null;
        if (! $clinics instanceof EloquentCollection || $clinics->isEmpty()) {
            return $result;
        }

        $blockedClinics = $this->blocklistIds(AiRestriction::TYPE_CLINIC_BLOCKLIST);
        $blockedCategories = $this->blocklistIds(AiRestriction::TYPE_CATEGORY_BLOCKLIST);

        if ($blockedClinics->isEmpty() && $blockedCategories->isEmpty()) {
            return $result;
        }

        $filtered = $clinics
            ->reject(fn (Clinic $c) => $blockedClinics->contains($c->id))
            ->reject(function (Clinic $c) use ($blockedCategories) {
                if ($blockedCategories->isEmpty()) {
                    return false;
                }
                $cats = $c->relationLoaded('categories') ? $c->categories->pluck('id') : collect();

                return $cats->intersect($blockedCategories)->isNotEmpty();
            })
            ->values();

        $result['clinics'] = $filtered;

        return $result;
    }

    private function blocklistIds(string $type): Collection
    {
        return $this->restrictions()
            ->where('type', $type)
            ->pluck('value')
            ->map(fn ($v) => (int) $v);
    }

    // ── Side-effects ─────────────────────────────────────────────────────

    /**
     * Write the log row + pivots + bump clinic stats. Wrapped to swallow
     * any failure — logging must never break the chat flow. Returns the
     * persisted log id so the caller can wire follow-up work (e.g. queue
     * the summarization job).
     */
    private function safelyLog(string $query, array $result, array $actor, array $overrides): ?int
    {
        try {
            return DB::transaction(function () use ($query, $result, $actor, $overrides) {
                $clinics = $result['clinics'] ?? collect();
                $clinicIds = $clinics instanceof EloquentCollection ? $clinics->pluck('id')->all() : [];
                $categoryIds = $this->collectCategoryIds($clinics);

                $conversationId = $this->conversationIdFor($actor);

                $log = AiAssistantLog::create([
                    'user_id' => $actor['user_id'],
                    'visitor_id' => $actor['visitor_id'],
                    'guard' => $actor['guard'],
                    'conversation_id' => $conversationId,
                    'query' => $query,
                    'reply' => (string) ($result['reply'] ?? ''),
                    'kind' => (string) ($result['kind'] ?? 'unknown'),
                    'provider' => $result['provider'] ?? null,
                    'model' => $this->modelFromProvider($result['provider'] ?? null),
                    'response_ms' => $overrides['response_ms'] ?? null,
                    'locale' => app()->getLocale(),
                    'blocked_by_restriction_id' => $overrides['blocked_by_restriction_id'] ?? null,
                    'was_blocked' => (bool) ($overrides['was_blocked'] ?? false),
                    'was_emergency' => (bool) ($overrides['was_emergency'] ?? false),
                ]);

                if (! empty($clinicIds)) {
                    $now = now();
                    $log->clinics()->attach(array_combine(
                        $clinicIds,
                        array_fill(0, count($clinicIds), ['created_at' => $now]),
                    ));
                }
                if (! empty($categoryIds)) {
                    $now = now();
                    $log->categories()->attach(array_combine(
                        $categoryIds,
                        array_fill(0, count($categoryIds), ['created_at' => $now]),
                    ));
                }

                // Multi-source impression: each clinic that surfaced
                // in this reply gets a bump under source='ai'. The
                // ImpressionTrackerService handles the upsert + cascade
                // (none here since we don't track per-service AI
                // mentions at this layer — that would require parsing
                // the reply text). Clinic-level only is enough for the
                // dashboard.
                if (! empty($clinicIds)) {
                    app(ImpressionTrackerService::class)
                        ->trackManyClinics($clinicIds, ImpressionSource::AI);
                    $log->update(['stats_bumped' => true]);
                }

                return $log->id;
            });
        } catch (\Throwable $e) {
            // Never let logging break the chat reply.
            Log::warning('AiAssistantInterceptor logging failed', [
                'err' => $e->getMessage(),
                'class' => get_class($e),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return null;
        }
    }

    /**
     * Find the existing conversation this turn belongs to, or mint a new
     * uuid. Heuristic: same user/visitor + most recent log within the
     * `ai_conversation_window_minutes` setting (default 30) = same thread.
     */
    private function conversationIdFor(array $actor): string
    {
        $windowMinutes = max(1, (int) SystemSetting::get('ai_conversation_window_minutes', 30));
        $recent = AiAssistantLog::query()
            ->when($actor['user_id'], fn ($q) => $q->where('user_id', $actor['user_id']))
            ->when(! $actor['user_id'] && $actor['visitor_id'], fn ($q) => $q->where('visitor_id', $actor['visitor_id']))
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->whereNotNull('conversation_id')
            ->latest('id')
            ->value('conversation_id');

        return $recent ?: Str::uuid()->toString();
    }

    private function collectCategoryIds($clinics): array
    {
        if (! $clinics instanceof EloquentCollection) {
            return [];
        }
        $ids = [];
        foreach ($clinics as $c) {
            if ($c->relationLoaded('categories')) {
                foreach ($c->categories as $cat) {
                    $ids[$cat->id] = true;
                }
            }
        }

        return array_keys($ids);
    }

    private function modelFromProvider(?string $provider): ?string
    {
        return match ($provider) {
            'gemini' => SystemSetting::get('ai_gemini_model'),
            'openai' => SystemSetting::get('ai_openai_model'),
            'anthropic' => SystemSetting::get('ai_anthropic_model'),
            default => null,
        };
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function resolveActor(Request $request): array
    {
        foreach (['admin', 'clinic', 'web'] as $guard) {
            if (Auth::guard($guard)->check()) {
                $u = Auth::guard($guard)->user();

                return [
                    // Only the 'web' guard maps to the User table; admin /
                    // clinic guards live in their own tables so user_id
                    // stays null for them (we keep their guard string).
                    'user_id' => $guard === 'web' ? $u->getKey() : null,
                    'guard' => $guard,
                    'visitor_id' => null,
                ];
            }
        }

        // Anonymous visitor — hash a stable opaque id per session if
        // present, otherwise mint a one-off UUID for this single turn.
        $vid = $request->session()->get('ai_visitor_id');
        if (! $vid) {
            $vid = Str::uuid()->toString();
            try {
                $request->session()->put('ai_visitor_id', $vid);
            } catch (\Throwable $e) {
                // sessionless contexts (CLI, certain test harnesses) —
                // just keep the per-call uuid.
            }
        }

        return ['user_id' => null, 'guard' => null, 'visitor_id' => $vid];
    }

    private function emergencyResponse(): array
    {
        $template = SystemSetting::get('ai_emergency_response_template');
        $ambulance = SystemSetting::get('ai_emergency_ambulance', '997');

        $reply = $template
            ? str_replace(':ambulance', (string) $ambulance, (string) $template)
            : __('ai_center.default_emergency', ['ambulance' => $ambulance]);

        return $this->cannedReply($reply, 'emergency');
    }

    private function cannedReply(string $reply, string $kind): array
    {
        return [
            'kind' => $kind,
            'reply' => $reply,
            'clinics' => new EloquentCollection,
            'context' => [],
            'provider' => null,
            'assistant_name' => SystemSetting::get('ai_assistant_name', 'سلمى'),
        ];
    }

    /**
     * Cheap ASCII / Arabic lowercase + trim — same idea as the
     * normalization the underlying assistant service uses, kept local so
     * the interceptor doesn't reach into private helpers.
     */
    private function normalize(string $s): string
    {
        $s = mb_strtolower($s);
        // Strip Arabic tashkeel + unify alef/ya/ta marbuta so admins
        // don't have to type every variant.
        $s = preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', $s);
        $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه']);

        return trim($s);
    }

    private function matches(string $normalizedQuery, string $needle): bool
    {
        $needle = $this->normalize($needle);

        return $needle !== '' && mb_strpos($normalizedQuery, $needle) !== false;
    }
}
