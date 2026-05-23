<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Services\GoogleSheetsImportService;
use Illuminate\Console\Command;

class SyncClinicsSheet extends Command
{
    protected $signature = 'saerha:sync-clinics-sheet';
    protected $description = 'Import/refresh clinics from the configured public Google Sheet (system setting: clinics_sheet_url).';

    public function handle(GoogleSheetsImportService $sheets): int
    {
        $url = SystemSetting::get('clinics_sheet_url');

        if (empty($url)) {
            $this->info('No clinics_sheet_url configured — nothing to sync.');

            return self::SUCCESS;
        }

        if ($sheets->toCsvUrl($url) === null) {
            $this->warn('Configured clinics_sheet_url is not a valid Google Sheets link — skipping.');

            return self::SUCCESS;
        }

        $summary = $sheets->import($url);

        $this->info(sprintf(
            'Clinics sheet sync complete: %d created, %d updated, %d skipped.',
            $summary['created'],
            $summary['updated'],
            $summary['skipped'],
        ));

        return self::SUCCESS;
    }
}
