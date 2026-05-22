<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PlatformNotification extends Model
{
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'notifiable_type', 'notifiable_id',
        'type', 'icon', 'url', 'priority',
        'title', 'body', 'data', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data'    => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function markAsRead(): void
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeForRecipient(Builder $query, Model $recipient): Builder
    {
        return $query
            ->where('notifiable_type', $recipient::class)
            ->where('notifiable_id', $recipient->getKey());
    }
}
