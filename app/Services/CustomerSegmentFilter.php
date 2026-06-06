<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for translating Customer-Hub audience criteria
 * (segment / booking range / last-interaction window / has-notes / search)
 * into query constraints.
 *
 * Used by the Customers Hub list AND by campaign audience resolution, so the
 * recipient count a clinic sees while building a campaign always matches the
 * list it filtered.
 */
class CustomerSegmentFilter
{
    /**
     * Apply the given criteria to a Customer query.
     *
     * @param array{segment?:?string, booking_range?:?string, last_interaction?:?string, has_notes?:bool, search?:?string} $c
     */
    public static function apply(Builder $q, array $c): Builder
    {
        if (! empty($c['search'])) {
            $search = trim((string) $c['search']);
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        match ($c['segment'] ?? null) {
            'vip'           => $q->where('completed_bookings', '>=', Customer::VIP_THRESHOLD),
            'repeat'        => $q->whereBetween('completed_bookings', [Customer::REPEAT_THRESHOLD, Customer::VIP_THRESHOLD - 1]),
            'new'           => $q->where('total_bookings', '<=', 1),
            'has_complaint' => $q->where('total_complaints', '>', 0),
            'inactive_90d'  => $q->where(function ($w) {
                $w->where('last_interaction_at', '<', Carbon::now()->subDays(90))
                  ->orWhereNull('last_interaction_at');
            }),
            'new_month'     => $q->where('first_seen_at', '>=', Carbon::now()->startOfMonth()),
            default         => null,
        };

        match ($c['booking_range'] ?? null) {
            '1'    => $q->where('total_bookings', '=', 1),
            '2-4'  => $q->whereBetween('total_bookings', [2, 4]),
            '5-9'  => $q->whereBetween('total_bookings', [5, 9]),
            '10+'  => $q->where('total_bookings', '>=', 10),
            default => null,
        };

        if (! empty($c['has_notes'])) {
            $q->whereHas('notes');
        }

        match ($c['last_interaction'] ?? null) {
            '30d'       => $q->where('last_interaction_at', '>=', Carbon::now()->subDays(30)),
            '90d'       => $q->where('last_interaction_at', '>=', Carbon::now()->subDays(90)),
            '180d_plus' => $q->where(function ($w) {
                $w->where('last_interaction_at', '<', Carbon::now()->subDays(180))
                  ->orWhereNull('last_interaction_at');
            }),
            default => null,
        };

        return $q;
    }

    /** Pull the recognised criteria out of a request/array, dropping the rest. */
    public static function criteriaFrom(array $input): array
    {
        return [
            'segment'          => $input['segment'] ?? null,
            'booking_range'    => $input['booking_range'] ?? null,
            'last_interaction' => $input['last_interaction'] ?? null,
            'has_notes'        => (bool) ($input['has_notes'] ?? false),
            'search'           => $input['search'] ?? null,
        ];
    }
}
