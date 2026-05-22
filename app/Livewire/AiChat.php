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

        $this->messages[] = ['role' => 'user', 'content' => $query];
        $this->input = '';

        $result = app(AiAssistantService::class)->ask($query);

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
        $promptKey = (new AiAssistantService)->quickPrompts()[$key] ?? null;
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
        return (new AiAssistantService)->quickPrompts();
    }

    public function render()
    {
        return view('livewire.ai-chat');
    }
}
