<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Clinic extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Below this many total impressions the public "عدد الظهور" badge is
     * hidden — a freshly-onboarded complex shouldn't broadcast a tiny,
     * unprofessional number while the platform is still in launch stage.
     */
    public const IMPRESSIONS_BADGE_MIN = 10;

    /**
     * Default Kanban card-suggestion config, used when a clinic hasn't
     * overridden it. `enabled` gates whether the suggestion surfaces;
     * `hours`/`count` are its tunable threshold. Mirrors the thresholds
     * baked into CustomerInsightService before this became per-clinic.
     *
     * Keep keys in sync with SuggestionKey in the React admin
     * (kanban/types.ts) and CustomerInsightService.
     */
    public const SUGGESTION_DEFAULTS = [
        'confirm_urgent' => ['enabled' => true, 'hours' => 24],
        'first_contact'  => ['enabled' => true],
        'retry_call'     => ['enabled' => true, 'hours' => 2],
        'reminder_soon'  => ['enabled' => true, 'hours' => 48],
        'cancel_risk'    => ['enabled' => true, 'count' => 2],
    ];

    /** Min/max bounds for the tunable thresholds (validation + UI clamp). */
    public const SUGGESTION_BOUNDS = [
        'confirm_urgent' => ['hours' => [1, 168]],
        'retry_call'     => ['hours' => [1, 72]],
        'reminder_soon'  => ['hours' => [1, 168]],
        'cancel_risk'    => ['count' => [1, 10]],
    ];

    protected $fillable = [
        'name', 'slug', 'phone', 'email', 'license_number', 'tax_number',
        'commercial_registration', 'password', 'password_plaintext', 'city_id',
        'address', 'district', 'latitude', 'longitude', 'google_place_id', 'maps_url',
        'description', 'logo', 'gallery', 'website', 'instagram',
        'twitter', 'snapchat', 'tiktok', 'status', 'subscription_type',
        'subscription_package_id',
        'subscription_starts_at', 'subscription_ends_at',
        'rejection_reason', 'is_featured', 'sort_order',
        'booking_stage_labels', 'suggestion_settings',
        // Marketing tracking pixels (see docs/tracking/). Moderated:
        // clinic edits pixels + requests, super-admin approves.
        'tracking_status', 'tracking_pixels', 'advanced_matching_optin',
        'tracking_rejection_reason', 'tracking_requested_at', 'tracking_reviewed_by',
        // Cart feature gate (moderated like tracking: clinic requests,
        // super-admin approves). cart_storefront_enabled is the clinic's
        // own show/hide switch once active.
        'cart_status', 'cart_rejection_reason', 'cart_requested_at',
        'cart_reviewed_by', 'cart_storefront_enabled',
    ];

    protected $hidden = ['password', 'password_plaintext', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            // Reversible copy of the login password (super-admin reveal +
            // approval email). Encrypted at rest with the app key.
            'password_plaintext' => 'encrypted',
            'gallery' => 'array',
            'booking_stage_labels' => 'array',
            'suggestion_settings' => 'array',
            'is_featured' => 'boolean',
            'subscription_starts_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'tracking_pixels' => 'array',
            'advanced_matching_optin' => 'boolean',
            'tracking_requested_at' => 'datetime',
            'cart_requested_at' => 'datetime',
            'cart_storefront_enabled' => 'boolean',
        ];
    }

    /** Cart feature is approved + on for this clinic. */
    public function cartActive(): bool
    {
        return $this->cart_status === 'active';
    }

    /** Cart button should render on this clinic's public pages. */
    public function cartVisible(): bool
    {
        return $this->cartActive() && $this->cart_storefront_enabled;
    }

    /**
     * Set a new login password: stores the bcrypt hash (for auth) and the
     * encrypted plaintext (for super-admin reveal + the credentials email).
     * Does NOT persist — caller saves. Returns the generated/!given plaintext.
     */
    public function issuePassword(?string $plain = null): string
    {
        $plain ??= Str::password(10, letters: true, numbers: true, symbols: false);

        $this->password = $plain;            // hashed by the cast
        $this->password_plaintext = $plain;  // encrypted by the cast

        return $plain;
    }

    protected static function booted(): void
    {
        static::creating(function (Clinic $clinic) {
            if (empty($clinic->slug)) {
                $clinic->slug = Str::slug($clinic->name);
            }
        });
    }

    public function isSubscriptionActive(): bool
    {
        return $this->status === 'active'
            && $this->subscription_ends_at
            && $this->subscription_ends_at->isFuture();
    }

    /**
     * The clinic's effective Kanban suggestion config: stored overrides
     * merged over SUGGESTION_DEFAULTS, normalized + clamped to bounds.
     * Always returns every known key with concrete enabled/threshold
     * values, so callers never have to null-check.
     *
     * @return array<string, array{enabled: bool, hours?: int, count?: int}>
     */
    public function suggestionSettings(): array
    {
        $stored = is_array($this->suggestion_settings) ? $this->suggestion_settings : [];
        $out = [];

        foreach (self::SUGGESTION_DEFAULTS as $key => $defaults) {
            $row = is_array($stored[$key] ?? null) ? $stored[$key] : [];

            $merged = ['enabled' => array_key_exists('enabled', $row)
                ? (bool) $row['enabled']
                : $defaults['enabled']];

            foreach (['hours', 'count'] as $field) {
                if (! isset($defaults[$field])) {
                    continue;
                }
                $value = isset($row[$field]) ? (int) $row[$field] : $defaults[$field];
                [$min, $max] = self::SUGGESTION_BOUNDS[$key][$field];
                $merged[$field] = max($min, min($max, $value));
            }

            $out[$key] = $merged;
        }

        return $out;
    }

    public function isPremium(): bool
    {
        return $this->subscription_type === 'premium' && $this->isSubscriptionActive();
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '>=', now());
    }

    /**
     * Left-join the clinic's subscription package and project its
     * relevant flags as scalar columns on the result row. Used by
     * public ranking + section gating so a single query reads both
     * the clinic and what its package permits, without needing to
     * eager-load the relation or call FeatureGate per row.
     *
     * Adds: pkg_featured_in_search, pkg_ai_assistant_priority,
     *       pkg_verified_badge, pkg_allow_offers_packages,
     *       pkg_allow_doctors_before_after.
     * Clinics without a package fall to 0 (Free defaults) via COALESCE.
     */
    public function scopeWithPackageFeatures(Builder $query): Builder
    {
        // Guard against double-joining when the scope is composed twice.
        $alreadyJoined = collect($query->getQuery()->joins ?? [])
            ->contains(fn ($j) => $j->table === 'subscription_packages as pkg');
        if ($alreadyJoined) {
            return $query;
        }

        return $query
            ->leftJoin('subscription_packages as pkg', 'pkg.id', '=', 'clinics.subscription_package_id')
            ->select('clinics.*')
            ->selectRaw('COALESCE(pkg.featured_in_search, 0) AS pkg_featured_in_search')
            ->selectRaw('COALESCE(pkg.ai_assistant_priority, 0) AS pkg_ai_assistant_priority')
            ->selectRaw('COALESCE(pkg.verified_badge, 0) AS pkg_verified_badge')
            ->selectRaw('COALESCE(pkg.allow_offers_packages, 0) AS pkg_allow_offers_packages')
            ->selectRaw('COALESCE(pkg.allow_doctors_before_after, 0) AS pkg_allow_doctors_before_after');
    }

    /**
     * Public listing rank — package signals first, then admin manual
     * boost, then tier priority, then recency. The package join is
     * mandatory; `is_featured` stays as a per-clinic escalation an
     * admin can flip without changing the whole package's tier.
     */
    public function scopeRankedForListing(Builder $query): Builder
    {
        return $query
            ->withPackageFeatures()
            ->orderByDesc('pkg_featured_in_search')
            ->orderByDesc('clinics.is_featured')
            ->orderByDesc('pkg_ai_assistant_priority')
            ->latest('clinics.created_at');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'clinic_categories');
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function priceQuoteRequests()
    {
        return $this->hasMany(PriceQuoteRequest::class);
    }

    public function googleReviews()
    {
        return $this->hasMany(GoogleReview::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Non-owner team accounts (coordinator / reception). The owner is
     * NOT in this collection — they live on the Clinic row itself.
     */
    public function teamMembers()
    {
        return $this->hasMany(ClinicTeamMember::class);
    }

    /**
     * Activity log entries scoped to this clinic — both owner-driven
     * and team-member-driven events land here.
     */
    public function activityLogs()
    {
        return $this->hasMany(ClinicActivityLog::class);
    }

    /**
     * The owner-as-actor side of the activity log polymorphic relation.
     * Lets us write `$clinic->ownerActivityLogs` to fetch only what the
     * owner did, separate from team-member entries.
     */
    public function ownerActivityLogs()
    {
        return $this->morphMany(ClinicActivityLog::class, 'actor');
    }

    /**
     * The package currently in force for the clinic. Direct FK
     * (subscription_package_id) — kept on the clinic row itself so
     * gates can resolve in one query without hitting subscriptions.
     */
    public function package()
    {
        return $this->belongsTo(SubscriptionPackage::class, 'subscription_package_id');
    }

    public function stats()
    {
        return $this->hasMany(ClinicStat::class);
    }

    /**
     * Total public "appearances" of this complex across every source and
     * day — the figure shown to visitors as «عدد الظهور». Sums the
     * clinic_impressions counters (search + filter + home + similar +
     * compare + profile opens, plus AI silently). Cached for 15 minutes
     * so the social-proof badge never adds a SUM query to every
     * profile / service / offer / doctor page load.
     */
    public function impressionsCount(): int
    {
        return (int) Cache::remember(
            "clinic:{$this->id}:impressions_total",
            now()->addMinutes(15),
            fn () => (int) DB::table('clinic_impressions')
                ->where('clinic_id', $this->id)
                ->sum('count'),
        );
    }

    public function aiConversations()
    {
        return $this->hasMany(AiConversation::class);
    }

    public function notifications()
    {
        return $this->morphMany(PlatformNotification::class, 'notifiable');
    }

    public function workingHours()
    {
        return $this->hasMany(ClinicWorkingHour::class)->orderBy('day_of_week');
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function subClinics()
    {
        return $this->hasMany(SubClinic::class)->where('is_active', true)->orderBy('sort_order');
    }

    /** Unfiltered sub-clinics (active + inactive) — used by admin views. */
    public function subClinicsAll()
    {
        return $this->hasMany(SubClinic::class)->orderBy('sort_order')->orderBy('name');
    }

    public function doctors()
    {
        return $this->hasMany(Doctor::class)->where('is_active', true)->orderBy('sort_order');
    }

    /** Unfiltered doctors (active + inactive) — used by the clinic panel. */
    public function doctorsAll()
    {
        return $this->hasMany(Doctor::class)->orderBy('sort_order')->orderBy('name');
    }

    public function packages()
    {
        return $this->hasMany(Package::class)->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Promotional offers (standalone entity, replaced the legacy
     * old_price/is_featured_offer fields on services).
     */
    public function offers()
    {
        return $this->hasMany(Offer::class);
    }

    /** Unfiltered packages (active + inactive) — used by the clinic panel. */
    public function packagesAll()
    {
        return $this->hasMany(Package::class)->orderBy('sort_order')->orderBy('name');
    }

    public function categoryRequests()
    {
        return $this->hasMany(CategoryRequest::class);
    }

    public function beforeAfterPhotos()
    {
        return $this->hasMany(BeforeAfterPhoto::class)->where('is_active', true)->orderBy('sort_order');
    }

    /** Unfiltered before/after photos — used by the clinic panel. */
    public function beforeAfterPhotosAll()
    {
        return $this->hasMany(BeforeAfterPhoto::class)->orderBy('sort_order')->orderByDesc('id');
    }

    public function whatsappLink(?string $message = null): string
    {
        $phone = preg_replace('/\D/', '', $this->phone ?? '');
        if (str_starts_with($phone, '05')) {
            $phone = '966' . substr($phone, 1);
        }
        $url = 'https://wa.me/' . $phone;
        if ($message) {
            $url .= '?text=' . urlencode($message);
        }
        return $url;
    }

    /**
     * Best-available "Directions" URL, in order of reliability:
     * 1. The Maps link the admin pasted (used verbatim).
     * 2. Google place_id (most accurate programmatic destination).
     * 3. lat/lng coordinates.
     * 4. Free-text address + city as a search query.
     * Returns null only when none of the above exist.
     */
    public function directionsUrl(): ?string
    {
        if (! empty($this->maps_url)) {
            return $this->maps_url;
        }

        if (! empty($this->google_place_id)) {
            return 'https://www.google.com/maps/dir/?api=1&destination='
                . urlencode($this->name)
                . '&destination_place_id=' . urlencode($this->google_place_id);
        }

        if ($this->latitude && $this->longitude) {
            return 'https://www.google.com/maps/dir/?api=1&destination='
                . $this->latitude . ',' . $this->longitude;
        }

        $query = trim(implode(' ', array_filter([
            $this->name,
            $this->address,
            $this->city?->display_name,
        ])));

        return $query !== ''
            ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($query)
            : null;
    }

    /** Whether the complex exposes any location (for showing the Directions button). */
    public function hasDirections(): bool
    {
        return $this->directionsUrl() !== null;
    }

    /** Whether the complex is currently open, based on today's working hours. */
    public function isOpenNow(): bool
    {
        $today = $this->workingHours
            ->firstWhere('day_of_week', (int) now()->dayOfWeek);

        if (! $today || ! $today->is_open || ! $today->opens_at || ! $today->closes_at) {
            return false;
        }

        $now = now();
        $opens = now()->setTimeFromTimeString($today->opens_at);
        $closes = now()->setTimeFromTimeString($today->closes_at);
        if ($closes->lessThanOrEqualTo($opens)) {
            $closes->addDay();
        }

        return $now->between($opens, $closes);
    }

    /**
     * Public social/contact links keyed by platform, with display label and
     * resolved URL — drives the social bar and schema.org sameAs.
     * Only non-empty platforms are returned.
     */
    public function socialLinks(): array
    {
        $handle = fn (?string $v) => ltrim(trim((string) $v), '@');
        $links = [];

        if (! empty($this->website)) {
            $links['website'] = $this->website;
        }
        if (! empty($this->instagram)) {
            $h = $handle($this->instagram);
            $links['instagram'] = str_starts_with($h, 'http') ? $h : 'https://instagram.com/' . $h;
        }
        if (! empty($this->twitter)) {
            $h = $handle($this->twitter);
            $links['twitter'] = str_starts_with($h, 'http') ? $h : 'https://x.com/' . $h;
        }
        if (! empty($this->snapchat)) {
            $h = $handle($this->snapchat);
            $links['snapchat'] = str_starts_with($h, 'http') ? $h : 'https://snapchat.com/add/' . $h;
        }
        if (! empty($this->tiktok)) {
            $h = $handle($this->tiktok);
            $links['tiktok'] = str_starts_with($h, 'http') ? $h : 'https://tiktok.com/@' . $h;
        }

        return $links;
    }
}
