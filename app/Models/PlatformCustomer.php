<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform-wide customer identity — one row per human, keyed by the
 * normalized phone number. Sits *above* the per-clinic Customer rows:
 * those keep all CRM state (notes, tags, counters); this unifies the
 * identity, the canonical name and the verification status.
 *
 * Name resolution (see CustomerLinker for the write side):
 *   - canonical_name = the self-registered name once a User exists,
 *     otherwise the earliest clinic-supplied alias.
 *   - Each clinic's view = the earliest alias *that clinic* supplied,
 *     unless the customer has self-registered (then canonical wins).
 *   - Every other name is an "alternative", tagged with its source.
 */
class PlatformCustomer extends Model
{
    protected $fillable = [
        'phone', 'user_id', 'canonical_name', 'is_phone_verified', 'first_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_phone_verified' => 'boolean',
            'first_seen_at'     => 'datetime',
        ];
    }

    // ---------- relations ----------
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** All names ever associated, across every source. */
    public function aliases(): HasMany
    {
        return $this->hasMany(CustomerNameAlias::class)->orderBy('created_at');
    }

    /** The per-clinic CRM rows that share this identity. */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    // ---------- display helpers ----------
    /**
     * Name shown to a specific clinic: the self-registered name if the
     * customer ever signed up, otherwise the earliest name *that clinic*
     * gave them. Falls back to the canonical name, then a dash.
     */
    public function displayNameForClinic(int $clinicId): string
    {
        if ($this->canonical_name && $this->is_phone_verified) {
            return $this->canonical_name;
        }

        $clinicAlias = $this->aliases
            ->firstWhere(fn (CustomerNameAlias $a) => (int) $a->clinic_id === $clinicId);

        return $clinicAlias?->name ?? $this->canonical_name ?? '—';
    }

    /** Name shown to admins: canonical (self) name when present. */
    public function displayName(): string
    {
        return $this->canonical_name
            ?? $this->aliases->first()?->name
            ?? '—';
    }
}
