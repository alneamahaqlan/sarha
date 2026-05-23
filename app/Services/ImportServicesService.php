<?php

namespace App\Services;

use App\Models\Service;

/**
 * CSV import + AI column analysis for clinic services.
 *
 * Extracted verbatim from Filament's ImportServices page so both call paths
 * (the Filament page and the React/API path) share the same parsing, AI
 * mapping, heuristic fallback, and Service::create loop. No logic change.
 */
class ImportServicesService
{
    public function __construct(private readonly AiContentService $ai) {}

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

            foreach (['price', 'old_price'] as $priceField) {
                if (! empty($service[$priceField])) {
                    $service[$priceField] = (float) preg_replace('/[^\d.]/', '', $service[$priceField]);
                }
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
            'old_price'   => ['/old|قديم|قبل/iu'],
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
