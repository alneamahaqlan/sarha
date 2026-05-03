<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformNotification extends Model
{
    protected $fillable = [
        'notifiable_type', 'notifiable_id', 'type',
        'title', 'body', 'data', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }
}
