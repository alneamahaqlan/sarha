<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public static function log(
        string $action,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): ?AuditLog {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return null;
        }

        return AuditLog::create([
            'admin_id'   => $admin->id,
            'admin_name' => $admin->name,
            'action'     => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id'   => $model?->getKey(),
            'old_values' => self::sanitize($oldValues),
            'new_values' => self::sanitize($newValues),
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
        ]);
    }

    public static function logCreated(Model $model): ?AuditLog
    {
        return self::log(
            action: 'created_' . class_basename($model),
            model: $model,
            newValues: $model->getAttributes(),
        );
    }

    public static function logUpdated(Model $model): ?AuditLog
    {
        if (! $model->wasChanged()) {
            return null;
        }

        $changes = $model->getChanges();
        $original = collect($changes)->mapWithKeys(
            fn ($v, $k) => [$k => $model->getOriginal($k)]
        )->all();

        return self::log(
            action: 'updated_' . class_basename($model),
            model: $model,
            oldValues: self::sanitize($original),
            newValues: self::sanitize($changes),
        );
    }

    public static function logDeleted(Model $model): ?AuditLog
    {
        return self::log(
            action: 'deleted_' . class_basename($model),
            model: $model,
            oldValues: self::sanitize($model->getAttributes()),
        );
    }

    private static function sanitize(?array $values): ?array
    {
        if (! $values) {
            return null;
        }

        return collect($values)
            ->except(['password', 'remember_token', 'api_token'])
            ->all();
    }
}
