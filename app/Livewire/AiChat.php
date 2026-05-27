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

    public function send(): void
    {
        $query = trim($this->input);
        if ($query === '') return;

        // Capture the conversation history BEFORE appending the new user turn,
        // so the service sees "what was said before this question". Map only
        // role+content (drop the clinics blob — UI concern, would blow the
        // token budget). Last 6 turns is plenty of context.
        $history = array_map(
            fn ($m) => ['role' => $m['role'], 'content' => (string) ($m['content'] ?? '')],
            array_slice($this->messages, -6),
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

    public function reset_(): void
    {
        $this->messages = [];
        $this->input = '';
        $this->context = [];
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
