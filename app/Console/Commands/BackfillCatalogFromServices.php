<?php

namespace App\Console\Commands;

use App\Models\CatalogService;
use App\Models\Service;
use App\Services\CatalogMatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Phase 4 of the unified-service-catalog rollout: seed the canonical
 * `catalog_services` table from the distinct service names clinics already
 * created, and link every existing service to its catalog entry.
 *
 * After this runs the catalog typeahead has real options, so clinics pick an
 * existing canonical name (→ published instantly) instead of every service
 * spawning a pending request. Idempotent — only touches unlinked services.
 */
class BackfillCatalogFromServices extends Command
{
    protected $signature = 'catalog:backfill-from-services {--dry-run : Show what would change without writing}';

    protected $description = 'Seed the unified service catalog from existing clinic services and link them (Phase 4).';

    public function __construct(private readonly CatalogMatchService $matcher)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $services = Service::with('categories:id')
            ->whereNull('catalog_service_id')
            ->get();

        if ($services->isEmpty()) {
            $this->info('No unlinked services to backfill — catalog is already in sync.');

            return self::SUCCESS;
        }

        // Group unlinked services by their folded name so spelling/diacritic
        // variants of the same concept collapse into one catalog entry.
        $groups = $services->groupBy(fn (Service $s) => $this->matcher->normalize((string) $s->name));

        $created = 0;
        $reused = 0;
        $linked = 0;

        foreach ($groups as $norm => $group) {
            if ($norm === '') {
                continue; // skip blank/garbage names
            }

            // Display name = the most frequently used original spelling.
            $displayName = $group
                ->groupBy(fn (Service $s) => trim((string) $s->name))
                ->sortByDesc(fn ($g) => $g->count())
                ->keys()
                ->first() ?: trim((string) $group->first()->name);

            // Reuse an existing active catalog entry if one already matches.
            $catalog = $this->matcher->match($displayName);

            if (! $catalog) {
                $categoryId = $group
                    ->firstWhere(fn (Service $s) => $s->categories->isNotEmpty())
                    ?->categories->first()?->id;

                if ($dry) {
                    $this->line("  + create catalog: {$displayName}  ({$group->count()} services)");
                    $created++;
                    continue;
                }

                $catalog = CatalogService::create([
                    'name'        => $displayName,
                    'slug'        => $this->uniqueSlug($displayName),
                    'category_id' => $categoryId,
                    'status'      => CatalogService::STATUS_ACTIVE,
                ]);
                $created++;
            } else {
                $reused++;
                if ($dry) {
                    $this->line("  ~ link to existing: {$catalog->name}  ({$group->count()} services)");
                    continue;
                }
            }

            $ids = $group->pluck('id');
            Service::whereIn('id', $ids)->update([
                'catalog_service_id' => $catalog->id,
                'approval_status'    => Service::APPROVAL_APPROVED,
            ]);
            $linked += $ids->count();
        }

        $this->info(($dry ? '[DRY RUN] ' : '')
            ."Catalog entries created: {$created}, reused: {$reused}, services linked: {$linked}");

        return self::SUCCESS;
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
