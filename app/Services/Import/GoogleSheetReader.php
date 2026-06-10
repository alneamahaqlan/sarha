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
    use MapsTabularRows;

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
        $this->validateRange($rowFrom, $rowTo);

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

        return $this->mapLines($lines, $columnMap, $rowFrom, $rowTo);
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
}
