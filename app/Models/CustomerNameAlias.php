<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One name a PlatformCustomer has been known by, tagged with where it
 * came from. A null clinic_id means the customer registered it themself
 * on the platform (source = self). Drives the admin "alternative names"
 * panel and each clinic's own-name view.
 */
class CustomerNameAlias extends Model
{
    public const SOURCE_SELF   = 'self';
    public const SOURCE_CLINIC = 'clinic';
    public const SOURCE_IMPORT = 'import';

    protected $fillable = [
        'platform_customer_id', 'clinic_id', 'name', 'source',
    ];

    protected function casts(): array
    {
        // Cast the FKs so strict comparisons (=== $clinicId) in the name
        // resolvers behave regardless of the PDO driver returning strings.
        return [
            'platform_customer_id' => 'integer',
            'clinic_id'            => 'integer',
        ];
    }

    public function platformCustomer(): BelongsTo
    {
        return $this->belongsTo(PlatformCustomer::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /** True for clinic-supplied names (import counts as clinic-supplied). */
    public function isClinicSupplied(): bool
    {
        return $this->source !== self::SOURCE_SELF;
    }
}
