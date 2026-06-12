<?php

namespace App\Services\Import;

use RuntimeException;

/**
 * Shared column-letter mapping + row-range slicing for any tabular import
 * source (Google Sheet CSV, uploaded CSV, uploaded XLSX). Keeps the exact
 * same semantics — column letters (A, B, … AA) and 1-based row numbers the
 * user sees in their spreadsheet — across every reader.
 */
trait MapsTabularRows
{
    /** Hard cap on rows pulled in a single import (synchronous). */
    public static int $maxRows = 1000;

    protected function validateRange(int $rowFrom, int $rowTo): void
    {
        if ($rowFrom < 1 || $rowTo < $rowFrom) {
            throw new RuntimeException('import.invalid_range');
        }
        if (($rowTo - $rowFrom + 1) > static::$maxRows) {
            throw new RuntimeException('import.range_too_large');
        }
    }

    /**
     * Slice parsed lines to the requested 1-based row range and project
     * each row's cells onto the field keys via the column map.
     *
     * @param  array<int,array<int,string>>  $lines  0-based rows of cells
     * @param  array<string,string>  $columnMap  field key => column letter
     * @return array<int,array{row:int,values:array<string,string>}>
     */
    protected function mapLines(array $lines, array $columnMap, int $rowFrom, int $rowTo): array
    {
        // Resolve each field's column letter to a 0-based index once.
        $indexMap = [];
        foreach ($columnMap as $field => $letter) {
            if (is_string($letter) && trim($letter) !== '') {
                $indexMap[$field] = $this->columnLetterToIndex(trim($letter));
            }
        }

        $out = [];
        // Sheet row N is at $lines[N-1]; clamp to what the file actually has.
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
     * Parse CSV text into rows of cells via a temp stream so quoted
     * commas/newlines inside fields are handled.
     *
     * @return array<int,array<int,string>>
     */
    protected function parseCsv(string $body): array
    {
        // Strip a UTF-8 BOM that exporters sometimes prepend.
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body;

        $rows = [];
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $body);
        rewind($stream);
        while (($cells = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
            // fgetcsv yields [null] for a truly blank line — keep it as an
            // empty row so row numbers stay aligned with the file.
            $rows[] = ($cells === [null]) ? [] : $cells;
        }
        fclose($stream);

        return $rows;
    }
}
