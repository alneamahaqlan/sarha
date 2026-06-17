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
                'customers.*', 'reminders.*', 'campaigns.*',
                // Abandoned-cart follow-up (view + outreach + convert)
                'cart_leads.*',
                // Content + catalog
                'services.*', 'doctors.*', 'sub_clinics.*',
                'offers.*', 'packages.*',
                'articles.*', 'before_after.*', 'stories.*',
                'category_requests.*',
                'landing_pages.*',
                'page_builder.*', 'profile.view',
                // Cashback rewards — coordinators configure the rule, grant,
                // view, and redeem.
                'rewards.*',
            ],
            self::RECEPTION => [
                'bookings.*', 'complaints.*', 'price_quotes.*',
                // Reception sees + searches customers and can ADD
                // notes (author check enforces self-edit only) but
                // can't edit details or delete others' notes.
                'customers.view', 'customers.notes.create',
                // Reception drives day-to-day follow-up, so it can set,
                // complete, and cancel contact reminders.
                'reminders.*',
                // Reception also chases abandoned carts (view + outreach + convert).
                'cart_leads.*',
                // Reception sees + REDEEMS cashback vouchers at the desk, but
                // does NOT configure the grant rule or issue manual grants.
                'rewards.view', 'rewards.redeem',
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
            'stories.view', 'stories.manage',
            'category_requests.view',
            // Landing pages — clinic-built, vetted once by the platform admin.
            'landing_pages.view', 'landing_pages.manage',
            'page_builder.view', 'page_builder.manage',
            'profile.view', 'profile.manage',
            // Marketing tracking pixels — owner-only (sensitive: affects
            // what loads on the public site + privacy/PDPL surface).
            'tracking.view', 'tracking.manage',
            // Cart feature — owner-only (affects the public storefront +
            // is gated/approved by the platform admin).
            'cart.view', 'cart.manage',
            // Abandoned-cart follow-up (view list + contact/convert). Available
            // to owner, coordinator, and reception — they drive follow-up.
            'cart_leads.view', 'cart_leads.contact',
            // Customer Hub (phase 3)
            'customers.view', 'customers.manage',
            'customers.notes.create', 'customers.notes.manage',
            // Contact reminders (set / complete / cancel a follow-up nudge)
            'reminders.view', 'reminders.create', 'reminders.manage',
            // Patient campaigns (segment + manual WhatsApp outreach)
            'campaigns.view', 'campaigns.manage',
            // Cashback rewards — view/redeem (reception+) vs manage (config +
            // manual grant; coordinator/owner only).
            'rewards.view', 'rewards.redeem', 'rewards.manage',
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
