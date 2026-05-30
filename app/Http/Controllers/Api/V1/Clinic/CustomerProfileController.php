<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClinicActivityLog;
use App\Models\Complaint;
use App\Models\PriceQuoteRequest;
use App\Services\CustomerInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Customer 360 for the Kanban side-panel — keyed by phone since
 * there's no canonical Customer model. Strictly scoped to the
 * acting clinic so a member never sees another clinic's history
 * for the same phone.
 */
class CustomerProfileController extends Controller
{
    public function __construct(
        private readonly CustomerInsightService $insights,
    ) {}

    public function show(string $phone): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();

        $bookings = Booking::query()
            ->where('clinic_id', $clinicId)
            ->where('customer_phone', $phone)
            ->with('service:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $complaints = Complaint::query()
            ->where('clinic_id', $clinicId)
            ->where('customer_phone', $phone)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $quotes = PriceQuoteRequest::query()
            ->where('clinic_id', $clinicId)
            ->where('customer_phone', $phone)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $lastActivity = ClinicActivityLog::query()
            ->where('clinic_id', $clinicId)
            ->whereJsonContains('summary->customer_phone', $phone)
            ->orderByDesc('created_at')
            ->first();

        // Side-effect: preload insights for the single phone so the
        // summary block reflects the same cache other endpoints use.
        $this->insights->preload($clinicId, $bookings);
        $signals = $this->insights->insightsFor($phone);

        $name = $bookings->first()?->customer_name
            ?? $complaints->first()?->customer_name
            ?? $quotes->first()?->customer_name
            ?? '—';

        return response()->json([
            'data' => [
                'phone'           => $phone,
                'name'            => $name,
                'summary'         => [
                    'total_bookings'   => $signals['total_bookings'],
                    'completed_count'  => $signals['completed_count'],
                    'first_seen'       => $signals['first_seen'],
                    'is_vip'           => $signals['is_vip'],
                    'is_repeat'        => $signals['is_repeat'],
                    'is_new'           => $signals['is_new'],
                    'has_open_complaint' => $signals['has_open_complaint'],
                    'cancel_risk'      => $signals['cancel_risk'],
                ],
                'bookings'        => $bookings->map(fn(Booking $b) => [
                    'id'             => $b->id,
                    'reference_code' => $b->reference_code,
                    'service_name'   => $b->service?->name,
                    'status'         => $b->status,
                    'appointment_at' => $b->appointment_at?->toIso8601String(),
                    'created_at'     => $b->created_at?->toIso8601String(),
                ])->all(),
                'complaints'      => $complaints->map(fn(Complaint $c) => [
                    'id'             => $c->id,
                    'reference_code' => $c->reference_code,
                    'subject'        => $c->subject,
                    'status'         => $c->status,
                    'priority'       => $c->priority,
                    'created_at'     => $c->created_at?->toIso8601String(),
                ])->all(),
                'price_quotes'    => $quotes->map(fn(PriceQuoteRequest $q) => [
                    'id'           => $q->id,
                    'service_name' => $q->service_name,
                    'status'       => $q->status,
                    'created_at'   => $q->created_at?->toIso8601String(),
                ])->all(),
                'last_activity'   => $lastActivity ? [
                    'action'     => $lastActivity->action,
                    'actor_name' => $lastActivity->actor_name,
                    'created_at' => Carbon::parse($lastActivity->created_at)->toIso8601String(),
                ] : null,
            ],
        ]);
    }
}
