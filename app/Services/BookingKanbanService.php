<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Backs the Kanban board endpoints: per-column paginated queries
 * (cursor-friendly), top-bar stats, and shared filter application.
 *
 * No view rendering, no permission checks — those live in the
 * controller / middleware.
 */
class BookingKanbanService
{
    public function __construct(
        private readonly CustomerInsightService $insights,
    ) {}

    /**
     * Fetch a single Kanban column.
     *
     * @return array{items: Collection, next_cursor: ?string, has_more: bool}
     */
    public function column(int $clinicId, string $column, array $filters = [], ?int $cursor = null, int $limit = 20): array
    {
        $q = $this->baseQuery($clinicId, $filters)->forKanbanColumn($column);

        // Cursor = last id seen (descending order). Cheaper than offset
        // and stable under inserts.
        if ($cursor) {
            $q->where('id', '<', $cursor);
        }

        $q->orderByRaw('CASE WHEN appointment_at IS NULL THEN 1 ELSE 0 END, appointment_at ASC')
          ->orderByDesc('id');

        $items = $q->take($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items = $items->slice(0, $limit)->values();
        }

        $this->insights->preload($clinicId, $items);

        return [
            'items'       => $items,
            'next_cursor' => $hasMore ? (string) $items->last()->id : null,
            'has_more'    => $hasMore,
        ];
    }

    /**
     * Headline stats for the top bar. Single combined select.
     */
    public function stats(int $clinicId): array
    {
        $today     = Carbon::today();
        $yesterday = Carbon::yesterday();
        $now       = Carbon::now();
        $weekStart = Carbon::now()->startOfWeek();

        $todayCount = Booking::query()
            ->where('clinic_id', $clinicId)
            ->whereDate('appointment_at', $today)
            ->whereIn('status', [Booking::STATUS_APPOINTMENT_SET, Booking::STATUS_COMPLETED])
            ->count();

        $yesterdayNoShow = Booking::query()
            ->where('clinic_id', $clinicId)
            ->whereDate('appointment_at', $yesterday)
            ->where('status', Booking::STATUS_NO_SHOW)
            ->count();

        $needsUrgent = Booking::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('status', [Booking::STATUS_NEW, Booking::STATUS_CONTACTED])
            ->whereNotNull('appointment_at')
            ->where('appointment_at', '<=', $now->copy()->addHours(24))
            ->where('appointment_at', '>=', $now)
            ->count();

        $weeklyTotals = Booking::query()
            ->where('clinic_id', $clinicId)
            ->where('created_at', '>=', $weekStart)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as confirmed
            ', [Booking::STATUS_APPOINTMENT_SET, Booking::STATUS_COMPLETED])
            ->first();

        $rate = ($weeklyTotals && $weeklyTotals->total > 0)
            ? round(((int) $weeklyTotals->confirmed) / ((int) $weeklyTotals->total), 2)
            : null;

        return [
            'today_count'         => $todayCount,
            'yesterday_no_show'   => $yesterdayNoShow,
            'needs_urgent_confirm'=> $needsUrgent,
            'weekly_confirm_rate' => $rate,
        ];
    }

    /**
     * Builds the base query with shared filters applied. Used by
     * both columns and table view.
     */
    public function baseQuery(int $clinicId, array $filters = []): Builder
    {
        $q = Booking::query()
            ->where('clinic_id', $clinicId)
            ->with(['service:id,name', 'assignee', 'tags']);

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $q->where(function ($w) use ($search) {
                $w->where('reference_code', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        if ($svc = $filters['service_id'] ?? null) {
            $q->where('service_id', (int) $svc);
        }

        if ($assignee = $filters['assignee_id'] ?? null) {
            // assignee=mine handled separately (controller injects actor type/id)
            $q->where('assignee_id', (int) $assignee);
            if ($type = $filters['assignee_type'] ?? null) {
                $q->where('assignee_type', $type);
            }
        }

        if (! empty($filters['mine_only']) && ! empty($filters['actor_type']) && ! empty($filters['actor_id'])) {
            $q->where('assignee_type', $filters['actor_type'])
              ->where('assignee_id', (int) $filters['actor_id']);
        }

        if ($from = $filters['date_from'] ?? null) {
            $q->whereDate('appointment_at', '>=', $from);
        }
        if ($to = $filters['date_to'] ?? null) {
            $q->whereDate('appointment_at', '<=', $to);
        }

        // The `auto_tag` filter maps to a derived signal — most are
        // now backed by Customer entity counters from phase 1, so we
        // push the filter down into SQL via whereHas (uses the new
        // (clinic_id, completed_bookings) etc. composite indexes).
        $autoTag = $filters['auto_tag'] ?? null;
        match ($autoTag) {
            'urgent_confirm' => $q
                ->whereIn('status', [Booking::STATUS_NEW, Booking::STATUS_CONTACTED])
                ->whereNotNull('appointment_at')
                ->where('appointment_at', '<=', now()->addHours(24))
                ->where('appointment_at', '>=', now()),

            'vip' => $q->whereHas('customer', fn($w) => $w
                ->where('completed_bookings', '>=', \App\Models\Customer::VIP_THRESHOLD)),

            'repeat' => $q->whereHas('customer', fn($w) => $w
                ->whereBetween('completed_bookings', [\App\Models\Customer::REPEAT_THRESHOLD, \App\Models\Customer::VIP_THRESHOLD - 1])),

            'new_customer' => $q->whereHas('customer', fn($w) => $w
                ->where('total_bookings', '<=', 1)),

            'has_complaint' => $q->whereHas('customer', fn($w) => $w
                ->where('total_complaints', '>', 0)),

            default => null,
        };

        // Custom-tag filter: matches a tag label across BOTH scopes
        // (per-booking tags and per-customer tags). After phase 2 the
        // customer tags live in `customer_tags` keyed by customer_id.
        if ($label = trim((string) ($filters['custom_tag'] ?? ''))) {
            $q->where(function ($w) use ($label) {
                $w->whereHas('tags', fn($q) => $q->where('label', $label))
                  ->orWhereHas('customer.tags', fn($q) => $q->where('label', $label));
            });
        }

        return $q;
    }

    /**
     * Distinct list of custom tag labels in use within a clinic —
     * both customer-scoped and booking-scoped. Powers the filter
     * dropdown.
     */
    public function tagLabels(int $clinicId): array
    {
        $customer = \App\Models\CustomerTag::query()
            ->whereHas('customer', fn($q) => $q->where('clinic_id', $clinicId))
            ->select('label', 'color')
            ->distinct()
            ->get();
        $booking = \App\Models\BookingTag::query()
            ->whereHas('booking', fn($q) => $q->where('clinic_id', $clinicId))
            ->select('label', 'color')
            ->distinct()
            ->get();
        return $customer->concat($booking)
            ->unique('label')
            ->values()
            ->map(fn($t) => ['label' => $t->label, 'color' => $t->color])
            ->all();
    }
}
