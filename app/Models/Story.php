<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An Instagram-style clinic "story" — a full-bleed promotional image with
 * an optional caption, shown as a tappable ring around the clinic's logo on
 * its public page. Optionally auto-expires via `ends_at`.
 */
class Story extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'clinic_stories';

    protected $fillable = [
        'clinic_id',
        'image',
        'caption',
        'ends_at',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ends_at'   => 'datetime',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Live stories: published and not past their (optional) expiry. Used by
     * the public page; the clinic panel sees all of them.
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
