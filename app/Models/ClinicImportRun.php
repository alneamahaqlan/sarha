<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One executed pull from a ClinicImportSource. Records the row range and
 * counts so a later re-pull can detect overlap with rows already imported.
 */
class ClinicImportRun extends Model
{
    protected $fillable = [
        'import_source_id', 'clinic_id',
        'row_from', 'row_to', 'rows_imported', 'rows_skipped',
        'created_by_type', 'created_by_id', 'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'row_from'      => 'integer',
            'row_to'        => 'integer',
            'rows_imported' => 'integer',
            'rows_skipped'  => 'integer',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ClinicImportSource::class, 'import_source_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function createdBy()
    {
        return $this->morphTo('created_by');
    }
}
