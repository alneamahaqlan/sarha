<?php

namespace App\Services;

use App\Models\City;
use App\Models\Clinic;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Import clinics from a PUBLIC Google Sheet via its CSV export URL
 * (https://docs.google.com/spreadsheets/d/{ID}/export?format=csv&gid={GID}).
 *
 * No SDK, no API key — relies on the sheet being shared "anyone with the link".
 * Conservative + null-safe: rows are matched on phone or email and upserted.
 */
class GoogleSheetsImportService
{
    /**
     * Convert any Google Sheets URL into its CSV-export form.
     * Returns null when the URL is not a recognizable Sheets URL.
     */
    public function toCsvUrl(string $url): ?string
    {
        if (! preg_match('#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $url, $m)) {
            return null;
        }
        $id = $m[1];

        // gid may live in the path (#gid=) or query (?gid=); default to 0.
        $gid = '0';
        if (preg_match('/[#&?]gid=([0-9]+)/', $url, $g)) {
            $gid = $g[1];
        }

        return "https://docs.google.com/spreadsheets/d/{$id}/export?format=csv&gid={$gid}";
    }

    /**
     * Fetch + parse + upsert. Returns a summary.
     *
     * @return array{created:int, updated:int, skipped:int}
     */
    public function import(string $url): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $csvUrl = $this->toCsvUrl($url);
        if ($csvUrl === null) {
            return $summary;
        }

        $response = Http::timeout(30)->get($csvUrl);
        if (! $response->successful()) {
            return $summary;
        }

        $rows = $this->parseCsv($response->body());
        if (count($rows) < 2) {
            return $summary;
        }

        $headers = array_map(fn ($h) => Str::lower(trim((string) $h)), array_shift($rows));
        $map = $this->columnIndexes($headers);

        // Require at least a name column to do anything useful.
        if (! isset($map['name'])) {
            return $summary;
        }

        foreach ($rows as $row) {
            $name  = $this->cell($row, $map, 'name');
            $phone = $this->normalizePhone($this->cell($row, $map, 'phone'));
            $email = $this->cell($row, $map, 'email');
            $email = $email !== null ? Str::lower($email) : null;

            // Need a name AND a unique key (phone or email) to upsert safely.
            if ($name === null || ($phone === null && $email === null)) {
                $summary['skipped']++;
                continue;
            }

            $existing = null;
            if ($phone !== null) {
                $existing = Clinic::withTrashed()->where('phone', $phone)->first();
            }
            if ($existing === null && $email !== null) {
                $existing = Clinic::withTrashed()->where('email', $email)->first();
            }

            $attributes = array_filter([
                'name'  => $name,
                'phone' => $phone,
                'email' => $email,
            ], fn ($v) => $v !== null);

            $cityName = $this->cell($row, $map, 'city');
            if ($cityName !== null && ($cityId = $this->resolveCityId($cityName)) !== null) {
                $attributes['city_id'] = $cityId;
            }

            $status = $this->cell($row, $map, 'status');
            if ($status !== null && in_array($status, ['pending', 'active', 'suspended', 'rejected'], true)) {
                $attributes['status'] = $status;
            }

            if ($existing) {
                $existing->fill($attributes)->save();
                $summary['updated']++;
            } else {
                // New clinics default to pending with a random password (login via reset).
                $attributes['status'] = $attributes['status'] ?? 'pending';
                $attributes['password'] = $attributes['password'] ?? Str::random(32);
                Clinic::create($attributes);
                $summary['created']++;
            }
        }

        return $summary;
    }

    /** Parse raw CSV text into an array of rows. */
    private function parseCsv(string $body): array
    {
        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $body);
        rewind($handle);
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Map known field names to their column index, tolerant of EN/AR headers.
     *
     * @return array<string,int>
     */
    private function columnIndexes(array $headers): array
    {
        $rules = [
            'name'   => ['name', 'clinic', 'الاسم', 'اسم', 'المجمع'],
            'phone'  => ['phone', 'mobile', 'tel', 'الجوال', 'الهاتف', 'رقم'],
            'email'  => ['email', 'mail', 'البريد', 'الايميل'],
            'city'   => ['city', 'المدينة', 'مدينة'],
            'status' => ['status', 'الحالة'],
        ];

        $map = [];
        foreach ($headers as $idx => $header) {
            foreach ($rules as $field => $needles) {
                if (isset($map[$field])) {
                    continue;
                }
                foreach ($needles as $needle) {
                    if ($header !== '' && str_contains($header, $needle)) {
                        $map[$field] = $idx;
                        break;
                    }
                }
            }
        }

        return $map;
    }

    /** Return a trimmed, non-empty cell value for a mapped field, or null. */
    private function cell(array $row, array $map, string $field): ?string
    {
        if (! isset($map[$field])) {
            return null;
        }
        $value = trim((string) ($row[$map[$field]] ?? ''));

        return $value === '' ? null : $value;
    }

    /** Normalize a Saudi phone (digits only; 05… kept as-is). */
    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $phone);

        return $digits === '' ? null : $digits;
    }

    /** Resolve a city by Arabic or English name (case-insensitive). */
    private function resolveCityId(string $name): ?int
    {
        $name = trim($name);

        return City::where('name', $name)
            ->orWhere('name_en', $name)
            ->value('id');
    }
}
