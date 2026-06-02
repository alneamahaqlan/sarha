<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams an Eloquent query out as a UTF-8 CSV (with BOM, so Excel
 * renders Arabic correctly). Chunked so large result sets never load
 * fully into memory.
 */
class CsvExport
{
    /**
     * @param  array<int, string>  $headers   Column header cells.
     * @param  \Closure(\Illuminate\Database\Eloquent\Model): array<int, string|int|null>  $mapper  Maps a row to its CSV cells.
     */
    public static function streamQuery(string $filename, array $headers, Builder $query, \Closure $mapper): StreamedResponse
    {
        return response()->stream(function () use ($headers, $query, $mapper) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM — without it Excel mojibakes Arabic text.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            $query->chunk(500, function ($rows) use ($out, $mapper) {
                foreach ($rows as $row) {
                    fputcsv($out, $mapper($row));
                }
            });

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
