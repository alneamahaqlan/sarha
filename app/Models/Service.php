<?php

namespace App\Models;

use App\Models\Concerns\HasDemoData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes, HasDemoData;

    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_PENDING  = 'pending';

    protected $fillable = [
        'clinic_id', 'sub_clinic_id', 'catalog_service_id',
        'name', 'description',
        'price', 'price_from', 'price_includes', 'price_excludes', 'image',
        'is_active', 'approval_status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price'      => 'decimal:2',
            'price_from' => 'boolean',
            'is_active'  => 'boolean',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    /** The canonical catalog entry this service is an instance of (nullable). */
    public function catalogService(): BelongsTo
    {
        return $this->belongsTo(CatalogService::class);
    }

    /**
     * Public visibility gate: a service shows to customers only when it is
     * active AND its catalog request (if any) was approved. Apply this on
     * every public-facing query alongside the existing is_active filter.
     */
    public function scopeApprovedPublic(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where('approval_status', self::APPROVAL_APPROVED);
    }

    public function subClinic()
    {
        return $this->belongsTo(SubClinic::class);
    }

    /**
     * The specialties this service belongs to. Many-to-many because a single
     * service often spans more than one specialty (e.g. "laser hair removal"
     * is both dermatology + cosmetics). Validated to 1–5 categories at the
     * API layer.
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_service')->withTimestamps();
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class)->withTimestamps();
    }

    /**
     * Doctors who provide this service. Many-to-many: a service may be done
     * by several doctors, and a doctor performs several services. Picked on
     * the clinic "add service" form; mirrored on each doctor's profile.
     */
    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_service')->withTimestamps();
    }

    /**
     * Promotional offers for this service. Offers live in their own table
     * now — clinic admins manage them on a dedicated page builder.
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * The single offer (if any) created from the inline "سعر العرض" field on
     * the service form. Kept separate from manually-built offers via the
     * origin marker so the form upserts/deletes only this one.
     */
    public function inlineOffer(): HasOne
    {
        return $this->hasOne(Offer::class)->where('origin', Offer::ORIGIN_SERVICE_FORM);
    }

    /** Purchase line items referencing this service (across bookings). */
    public function bookingServices(): HasMany
    {
        return $this->hasMany(BookingService::class);
    }

    /** Interest records referencing this service. */
    public function interestedCustomers(): HasMany
    {
        return $this->hasMany(CustomerInterestedService::class);
    }
}
