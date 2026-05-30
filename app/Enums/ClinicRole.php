<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Roles for the clinic-side panel. Three fixed values per spec — no
 * custom roles, no DB table. The enum is the single source of truth
 * for what each role can do; the React UI and middleware both read
 * from `abilities()` so they cannot drift.
 *
 * Ability wildcards use Laravel's Str::is matching:
 *   'bookings.*' matches 'bookings.viewAny', 'bookings.update', ...
 *   '*' matches everything (owner only).
 */
enum ClinicRole: string
{
    case OWNER       = 'owner';
    case COORDINATOR = 'coordinator';
    case RECEPTION   = 'reception';

    /**
     * The full list of ability patterns this role can perform. Sidebar
     * filtering, route middleware, and per-resource policies all read
     * from here.
     */
    public function abilities(): array
    {
        return match ($this) {
            self::OWNER => ['*'],
            self::COORDINATOR => [
                // Day-to-day operations
                'bookings.*', 'complaints.*', 'price_quotes.*',
                // Content + catalog
                'services.*', 'doctors.*', 'sub_clinics.*',
                'offers.*', 'packages.*',
                'articles.*', 'before_after.*',
                'category_requests.*',
                'page_builder.*', 'profile.view',
            ],
            self::RECEPTION => [
                'bookings.*', 'complaints.*', 'price_quotes.*',
                'profile.view',
            ],
        };
    }

    /**
     * Pattern-match an ability against this role's grants. Used by
     * middleware AND by the React UI (via the permissions payload).
     */
    public function can(string $ability): bool
    {
        foreach ($this->abilities() as $pattern) {
            if ($pattern === '*' || Str::is($pattern, $ability)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Display label for the React UI badge — looked up via i18n in
     * the client; this is just the slug.
     */
    public function label(): string
    {
        return $this->value;
    }

    /**
     * Color token consumed by the React Badge component. Kept here
     * (not in the React layer) so the catalog stays in PHP next to
     * the role definition itself.
     */
    public function colorToken(): string
    {
        return match ($this) {
            self::OWNER       => 'gold',
            self::COORDINATOR => 'info',
            self::RECEPTION   => 'muted',
        };
    }

    /**
     * Compact permissions map shipped in the auth payload. Keys are
     * the abilities the React UI checks via useCan(); values are
     * booleans. Drives both nav filtering and per-page guards.
     *
     * The list is intentionally enumerated (not derived from the
     * patterns) so the frontend doesn't have to interpret wildcards.
     */
    public function permissionMap(): array
    {
        $abilities = [
            // Sidebar items (used by ClinicLayout to filter nav)
            'bookings.view', 'bookings.update',
            'complaints.view', 'complaints.reply',
            'price_quotes.view', 'price_quotes.reply',
            'services.view', 'services.manage',
            'doctors.view', 'doctors.manage',
            'sub_clinics.view', 'sub_clinics.manage',
            'offers.view', 'offers.manage',
            'packages.view', 'packages.manage',
            'articles.view', 'articles.manage',
            'before_after.view', 'before_after.manage',
            'category_requests.view',
            'page_builder.view', 'page_builder.manage',
            'profile.view', 'profile.manage',
            // Owner-only domains
            'subscription.view', 'subscription.manage',
            'team.view', 'team.manage',
            'team_activity.view',
            'stats.view',
        ];

        $map = [];
        foreach ($abilities as $a) {
            $map[$a] = $this->can($a);
        }
        return $map;
    }
}
