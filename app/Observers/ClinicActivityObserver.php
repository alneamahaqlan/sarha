<?php

namespace App\Observers;

use App\Services\ClinicActivityLogger;
use App\Support\ActingClinicUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic observer that logs create/update/delete events on catalog
 * models that belong to a clinic. Registered for: Service, Doctor,
 * ClinicPackage, Offer, BeforeAfterPhoto, SubClinic, Article.
 *
 * Skip rules:
 *   - no acting user (seeder / console) → silently skipped
 *   - update with only timestamp changes → skipped (no real edit)
 *   - the action verb is derived from the model's basename, so adding
 *     a new observed model needs no observer changes
 */
class ClinicActivityObserver
{
    public function __construct(
        private readonly ClinicActivityLogger $logger,
    ) {}

    public function created(Model $model): void
    {
        $this->fire($model, 'created');
    }

    public function updated(Model $model): void
    {
        // If the only dirty columns are updated_at/created_at, treat
        // it as a no-op (e.g. Eloquent touched the row from a relation
        // save) so the activity log stays meaningful.
        $real = array_diff(array_keys($model->getDirty()), ['updated_at', 'created_at']);
        if (empty($real)) return;

        $this->fire($model, 'updated', ['fields' => array_values($real)]);
    }

    public function deleted(Model $model): void
    {
        $this->fire($model, 'deleted');
    }

    /**
     * Shared write path. Skips when no acting user (background work).
     */
    private function fire(Model $model, string $verb, array $extra = []): void
    {
        if (! ActingClinicUser::clinicId()) return;

        $base = class_basename($model);
        // Convention: lowercased basename — "Service" → "service.created"
        // matches the lang key namespace the React UI uses.
        $slug = strtolower($base);

        $summary = array_merge([
            'label' => $model->name ?? $model->title ?? ('#' . $model->getKey()),
        ], $extra);

        $this->logger->log("{$slug}.{$verb}", $model, $summary);
    }
}
