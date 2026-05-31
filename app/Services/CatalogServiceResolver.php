<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\CatalogService;
use App\Models\Category;
use App\Models\PlatformNotification;
use Illuminate\Support\Str;

/**
 * Decides how a newly-created clinic service connects to the unified catalog:
 *
 *   1. explicit catalog_service_id from the picker  → link, approved
 *   2. auto-matched to an active catalog entry       → link, approved
 *   3. no match                                      → create a PENDING
 *      catalog entry (the "request"), link to it, mark the service pending,
 *      and notify admins — same review pattern as CategoryRequest.
 *
 * Returns the catalog_service_id + approval_status the caller writes onto the
 * Service row. Keeping this here (not in the controller) means the import
 * confirm path and the manual form share one rule.
 */
class CatalogServiceResolver
{
    public function __construct(private readonly CatalogMatchService $matcher) {}

    /**
     * @param  array<int,int>  $categoryIds  Specialties chosen for the service
     *                                        (the first seeds a new catalog entry's category).
     * @return array{catalog_service_id:int, approval_status:string}
     */
    public function resolveForCreate(
        int $clinicId,
        string $name,
        ?int $explicitCatalogServiceId = null,
        array $categoryIds = [],
    ): array {
        // 1. Picker supplied an explicit active catalog entry.
        if ($explicitCatalogServiceId) {
            $chosen = CatalogService::active()->find($explicitCatalogServiceId);
            if ($chosen) {
                return [
                    'catalog_service_id' => $chosen->id,
                    'approval_status'    => \App\Models\Service::APPROVAL_APPROVED,
                ];
            }
            // Fall through to matching if the id was stale/inactive.
        }

        // 2. Try to auto-match by name.
        $match = $this->matcher->match($name);
        if ($match) {
            return [
                'catalog_service_id' => $match->id,
                'approval_status'    => \App\Models\Service::APPROVAL_APPROVED,
            ];
        }

        // 3. No match → create a pending catalog entry (the request) + notify.
        $pending = $this->createPendingRequest($clinicId, $name, $categoryIds[0] ?? null);

        return [
            'catalog_service_id' => $pending->id,
            'approval_status'    => \App\Models\Service::APPROVAL_PENDING,
        ];
    }

    /**
     * Create a pending catalog entry and alert every active admin. Reuses an
     * existing pending row for the same normalised name + clinic so a clinic
     * re-submitting the same service doesn't spam the review queue.
     */
    private function createPendingRequest(int $clinicId, string $name, ?int $categoryId): CatalogService
    {
        $name = trim($name);

        $existing = CatalogService::pending()
            ->where('requested_by_clinic_id', $clinicId)
            ->get(['id', 'name'])
            ->first(fn (CatalogService $c) => $this->matcher->normalize($c->name) === $this->matcher->normalize($name));
        if ($existing) {
            return $existing;
        }

        if ($categoryId && ! Category::where('id', $categoryId)->where('is_active', true)->exists()) {
            $categoryId = null;
        }

        $catalog = CatalogService::create([
            'name'                   => $name,
            'slug'                   => $this->uniqueSlug($name),
            'category_id'            => $categoryId,
            'status'                 => CatalogService::STATUS_PENDING,
            'requested_by_clinic_id' => $clinicId,
        ]);

        $clinicName = optional(auth('clinic')->user())->name ?? '—';
        foreach (Admin::where('is_active', true)->get(['id']) as $admin) {
            PlatformNotification::create([
                'notifiable_type' => Admin::class,
                'notifiable_id'   => $admin->id,
                'type'            => 'catalog_service_request.new',
                'icon'            => 'heroicon-o-squares-plus',
                'url'             => '/app/admin/catalog-services',
                'priority'        => 'normal',
                'title'           => 'طلب خدمة جديدة للكتالوغ',
                'body'            => "{$clinicName} اقترح خدمة: {$catalog->name}",
                'data'            => ['catalog_service_id' => $catalog->id],
            ]);
        }

        return $catalog;
    }

    /** Slug unique within catalog_services; falls back to a random suffix. */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $i = 2;
        while (CatalogService::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
