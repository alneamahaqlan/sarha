<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A saved public Google Sheet a clinic imports leads from. See the migration
 * for column semantics. The heavy lifting (reading the sheet, matching
 * services, creating bookings) lives in App\Services\Import\*.
 */
class ClinicImportSource extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'clinic_id', 'name', 'sheet_url',
        'column_map', 'defaults', 'service_aliases', 'last_imported_at',
    ];

    protected function casts(): array
    {
        return [
            'column_map'       => 'array',
            'defaults'         => 'array',
            'service_aliases'  => 'array',
            'last_imported_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ClinicImportRun::class, 'import_source_id');
    }
}
