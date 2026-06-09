<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads a PUBLIC Google Sheet (no OAuth) by turning its share URL into the
 * CSV export endpoint and fetching it. Maps columns by spreadsheet letter
 * (A, B, …, AA) onto our field keys and slices to a 1-based row range that
 * matches the row numbers the user sees in the sheet.
 *
 * Intentionally dumb: any auth/format failure surfaces as a RuntimeException
 * with an Arabic-ready message key the controller translates.
 */
class GoogleSheetReader
{
    /** Hard cap on rows pulled in a single import (synchronous). */
    public const MAX_ROWS = 1000;

    /**
     * Pull mapped rows from the sheet.
     *
     * @param  array<string,string>  $columnMap  field key => column letter, e.g. ['customer_name' => 'B']
     * @param  int  $rowFrom  first sheet row to read (1-based, inclusive)
     * @param  int  $rowTo    last sheet row to read (1-based, inclusive)
     * @return array<int,array{row:int,values:array<string,string>}>
     */
    public function read(string $sheetUrl, array $columnMap, int $rowFrom, int $rowTo): array
    {
        if ($rowFrom < 1 || $rowTo < $rowFrom) {
            throw new RuntimeException('import.invalid_range');
        }
        if (($rowTo - $rowFrom + 1) > self::MAX_ROWS) {
            throw new RuntimeException('import.range_too_large');
        }

        $csvUrl = $this->toCsvExportUrl($sheetUrl);

        $response = Http::timeout(20)->get($csvUrl);
        if (! $response->ok()) {
            // 401/403 → sheet isn't public; anything else → bad link/format.
            throw new RuntimeException(
                in_array($response->status(), [401, 403], true) ? 'import.sheet_not_public' : 'import.sheet_unreadable'
            );
        }

        $lines = $this->parseCsv($response->body());
        if ($lines === []) {
            throw new RuntimeException('import.sheet_empty');
        }

        // Resolve each field's column letter to a 0-based index once.
        $indexMap = [];
        foreach ($columnMap as $field => $letter) {
            if (is_string($letter) && trim($letter) !== '') {
                $indexMap[$field] = $this->columnLetterToIndex(trim($letter));
            }
        }

        $out = [];
        // Sheet row N is at $lines[N-1]; clamp to what the sheet actually has.
        $lastRow = min($rowTo, count($lines));
        for ($row = $rowFrom; $row <= $lastRow; $row++) {
            $cells = $lines[$row - 1];
            $values = [];
            foreach ($indexMap as $field => $idx) {
                $values[$field] = isset($cells[$idx]) ? trim((string) $cells[$idx]) : '';
            }
            $out[] = ['row' => $row, 'values' => $values];
        }

        return $out;
    }

    /**
     * Accepts the usual share URL forms and returns the CSV export endpoint:
     *   …/spreadsheets/d/<ID>/edit#gid=<GID>  →  …/spreadsheets/d/<ID>/export?format=csv&gid=<GID>
     */
    public function toCsvExportUrl(string $url): string
    {
        if (! preg_match('#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $url, $m)) {
            throw new RuntimeException('import.invalid_url');
        }
        $id = $m[1];

        $gid = '0';
        if (preg_match('~[#&?]gid=([0-9]+)~', $url, $g)) {
            $gid = $g[1];
        }

        return "https://docs.google.com/spreadsheets/d/{$id}/export?format=csv&gid={$gid}";
    }

    /**
     * Spreadsheet column letter → 0-based index. A→0, B→1, Z→25, AA→26.
     */
    public function columnLetterToIndex(string $letter): int
    {
        $letter = strtoupper(preg_replace('/[^A-Za-z]/', '', $letter) ?? '');
        if ($letter === '') {
            throw new RuntimeException('import.invalid_column');
        }
        $index = 0;
        foreach (str_split($letter) as $ch) {
            $index = $index * 26 + (ord($ch) - ord('A') + 1);
        }

        return $index - 1;
    }

    /**
     * Parse CSV text into rows of cells. Uses PHP's str_getcsv per line via a
     * temp stream so quoted commas/newlines inside fields are handled.
     *
     * @return array<int,array<int,string>>
     */
    private function parseCsv(string $body): array
    {
        // Strip a UTF-8 BOM that Google sometimes prepends.
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body;

        $rows = [];
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $body);
        rewind($stream);
        while (($cells = fgetcsv($stream)) !== false) {
            // fgetcsv yields [null] for a truly blank line — keep it as empty
            // row so row numbers stay aligned with the sheet.
            $rows[] = ($cells === [null]) ? [] : $cells;
        }
        fclose($stream);

        return $rows;
    }
}
