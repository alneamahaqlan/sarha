<?php

namespace App\Services\Import;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads an uploaded spreadsheet file (CSV or XLSX) into the SAME mapped-row
 * shape as GoogleSheetReader, so the rest of the import pipeline (service
 * matching, dedup, run recording) is identical regardless of source.
 *
 * XLSX is parsed with PHP's built-in ZipArchive + SimpleXML (an .xlsx is a
 * zip of XML) — no external library. Numbers/text come through as strings;
 * dates as their underlying value. Reads the first worksheet only.
 */
class SpreadsheetFileReader
{
    use MapsTabularRows;

    /**
     * @param  array<string,string>  $columnMap  field key => column letter
     * @return array<int,array{row:int,values:array<string,string>}>
     */
    public function read(string $filePath, array $columnMap, int $rowFrom, int $rowTo): array
    {
        $this->validateRange($rowFrom, $rowTo);

        if (! is_file($filePath)) {
            throw new RuntimeException('import.file_unreadable');
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $lines = match ($ext) {
            'csv', 'txt' => $this->parseCsv((string) file_get_contents($filePath)),
            'xlsx'       => $this->parseXlsx($filePath),
            default      => throw new RuntimeException('import.file_unsupported'),
        };

        if ($lines === []) {
            throw new RuntimeException('import.file_empty');
        }

        return $this->mapLines($lines, $columnMap, $rowFrom, $rowTo);
    }

    /**
     * Parse an .xlsx into 0-based rows of cells, aligned to the 1-based row
     * numbers the user sees in Excel (gaps preserved as empty rows).
     *
     * @return array<int,array<int,string>>
     */
    private function parseXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('import.file_unreadable');
        }

        $shared    = $this->readSharedStrings($zip);
        $sheetName = $this->firstWorksheetName($zip);
        $sheetXml  = $zip->getFromName($sheetName);
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('import.file_empty');
        }

        $ws = $this->loadXmlNoNs($sheetXml);
        if (! $ws || ! isset($ws->sheetData)) {
            throw new RuntimeException('import.file_empty');
        }

        $lines = [];
        foreach ($ws->sheetData->row as $row) {
            $rowNum = (int) $row['r'];
            if ($rowNum < 1) {
                continue;
            }

            $cells = [];
            foreach ($row->c as $c) {
                $colIdx = $this->refToColIndex((string) $c['r']);
                $cells[$colIdx] = $this->cellValue($c, $shared);
            }

            // Densify 0..max so column indices line up with the map.
            $dense = [];
            if ($cells !== []) {
                $max = max(array_keys($cells));
                for ($i = 0; $i <= $max; $i++) {
                    $dense[$i] = $cells[$i] ?? '';
                }
            }
            $lines[$rowNum - 1] = $dense;
        }

        if ($lines === []) {
            return [];
        }

        // Fill any skipped rows so indices are contiguous 0..maxLine.
        $maxLine = max(array_keys($lines));
        for ($i = 0; $i <= $maxLine; $i++) {
            if (! isset($lines[$i])) {
                $lines[$i] = [];
            }
        }
        ksort($lines);

        return array_values($lines);
    }

    /**
     * Resolve a single cell to its string value, dereferencing shared
     * strings (t="s") and inline strings (t="inlineStr").
     *
     * @param  array<int,string>  $shared
     */
    private function cellValue(SimpleXMLElement $c, array $shared): string
    {
        $type = (string) $c['t'];

        if ($type === 's') {
            return $shared[(int) $c->v] ?? '';
        }
        if ($type === 'inlineStr') {
            return isset($c->is) ? $this->concatText($c->is) : '';
        }

        return isset($c->v) ? trim((string) $c->v) : '';
    }

    /**
     * Shared-strings table: each <si> may hold one <t> or several <r><t>
     * rich-text runs — concatenate them all.
     *
     * @return array<int,string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $doc = $this->loadXmlNoNs($xml);
        if (! $doc) {
            return [];
        }

        $out = [];
        foreach ($doc->si as $si) {
            $out[] = $this->concatText($si);
        }

        return $out;
    }

    /** Concatenate every <t> descendant's text. */
    private function concatText(SimpleXMLElement $node): string
    {
        $text = '';
        foreach ($node->xpath('.//t') as $t) {
            $text .= (string) $t;
        }

        return trim($text);
    }

    /** First worksheet entry (sheet1.xml, else the lowest-numbered sheet). */
    private function firstWorksheetName(ZipArchive $zip): string
    {
        if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
            return 'xl/worksheets/sheet1.xml';
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                return $name;
            }
        }
        throw new RuntimeException('import.file_empty');
    }

    /** "B5" → 0-based column index (1 for B), ignoring the row digits. */
    private function refToColIndex(string $ref): int
    {
        $letters = preg_replace('/[0-9]/', '', $ref) ?? '';
        return $letters === '' ? 0 : $this->columnLetterToIndex($letters);
    }

    /** Strip the default namespace so SimpleXML/xpath stay prefix-free. */
    private function loadXmlNoNs(string $xml): ?SimpleXMLElement
    {
        $xml = preg_replace('/\sxmlns="[^"]*"/', '', $xml, 1) ?? $xml;
        $obj = @simplexml_load_string($xml);

        return $obj ?: null;
    }
}
