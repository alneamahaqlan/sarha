<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'clinic_id', 'sub_clinic_id',
        'name', 'description',
        'price', 'image',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price'     => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
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
     * Promotional offers for this service. Offers live in their own table
     * now — clinic admins manage them on a dedicated page builder.
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
