<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Offer;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * CSV import + AI column analysis for clinic services.
 *
 * Extracted verbatim from Filament's ImportServices page so both call paths
 * (the Filament page and the React/API path) share the same parsing, AI
 * mapping, heuristic fallback, and Service::create loop. No logic change.
 *
 * Also hosts the free-text AI path: extract a draft catalog (services +
 * offers) for on-screen review, then persist only the rows the clinic ticks.
 */
class ImportServicesService
{
    public function __construct(
        private readonly AiContentService $ai,
        private readonly CatalogServiceResolver $catalog,
    ) {}

    /** True when an AI provider is configured — required for the free-text path. */
    public function aiAvailable(): bool
    {
        return $this->ai->isConfigured();
    }

    /**
     * Extract a draft catalog (services + offers) from free-form text via AI,
     * then validate + normalise every row for on-screen review. Nothing is
     * saved here. AI-suggested category ids are filtered to this clinic's
     * active specialties; suggested service ids are filtered to services this
     * clinic actually owns — we never echo back an id the AI hallucinated.
     *
     * @return array{services: array<int,array<string,mixed>>, offers: array<int,array<string,mixed>>}
     */
    public function extractCatalogFromText(int $clinicId, string $text): array
    {
        // Lists handed to the AI as suggestion menus.
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])
            ->all();

        $existingServices = Service::where('clinic_id', $clinicId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Service $s) => ['id' => $s->id, 'name' => $s->name])
            ->all();

        $raw = $this->ai->extractCatalogFromText($text, $categories, $existingServices);

        $validCategoryIds = array_column($categories, 'id');
        $validServiceIds  = array_column($existingServices, 'id');

        return [
            'services' => $this->cleanServices($raw['services'] ?? [], $validCategoryIds),
            'offers'   => $this->cleanOffers($raw['offers'] ?? [], $validServiceIds),
        ];
    }

    /**
     * Persist the rows the clinic confirmed. Runs in a single transaction so a
     * mid-list failure rolls back cleanly. Offers default to a [now, +30d]
     * window unless the caller supplied an explicit ends_at.
     *
     * @param array<int,array<string,mixed>> $services
     * @param array<int,array<string,mixed>> $offers
     * @return array{services_created:int, offers_created:int}
     */
    public function confirmCatalog(int $clinicId, array $services, array $offers): array
    {
        $validServiceIds = Service::where('clinic_id', $clinicId)->pluck('id')->all();
        $validCategoryIds = Category::where('is_active', true)->pluck('id')->all();

        return DB::transaction(function () use ($clinicId, $services, $offers, $validServiceIds, $validCategoryIds) {
            $servicesCreated = 0;
            foreach ($services as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $categoryIds = array_values(array_intersect(
                    array_map('intval', (array) ($row['category_ids'] ?? [])),
                    $validCategoryIds,
                ));

                // Same catalog rule as the manual form: link to a canonical
                // service (→ approved) or file a pending request (→ hidden).
                $resolution = $this->catalog->resolveForCreate(
                    clinicId: $clinicId,
                    name: $name,
                    categoryIds: $categoryIds,
                );

                $service = Service::create([
                    'clinic_id'          => $clinicId,
                    'catalog_service_id' => $resolution['catalog_service_id'],
                    'name'               => $name,
                    'description'        => $this->nullableString($row['description'] ?? null),
                    'price'              => $this->cleanPrice($row['price'] ?? null) ?? 0,
                    'is_active'          => true,
                    'approval_status'    => $resolution['approval_status'],
                ]);

                if ($categoryIds) {
                    $service->categories()->sync($categoryIds);
                }
                $servicesCreated++;
            }

            $offersCreated = 0;
            foreach ($offers as $row) {
                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                // Only honour a service link the clinic actually owns; anything
                // else falls back to a general (clinic-wide) promo.
                $serviceId = isset($row['service_id']) ? (int) $row['service_id'] : 0;
                $linked = $serviceId && in_array($serviceId, $validServiceIds, true);

                $endsAt = ! empty($row['ends_at'])
                    ? \Illuminate\Support\Carbon::parse($row['ends_at'])
                    : now()->addDays(30);

                Offer::create([
                    'clinic_id'   => $clinicId,
                    'service_id'  => $linked ? $serviceId : null,
                    'type'        => $linked ? Offer::TYPE_SERVICE : Offer::TYPE_GENERAL,
                    'title'       => $title,
                    'description' => $this->nullableString($row['description'] ?? null),
                    'old_price'   => $this->cleanPrice($row['old_price'] ?? null),
                    'price'       => $this->cleanPrice($row['price'] ?? null),
                    'starts_at'   => now(),
                    'ends_at'     => $endsAt,
                    'is_active'   => true,
                ]);
                $offersCreated++;
            }

            return ['services_created' => $servicesCreated, 'offers_created' => $offersCreated];
        });
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,int> $validCategoryIds
     * @return array<int,array<string,mixed>>
     */
    private function cleanServices(array $rows, array $validCategoryIds): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($name === '') {
                continue;
            }

            $categoryIds = array_values(array_intersect(
                array_map('intval', (array) ($row['category_ids'] ?? [])),
                $validCategoryIds,
            ));

            $clean[] = [
                'name'         => $name,
                'description'  => $this->nullableString($row['description'] ?? null),
                'price'        => $this->cleanPrice($row['price'] ?? null),
                'category_ids' => $categoryIds,
            ];
        }

        return $clean;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,int> $validServiceIds
     * @return array<int,array<string,mixed>>
     */
    private function cleanOffers(array $rows, array $validServiceIds): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $title = isset($row['title']) ? trim((string) $row['title']) : '';
            if ($title === '') {
                continue;
            }

            $serviceId = isset($row['service_id']) ? (int) $row['service_id'] : 0;
            $serviceId = $serviceId && in_array($serviceId, $validServiceIds, true) ? $serviceId : null;

            $clean[] = [
                'title'       => $title,
                'description' => $this->nullableString($row['description'] ?? null),
                'old_price'   => $this->cleanPrice($row['old_price'] ?? null),
                'price'       => $this->cleanPrice($row['price'] ?? null),
                'service_id'  => $serviceId,
                'type'        => $serviceId ? Offer::TYPE_SERVICE : Offer::TYPE_GENERAL,
            ];
        }

        return $clean;
    }

    /** Strip currency symbols / separators → float, or null when absent. */
    private function cleanPrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $stripped = preg_replace('/[^\d.]/', '', (string) $value);

        return ($stripped === '' || $stripped === '.') ? null : (float) $stripped;
    }

    /** Trim → non-empty string, or null. */
    private function nullableString(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));

        return $s === '' ? null : $s;
    }

    /**
     * Parse a CSV file into headers + rows arrays.
     *
     * @return array{headers: array<int,string>, rows: array<int,array<int,string>>}
     */
    public function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map('trim', $row);
        }
        fclose($handle);

        if (count($rows) < 2) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_shift($rows);

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Run AI column analysis when available, otherwise return heuristic mappings.
     *
     * @return array<int,array{column:string, mapped_to:?string, confidence:int, reason:string}>
     */
    public function analyzeColumns(array $headers, array $sampleRows): array
    {
        if ($this->ai->isConfigured()) {
            try {
                return $this->ai->analyzeExcelColumns($headers, array_slice($sampleRows, 0, 3));
            } catch (\Throwable) {
                // Fall through to heuristic.
            }
        }

        return $this->heuristicMatch($headers);
    }

    /**
     * Import rows into Service records using a confirmed mapping.
     *
     * @param array<int,string> $headers     Original CSV headers, indexed 0..N.
     * @param array<int,array<int,string>> $rows  Data rows (header excluded).
     * @param array<int,string|null> $mapping  Map of headerIndex => modelField.
     * @return int  Number of services created.
     */
    public function importRows(int $clinicId, array $headers, array $rows, array $mapping): int
    {
        $imported = 0;

        foreach ($rows as $row) {
            $service = ['clinic_id' => $clinicId, 'is_active' => true];
            foreach ($mapping as $colIdx => $field) {
                if ($field === null || $field === '') continue;
                $service[$field] = $row[$colIdx] ?? null;
            }

            if (empty($service['name'])) continue;

            if (! empty($service['price'])) {
                $service['price'] = (float) preg_replace('/[^\d.]/', '', $service['price']);
            }

            Service::create($service);
            $imported++;
        }

        // Silence the unused-headers param: kept in signature for future column-mapping use cases.
        unset($headers);

        return $imported;
    }

    /**
     * Heuristic header → field matcher — verbatim from ImportServices page.
     *
     * @return array<int,array{column:string, mapped_to:?string, confidence:int, reason:string}>
     */
    public function heuristicMatch(array $headers): array
    {
        $rules = [
            'name'        => ['/name|اسم/iu'],
            'price'       => ['/^price|سعر$|السعر/iu'],
            'description' => ['/desc|وصف|تفاصيل/iu'],
        ];

        return array_map(function ($col) use ($rules) {
            foreach ($rules as $field => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $col)) {
                        return [
                            'column'     => $col,
                            'mapped_to'  => $field,
                            'confidence' => 70,
                            'reason'     => 'Heuristic match',
                        ];
                    }
                }
            }
            return ['column' => $col, 'mapped_to' => null, 'confidence' => 0, 'reason' => 'No match'];
        }, $headers);
    }
}
