<?php

namespace App\Services\Import;

use App\Models\Booking;
use App\Models\ClinicImportRun;
use App\Models\ClinicImportSource;
use App\Models\Service;
use App\Services\CatalogMatchService;
use App\Services\CatalogServiceResolver;
use App\Support\ActingClinicUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates a sheet import for one clinic: re-reads the sheet, resolves
 * every service name to a clinic Service id (auto-matched / mapped / newly
 * created as pending+inactive), creates the bookings (the Customer link +
 * dedup is handled downstream by BookingCustomerLinkObserver), and records
 * the run. Reads are done here too so the commit never trusts client rows.
 */
class BookingImportService
{
    public function __construct(
        private readonly GoogleSheetReader $reader,
        private readonly ServiceMatcher $matcher,
        private readonly CatalogMatchService $catalog,
        private readonly CatalogServiceResolver $catalogResolver,
    ) {}

    /**
     * Dry-run: mapped rows + per-service-name match analysis.
     *
     * @return array{rows:array<int,array<string,mixed>>, services:array<int,mixed>, total:int}
     */
    public function preview(int $clinicId, string $sheetUrl, array $columnMap, int $rowFrom, int $rowTo): array
    {
        $rows = $this->reader->read($sheetUrl, $columnMap, $rowFrom, $rowTo);

        $serviceNames = collect($rows)
            ->map(fn ($r) => $r['values']['service'] ?? '')
            ->all();

        return [
            'rows'     => collect($rows)->map(fn ($r) => array_merge(['row' => $r['row']], $r['values']))->all(),
            'services' => $this->matcher->analyze($clinicId, $serviceNames),
            'total'    => count($rows),
        ];
    }

    /**
     * Execute the import. Throws ValidationException listing any service
     * name that is neither auto-matched nor resolved by the caller.
     *
     * @param  array<int,array{name:string,action:string,service_id?:int,sub_clinic_id?:int,category_ids?:array<int,int>}>  $resolutions
     * @return array{rows_imported:int, rows_skipped:int, run_id:int, source_id:?int}
     */
    public function commit(
        int $clinicId,
        string $sheetUrl,
        array $columnMap,
        int $rowFrom,
        int $rowTo,
        array $defaults,
        array $resolutions = [],
        bool $saveSource = false,
        ?string $sourceName = null,
        ?int $importSourceId = null,
    ): array {
        $rows = $this->reader->read($sheetUrl, $columnMap, $rowFrom, $rowTo);

        // 1. Resolve service names → service_id (or null for empty cells).
        $serviceMap = $this->buildServiceMap($clinicId, $rows, $resolutions);

        // 2. Create bookings + run inside one transaction.
        $imported = 0;
        $skipped  = 0;

        $result = DB::transaction(function () use (
            $rows, $serviceMap, $clinicId, $defaults, $sheetUrl, $columnMap,
            $rowFrom, $rowTo, $resolutions, $saveSource, $sourceName, $importSourceId, &$imported, &$skipped
        ) {
            foreach ($rows as $r) {
                $v = $r['values'];
                $name  = trim((string) ($v['customer_name'] ?? ''));
                $phone = trim((string) ($v['customer_phone'] ?? ''));

                // A row without a name or phone is unusable — skip, don't fail.
                if ($name === '' || $phone === '') {
                    $skipped++;
                    continue;
                }

                $serviceCell = trim((string) ($v['service'] ?? ''));
                $serviceId = $serviceCell === ''
                    ? null
                    : ($serviceMap[$this->catalog->normalize($serviceCell)] ?? null);

                $payload = [
                    'clinic_id'          => $clinicId,
                    'customer_name'      => mb_substr($name, 0, 120),
                    'customer_phone'     => mb_substr($phone, 0, 32),
                    'service_id'         => $serviceId,
                    'appointment_at'     => $this->parseDate($v['appointment_at'] ?? null),
                    'clinic_notes'       => $this->trimOrNull($v['notes'] ?? null, 1000),
                    'status'             => $defaults['status'] ?? 'new',
                    'source'             => 'import',
                    'acquisition_source' => $defaults['acquisition_source'] ?? 'other',
                ];

                if (! empty($defaults['assignee_type']) && ! empty($defaults['assignee_id'])) {
                    $payload['assignee_type'] = $defaults['assignee_type'] === 'Clinic'
                        ? \App\Models\Clinic::class
                        : \App\Models\ClinicTeamMember::class;
                    $payload['assignee_id'] = (int) $defaults['assignee_id'];
                }

                Booking::create($payload);
                $imported++;
            }

            // 3. Persist / update the saved source (for re-pulls).
            $source = $this->persistSource(
                $clinicId, $saveSource, $sourceName, $importSourceId,
                $sheetUrl, $columnMap, $defaults, $resolutions
            );

            // 4. Record the run (its row range powers overlap detection).
            $run = ClinicImportRun::create([
                'import_source_id' => $source?->id,
                'clinic_id'        => $clinicId,
                'row_from'         => $rowFrom,
                'row_to'           => $rowTo,
                'rows_imported'    => $imported,
                'rows_skipped'     => $skipped,
                'created_by_type'  => ActingClinicUser::actorType(),
                'created_by_id'    => ActingClinicUser::actorId(),
                'created_by_name'  => ActingClinicUser::actorName(),
            ]);

            $source?->update(['last_imported_at' => $run->created_at]);

            return ['run_id' => $run->id, 'source_id' => $source?->id];
        });

        return array_merge($result, ['rows_imported' => $imported, 'rows_skipped' => $skipped]);
    }

    /**
     * Map every distinct non-empty service name in the sheet to a clinic
     * service id. Auto-matches first; then applies caller resolutions
     * (map → existing id, create → new pending+inactive service). Any name
     * left unresolved aborts the whole import.
     *
     * @return array<string,int>  normalized name => service_id
     */
    private function buildServiceMap(int $clinicId, array $rows, array $resolutions): array
    {
        $names = collect($rows)->map(fn ($r) => trim((string) ($r['values']['service'] ?? '')))->filter()->unique()->values();
        if ($names->isEmpty()) {
            return [];
        }

        $analysis = collect($this->matcher->analyze($clinicId, $names->all()))->keyBy('name');

        // Index caller resolutions by normalized name for tolerant matching.
        $byName = collect($resolutions)->keyBy(fn ($r) => $this->catalog->normalize((string) $r['name']));

        $map = [];
        $unresolved = [];

        foreach ($names as $rawName) {
            $norm = $this->catalog->normalize($rawName);
            $entry = $analysis->get($rawName);

            // Auto-matched exactly → use it directly.
            if ($entry && $entry['status'] === 'matched') {
                $map[$norm] = $entry['service']['id'];
                continue;
            }

            $res = $byName->get($norm);
            if (! $res) {
                $unresolved[] = $rawName;
                continue;
            }

            if (($res['action'] ?? null) === 'map') {
                $map[$norm] = (int) $res['service_id'];
            } elseif (($res['action'] ?? null) === 'create') {
                $map[$norm] = $this->createService($clinicId, $rawName, (int) $res['sub_clinic_id'], $res['category_ids'] ?? [])->id;
            } else {
                $unresolved[] = $rawName;
            }
        }

        if ($unresolved !== []) {
            throw ValidationException::withMessages([
                'service_resolutions' => 'خدمات غير محلولة: ' . implode('، ', $unresolved),
            ]);
        }

        return $map;
    }

    /**
     * Create a clinic service from an imported name. Always inactive
     * (is_active=false) so it stays hidden until the clinic completes its
     * data and turns it on; catalog linkage/approval follows the shared
     * resolver rule. Stamps the import provenance into the description.
     */
    private function createService(int $clinicId, string $name, int $subClinicId, array $categoryIds): Service
    {
        // Inherit the sub-clinic's specialty when the caller didn't pick one,
        // so every service still carries at least one category.
        if ($categoryIds === []) {
            $catId = \App\Models\SubClinic::where('id', $subClinicId)->value('category_id');
            if ($catId) {
                $categoryIds = [(int) $catId];
            }
        }

        $resolution = $this->catalogResolver->resolveForCreate(
            clinicId: $clinicId,
            name: $name,
            explicitCatalogServiceId: null,
            categoryIds: $categoryIds,
        );

        $service = Service::create([
            'clinic_id'          => $clinicId,
            'sub_clinic_id'      => $subClinicId,
            'catalog_service_id' => $resolution['catalog_service_id'],
            'approval_status'    => $resolution['approval_status'],
            'name'               => $name,
            'description'        => 'أُضيفت عبر استيراد Google Sheet — يُرجى مراجعة الشيت والتأكد من أنها خدمة فريدة أو تنفيذ بحث واستبدال، ثم إكمال السعر والتفاصيل وتفعيلها.',
            'price'              => 0,
            'is_active'          => false,
        ]);

        if ($categoryIds !== []) {
            $service->categories()->sync($categoryIds);
        }

        return $service;
    }

    /**
     * Create or update the saved source. When re-pulling an existing source
     * (importSourceId) we refresh its mapping/defaults regardless of the
     * save flag; otherwise we only persist when the clinic opted to save.
     */
    private function persistSource(
        int $clinicId, bool $saveSource, ?string $sourceName, ?int $importSourceId,
        string $sheetUrl, array $columnMap, array $defaults, array $resolutions
    ): ?ClinicImportSource {
        // Remember the create/map decisions so the clinic isn't re-asked.
        $aliases = collect($resolutions)
            ->mapWithKeys(fn ($r) => [(string) $r['name'] => collect($r)->only(['action', 'service_id', 'sub_clinic_id'])->all()])
            ->all();

        if ($importSourceId) {
            $source = ClinicImportSource::where('clinic_id', $clinicId)->find($importSourceId);
            if ($source) {
                $source->update([
                    'sheet_url'       => $sheetUrl,
                    'column_map'      => $columnMap,
                    'defaults'        => $defaults,
                    'service_aliases' => array_merge((array) $source->service_aliases, $aliases),
                ]);
                return $source;
            }
        }

        if (! $saveSource) {
            return null;
        }

        return ClinicImportSource::create([
            'clinic_id'       => $clinicId,
            'name'            => $sourceName ?: 'مصدر بدون اسم',
            'sheet_url'       => $sheetUrl,
            'column_map'      => $columnMap,
            'defaults'        => $defaults,
            'service_aliases' => $aliases,
        ]);
    }

    private function parseDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function trimOrNull(?string $value, int $max): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
