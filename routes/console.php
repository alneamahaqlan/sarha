<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily prune of AI assistant logs (guarded by `ai_logs_retention_days`
// setting — runs cheaply as a no-op when retention is disabled).
Schedule::command('ai:prune-logs')->dailyAt('03:30');

// Note: the subscription lifecycle command is scheduled from
// bootstrap/app.php (06:00 daily), not here — keeping all custom
// saerha:* schedules in one file.
