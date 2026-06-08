<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\ClinicActivityLog;
use App\Models\Customer;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Per-request batched accessor for Customer-derived signals used by
 * the Kanban (auto-tags, suggestions, customer tags, heat).
 *
 * Phase 2 rewrite: instead of recomputing counters every request by
 * scanning bookings/complaints, we read directly from the Customer
 * entity's persisted counters (populated by CustomerLinker
 * observers in phase 1). Auto-tags are computed via the model's
 * own is_vip / is_repeat / etc. accessors.
 *
 * Suggestions still need a live look at recent ClinicActivityLog
 * entries (call_attempted with outcome, reminder_sent timing) so
 * the suggestion preload remains.
 */
class CustomerInsightService
{
    /** @var array<int, Customer> customer_id => Customer */
    private array $customerCache = [];

    /** @var array<int, array<int,string>> bookingId => suggestion keys */
    private array $suggestionCache = [];

    /**
     * Cancel-risk threshold (number of past cancellations/no-shows) for
     * the clinic whose board is being preloaded. Resolved once in
     * preloadSuggestions; falls back to the default until then.
     */
    private int $cancelRiskThreshold = Clinic::SUGGESTION_DEFAULTS['cancel_risk']['count'];

    /**
     * Preload signals for a set of bookings. After this call,
     * insightsFor() / suggestionsFor() are O(1) lookups.
     *
     * The collection must contain the bookings' customer_id values —
     * passed bookings without one fall back to a phone lookup so
     * legacy rows still render something sensible.
     */
    public function preload(int $clinicId, Collection $bookings): void
    {
        $customerIds = $bookings->pluck('customer_id')->filter()->unique()->values();

        // Bookings without customer_id (shouldn't happen post-phase 1,
        // defensive): resolve by phone so the card still shows badges.
        $orphanPhones = $bookings
            ->filter(fn($b) => empty($b->customer_id))
            ->pluck('customer_phone')
            ->filter()
            ->map(fn($p) => PhoneNormalizer::normalizeOrSelf($p))
            ->unique()
            ->values();

        if ($customerIds->isNotEmpty()) {
            Customer::query()
                ->whereIn('id', $customerIds)
                ->with('tags:id,customer_id,label,color')
                ->get()
                ->each(fn(Customer $c) => $this->customerCache[$c->id] = $c);
        }

        if ($orphanPhones->isNotEmpty()) {
            Customer::query()
                ->where('clinic_id', $clinicId)
                ->whereIn('phone', $orphanPhones)
                ->with('tags:id,customer_id,label,color')
                ->get()
                ->each(fn(Customer $c) => $this->customerCache[$c->id] = $c);
        }

        $this->preloadSuggestions($clinicId, $bookings);
    }

    /**
     * Get the Customer for a booking. Looks up by customer_id when
     * present; falls back to (clinic_id, normalized phone) for legacy
     * unlinked rows.
     */
    public function customerFor(Booking $booking): ?Customer
    {
        if ($booking->customer_id && isset($this->customerCache[$booking->customer_id])) {
            return $this->customerCache[$booking->customer_id];
        }
        // Fallback to phone scan within the preloaded cache.
        $phone = PhoneNormalizer::normalizeOrSelf($booking->customer_phone);
        foreach ($this->customerCache as $c) {
            if ($c->clinic_id === $booking->clinic_id && $c->phone === $phone) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Flat insights dict (compatibility with the old resource shape).
     */
    public function insightsFor(Booking $booking): array
    {
        $c = $this->customerFor($booking);
        if (! $c) {
            return [
                'is_vip'             => false,
                'is_repeat'          => false,
                'is_new'             => true,
                'completed_count'    => 0,
                'total_bookings'     => 0,
                'first_seen'         => null,
                'cancel_risk'        => false,
                'has_open_complaint' => false,
            ];
        }
        return [
            'is_vip'             => $c->is_vip,
            'is_repeat'          => $c->is_repeat,
            'is_new'             => $c->is_new,
            'completed_count'    => $c->completed_bookings,
            'total_bookings'     => $c->total_bookings,
            'first_seen'         => $c->first_seen_at?->toIso8601String(),
            // cancel_risk is still derived (no dedicated counter): it's
            // a behavioural signal, not a count. Pull it live from the
            // booking history for this customer.
            'cancel_risk'        => $this->cancelRiskFor($c),
            'has_open_complaint' => $c->has_prior_complaint,
        ];
    }

    public function suggestionsFor(int $bookingId): array
    {
        return $this->suggestionCache[$bookingId] ?? [];
    }

    /**
     * Heat indicator: red for urgent / cancel risk, yellow for VIP /
     * repeat / complaint, green otherwise.
     *
     * @param  array<int,string> $activeSuggestionKeys
     */
    public function heatFor(Booking $booking, array $activeSuggestionKeys): string
    {
        if (in_array('confirm_urgent', $activeSuggestionKeys, true)
            || in_array('cancel_risk', $activeSuggestionKeys, true)) {
            return 'red';
        }
        $c = $this->customerFor($booking);
        if ($c && ($c->is_vip || $c->is_repeat || $c->has_prior_complaint)) {
            return 'yellow';
        }
        return 'green';
    }

    /**
     * Returns the customer-scoped tags for a booking's customer.
     * Loaded eagerly during preload, so this is a cheap dict lookup.
     */
    public function customerTagsFor(Booking $booking): array
    {
        $c = $this->customerFor($booking);
        return $c?->tags?->all() ?? [];
    }

    // ---------- internal ----------

    /**
     * Cancel-risk = the customer has N+ historical cancellations or
     * no-shows, where N is the clinic's configured threshold (default 2,
     * resolved in preloadSuggestions). Computed live (we don't store it
     * as a counter). Cached per customer so multiple cards for the same
     * customer don't re-query.
     */
    private array $cancelRiskCache = [];
    private function cancelRiskFor(Customer $customer): bool
    {
        if (! isset($this->cancelRiskCache[$customer->id])) {
            $this->cancelRiskCache[$customer->id] = Booking::query()
                ->where('customer_id', $customer->id)
                ->whereIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
                ->count() >= $this->cancelRiskThreshold;
        }
        return $this->cancelRiskCache[$customer->id];
    }

    /**
     * Suggestion preload reads recent activities for the visible
     * bookings in a single batched query. State for "retry_call" etc
     * is derived from the activity log, not the customer entity.
     */
    private function preloadSuggestions(int $clinicId, Collection $bookings): void
    {
        $bookingIds = $bookings->pluck('id')->all();
        if (empty($bookingIds)) return;

        // Per-clinic suggestion config (which nudges are on + thresholds).
        // Resolved once for the whole board; missing clinic → defaults.
        $settings = Clinic::find($clinicId)?->suggestionSettings()
            ?? Clinic::SUGGESTION_DEFAULTS;
        $this->cancelRiskThreshold = (int) ($settings['cancel_risk']['count']
            ?? Clinic::SUGGESTION_DEFAULTS['cancel_risk']['count']);

        $actions = ['booking.call_attempted', 'booking.whatsapped', 'booking.reminder_sent'];

        $activityRows = ClinicActivityLog::query()
            ->where('clinic_id', $clinicId)
            ->where('model_type', Booking::class)
            ->whereIn('model_id', $bookingIds)
            ->whereIn('action', $actions)
            ->orderByDesc('created_at')
            ->get(['model_id', 'action', 'summary', 'created_at'])
            ->groupBy('model_id');

        $now = Carbon::now();

        foreach ($bookings as $b) {
            $activeKeys = [];

            $appt = $b->appointment_at ? Carbon::parse($b->appointment_at) : null;
            $isPending = in_array($b->status, [Booking::STATUS_NEW, Booking::STATUS_CONTACTED], true);
            $rows = $activityRows->get($b->id, collect());

            $hasContact = $rows->whereIn('action', ['booking.call_attempted', 'booking.whatsapped'])->isNotEmpty();
            $hasReminder = $rows->where('action', 'booking.reminder_sent')->isNotEmpty();
            $lastCall = $rows->where('action', 'booking.call_attempted')->first();

            if ($settings['confirm_urgent']['enabled']
                && $isPending && $appt
                && $appt->lte($now->copy()->addHours($settings['confirm_urgent']['hours'])) && $appt->gte($now)) {
                $activeKeys[] = 'confirm_urgent';
            }

            if ($settings['first_contact']['enabled']
                && $b->status === Booking::STATUS_NEW && ! $hasContact) {
                $activeKeys[] = 'first_contact';
            }

            if ($settings['retry_call']['enabled'] && $lastCall) {
                $outcome = $lastCall->summary['outcome'] ?? null;
                $hoursSince = Carbon::parse($lastCall->created_at)->diffInHours($now);
                if ($outcome === 'no_answer' && $hoursSince >= $settings['retry_call']['hours'] && $isPending) {
                    $activeKeys[] = 'retry_call';
                }
            }

            if ($settings['reminder_soon']['enabled']
                && $b->status === Booking::STATUS_APPOINTMENT_SET && $appt
                && $appt->lte($now->copy()->addHours($settings['reminder_soon']['hours'])) && $appt->gte($now) && ! $hasReminder) {
                $activeKeys[] = 'reminder_soon';
            }

            $customer = $this->customerFor($b);
            if ($settings['cancel_risk']['enabled']
                && $customer && $this->cancelRiskFor($customer) && $isPending) {
                $activeKeys[] = 'cancel_risk';
            }

            $this->suggestionCache[$b->id] = $activeKeys;
        }
    }
}
