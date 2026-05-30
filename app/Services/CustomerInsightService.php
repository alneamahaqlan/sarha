<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCustomerTag;
use App\Models\ClinicActivityLog;
use App\Models\Complaint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes per-customer (phone-keyed) derived signals used by the
 * Kanban: auto-tags (vip / repeat / new / cancel-risk), open-complaint
 * flag, and the smart suggestion list per booking.
 *
 * Designed to be called ONCE per request with the full list of phones
 * visible in the column. All counts are pulled in a single grouped
 * query per concern (no N+1). Results are cached on the service
 * instance for the duration of the request.
 *
 * Registered as a singleton in AppServiceProvider so the cache
 * survives across resources/controllers within the same request.
 */
class CustomerInsightService
{
    private const VIP_COMPLETED_THRESHOLD = 5;
    private const REPEAT_COMPLETED_MIN    = 2;
    private const CANCEL_RISK_THRESHOLD   = 2;

    /** @var array<string, array<string, mixed>> phone => insights */
    private array $cache = [];

    /** @var array<int, array<string, mixed>> bookingId => suggestions */
    private array $suggestionCache = [];

    /**
     * Preload signals for a set of bookings within a clinic. After
     * this, insightsFor() / suggestionsFor() are O(1) lookups.
     */
    public function preload(int $clinicId, Collection $bookings): void
    {
        $phones = $bookings->pluck('customer_phone')->unique()->filter()->values();
        if ($phones->isEmpty()) {
            return;
        }

        $completedCounts = Booking::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('customer_phone', $phones)
            ->where('status', Booking::STATUS_COMPLETED)
            ->selectRaw('customer_phone, COUNT(*) as aggregate')
            ->groupBy('customer_phone')
            ->pluck('aggregate', 'customer_phone');

        $totalCounts = Booking::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('customer_phone', $phones)
            ->selectRaw('customer_phone, COUNT(*) as aggregate, MIN(created_at) as first_seen')
            ->groupBy('customer_phone')
            ->get()
            ->keyBy('customer_phone');

        $cancelCounts = Booking::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('customer_phone', $phones)
            ->whereIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->selectRaw('customer_phone, COUNT(*) as aggregate')
            ->groupBy('customer_phone')
            ->pluck('aggregate', 'customer_phone');

        $openComplaintPhones = Complaint::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('customer_phone', $phones)
            ->whereIn('status', ['new', 'in_review'])
            ->pluck('customer_phone')
            ->unique()
            ->flip();

        foreach ($phones as $phone) {
            $completed = (int) ($completedCounts[$phone] ?? 0);
            $total     = (int) ($totalCounts[$phone]->aggregate ?? 0);
            $firstSeen = $totalCounts[$phone]->first_seen ?? null;
            $cancels   = (int) ($cancelCounts[$phone] ?? 0);

            $this->cache[$phone] = [
                'is_vip'             => $completed >= self::VIP_COMPLETED_THRESHOLD,
                'is_repeat'          => $completed >= self::REPEAT_COMPLETED_MIN && $completed < self::VIP_COMPLETED_THRESHOLD,
                'is_new'             => $total <= 1, // current booking is the only one
                'completed_count'    => $completed,
                'total_bookings'     => $total,
                'first_seen'         => $firstSeen ? Carbon::parse($firstSeen)->toIso8601String() : null,
                'cancel_risk'        => $cancels >= self::CANCEL_RISK_THRESHOLD,
                'has_open_complaint' => isset($openComplaintPhones[$phone]),
            ];
        }

        $this->preloadSuggestions($clinicId, $bookings);
    }

    public function insightsFor(string $phone): array
    {
        return $this->cache[$phone] ?? [
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

    public function suggestionsFor(int $bookingId): array
    {
        return $this->suggestionCache[$bookingId] ?? [];
    }

    /**
     * Heat indicator: red for urgent, yellow for attention, green
     * otherwise. Derived purely from already-computed signals.
     *
     * @param  array<int,string> $activeSuggestionKeys
     */
    public function heatFor(string $phone, array $activeSuggestionKeys): string
    {
        if (in_array('confirm_urgent', $activeSuggestionKeys, true)
            || in_array('cancel_risk', $activeSuggestionKeys, true)) {
            return 'red';
        }
        $insights = $this->insightsFor($phone);
        if ($insights['is_vip'] || $insights['is_repeat'] || $insights['has_open_complaint']) {
            return 'yellow';
        }
        return 'green';
    }

    /**
     * Smart suggestions tied to booking state + activity. Computed
     * per booking; we batch-load all activities for the visible
     * bookings, then derive suggestions from that pile.
     */
    private function preloadSuggestions(int $clinicId, Collection $bookings): void
    {
        $bookingIds = $bookings->pluck('id')->all();
        if (empty($bookingIds)) return;

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

            if ($isPending && $appt && $appt->lte($now->copy()->addHours(24)) && $appt->gte($now)) {
                $activeKeys[] = 'confirm_urgent';
            }

            if ($b->status === Booking::STATUS_NEW && ! $hasContact) {
                $activeKeys[] = 'first_contact';
            }

            if ($lastCall) {
                $outcome = $lastCall->summary['outcome'] ?? null;
                $hoursSince = Carbon::parse($lastCall->created_at)->diffInHours($now);
                if ($outcome === 'no_answer' && $hoursSince >= 2 && $isPending) {
                    $activeKeys[] = 'retry_call';
                }
            }

            if ($b->status === Booking::STATUS_APPOINTMENT_SET && $appt
                && $appt->lte($now->copy()->addHours(48)) && $appt->gte($now) && ! $hasReminder) {
                $activeKeys[] = 'reminder_soon';
            }

            $insights = $this->insightsFor((string) $b->customer_phone);
            if ($insights['cancel_risk'] && $isPending) {
                $activeKeys[] = 'cancel_risk';
            }

            $this->suggestionCache[$b->id] = $activeKeys;
        }
    }

    public function customerTagsFor(int $clinicId, string $phone): Collection
    {
        return BookingCustomerTag::query()
            ->where('clinic_id', $clinicId)
            ->where('customer_phone', $phone)
            ->orderBy('id')
            ->get();
    }
}
