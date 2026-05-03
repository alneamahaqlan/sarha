<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    protected $fillable = ['clinic_id', 'title', 'type', 'messages', 'metadata'];

    protected function casts(): array
    {
        return [
            'messages' => 'array',
            'metadata' => 'array',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function addMessage(string $role, string $content): void
    {
        $messages = $this->messages ?? [];
        $messages[] = ['role' => $role, 'content' => $content, 'at' => now()->toIso8601String()];
        $this->update(['messages' => $messages]);
    }
}
