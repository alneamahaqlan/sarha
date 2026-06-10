<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Applied to catalogue/content models listed in config/demo_seed.php → hideable.
 *
 * Adds a global scope that hides soft-hidden demo rows (demo_hidden_at not
 * null) from EVERY query across the platform — so the Seeder Center can make
 * a whole batch of demo content vanish instantly, then bring it back with the
 * same data (no reseed). Real rows (demo_hidden_at = null) are unaffected.
 *
 * Escape hatches mirror SoftDeletes ergonomics:
 *   Model::withHiddenDemo()  — include hidden demo rows
 *   Model::onlyHiddenDemo()  — only hidden demo rows (admin inventory)
 */
trait HasDemoData
{
    public static function bootHasDemoData(): void
    {
        static::addGlobalScope('demo_hidden', function (Builder $builder): void {
            $builder->whereNull($builder->getModel()->getTable() . '.demo_hidden_at');
        });
    }

    public function initializeHasDemoData(): void
    {
        if (! isset($this->casts['is_demo'])) {
            $this->casts['is_demo'] = 'boolean';
        }
        if (! isset($this->casts['demo_hidden_at'])) {
            $this->casts['demo_hidden_at'] = 'datetime';
        }
    }

    /** Include rows that are currently soft-hidden by the Seeder Center. */
    public function scopeWithHiddenDemo(Builder $query): Builder
    {
        return $query->withoutGlobalScope('demo_hidden');
    }

    /** Only the rows that are currently soft-hidden (admin inventory view). */
    public function scopeOnlyHiddenDemo(Builder $query): Builder
    {
        return $query->withoutGlobalScope('demo_hidden')
            ->whereNotNull($this->getTable() . '.demo_hidden_at');
    }
}
