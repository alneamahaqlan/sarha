<?php

namespace App\Observers;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function created(Model $model): void
    {
        AuditLogService::logCreated($model);
    }

    public function updated(Model $model): void
    {
        AuditLogService::logUpdated($model);
    }

    public function deleted(Model $model): void
    {
        AuditLogService::logDeleted($model);
    }
}
