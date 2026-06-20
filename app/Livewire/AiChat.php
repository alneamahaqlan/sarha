<?php

namespace App\Livewire;

use App\Services\AiAssistantService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AiChat extends Component
{
    public bool $open = false;
    public string $input = '';

    /** @var array<int, array{role: string, content: string, clinics?: array}> */
    public array $messages = [];

    /**
     * Persistent conversation state that travels with the user across turns —
     * this is what gives the assistant real memory instead of "answers the
     * last line only". Mutated server-side by AiAssistantService::ask() and
     * stored back here so the next message picks up where this one left off.
     *
     * Shape: { city_id, category_id, last_clinic_ids[], doctor_name }
     */
    public array $context = [];

    /**
     * Laravel-session key under which the whole conversation (messages +
     * context) is persisted. Storing it in the SESSION — not just the Livewire
     * component snapshot — is what lets the chat survive a full page reload or
     * navigation across the site: the conversation only ever ends when the
     * customer explicitly starts a new one.
     */
    private const SESSION_KEY = 'ai_chat';

    /**
     * Hard ceiling on stored turns so a very long-running session can't bloat
     * the session store. We keep the most recent MAX_STORED messages — far more
     * than any single visit needs, while still bounding growth.
     */
    private const MAX_STORED = 200;

    /**
     * Restore the conversation from the session on every (re)mount — page
     * reload, navigation, or reopening the widget. Without this the chat would
     * silently reset to empty the moment the page refreshed.
     */
    public function mount(): void
    {
        $saved = session(self::SESSION_KEY, []);
        $this->messages = is_array($saved['messages'] ?? null) ? $saved['messages'] : [];
        $this->context  = is_array($saved['context'] ?? null) ? $saved['context'] : [];
    }

    public function send(): void
    {
        $query = trim($this->input);
        if ($query === '') return;

        // The ONLY thing that clears the conversation: an explicit "start over"
        // command from the customer. Everything else is treated as a normal
        // turn that builds on the full history.
        if ($this->isRestartCommand($query)) {
            $this->reset_();
            return;
        }

        // Send the FULL conversation history (not just the last few turns) so
        // every new question is understood in the context of everything the
        // customer asked before. We drop the per-message clinics blob (a UI
        // concern) and hand role+content only; the service applies its own
        // safe window/budget on top.
        $history = array_map(
            fn ($m) => ['role' => $m['role'], 'content' => (string) ($m['content'] ?? '')],
            $this->messages,
        );

        $this->messages[] = ['role' => 'user', 'content' => $query];
        $this->input = '';

        $result = app(AiAssistantService::class)->ask($query, null, $history, $this->context);

        // Persist the updated context — the service writes back the city,
        // category, doctor and last_clinic_ids it learned this turn, and we
        // hand them back on the next ask() so the bot remembers them.
        $this->context = $result['context'] ?? $this->context;

        $this->messages[] = [
            'role'    => 'assistant',
            'content' => $result['reply'],
            'kind'    => $result['kind'],
            'clinics' => $result['clinics']->map(fn($c) => [
                'name' => $c->name,
                'slug' => $c->slug,
                'city' => $c->city?->display_name,
                'min_price' => $c->min_price ?? null,
                'rating' => round($c->google_reviews_avg_rating ?? 0, 1),
            ])->all(),
        ];

        $this->persist();
    }

    public function quickPrompt(string $key): void
    {
        // Resolve through the container — AiAssistantService now needs
        // AiProviderFactory injected, so direct `new` would throw.
        $promptKey = app(AiAssistantService::class)->quickPrompts()[$key] ?? null;
        if (! $promptKey) return;
        $this->input = __($promptKey);
        $this->send();
    }

    /**
     * Clears the conversation and wipes it from the session. Triggered only by
     * an explicit customer action — the "new conversation" button or a typed
     * "start over" command (see isRestartCommand).
     */
    public function reset_(): void
    {
        $this->messages = [];
        $this->input = '';
        $this->context = [];
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Writes the current conversation back to the session, bounded by
     * MAX_STORED so growth stays in check.
     */
    private function persist(): void
    {
        if (count($this->messages) > self::MAX_STORED) {
            $this->messages = array_slice($this->messages, -self::MAX_STORED);
        }

        session([self::SESSION_KEY => [
            'messages' => $this->messages,
            'context'  => $this->context,
        ]]);
    }

    /**
     * True only when the customer is explicitly asking to start fresh
     * ("ابدأ من جديد"، "محادثة جديدة"، "start over", "reset", …). Kept narrow
     * and short-message-gated so a sentence like "اشرح لي الموضوع من جديد"
     * (please re-explain) is NOT mistaken for a reset.
     */
    private function isRestartCommand(string $query): bool
    {
        $q = mb_strtolower(trim($query));
        $q = rtrim($q, " \t\n\r\0\x0B.!؟?،,؛");

        // Light Arabic normalization so alef/ya/ta-marbuta variants all match.
        $norm = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $q) ?? $q;
        $norm = strtr($norm, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه']);

        // Only short messages can be commands — guards against false positives
        // buried inside a longer, genuine question.
        $words = array_filter(preg_split('/\s+/u', $norm) ?: [], fn ($w) => $w !== '');
        if (count($words) > 5) return false;

        $triggers = [
            // Arabic (already normalized: أ→ا, ة→ه, ى→ي)
            'ابدا من جديد', 'ابدا من البدايه', 'ابدا جديد', 'بدايه جديده',
            'محادثه جديده', 'حوار جديد', 'مسح المحادثه', 'امسح المحادثه',
            'محادثه جديد', 'من جديد ابدا',
            // English
            'start over', 'start again', 'new conversation', 'new chat',
            'restart', 'reset', 'reset chat', 'clear chat', 'clear conversation',
        ];

        foreach ($triggers as $t) {
            $tn = strtr($t, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه']);
            if ($norm === $tn || str_contains($norm, $tn)) return true;
        }
        return false;
    }

    #[Computed]
    public function quickPrompts(): array
    {
        return app(AiAssistantService::class)->quickPrompts();
    }

    public function render()
    {
        return view('livewire.ai-chat');
    }
}
