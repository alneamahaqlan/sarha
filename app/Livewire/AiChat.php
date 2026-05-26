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

    public function send(): void
    {
        $query = trim($this->input);
        if ($query === '') return;

        // Capture the conversation history BEFORE appending the new user turn,
        // so the service sees "what was said before this question" — that's how
        // follow-ups like "really?" or "explain that" get the right context.
        // Map only role+content (drop the clinics blob — it's a UI concern and
        // would blow the token budget). Last 6 turns is plenty of context
        // without burning tokens on stale exchanges.
        $history = array_map(
            fn ($m) => ['role' => $m['role'], 'content' => (string) ($m['content'] ?? '')],
            array_slice($this->messages, -6),
        );

        $this->messages[] = ['role' => 'user', 'content' => $query];
        $this->input = '';

        $result = app(AiAssistantService::class)->ask($query, null, $history);

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
