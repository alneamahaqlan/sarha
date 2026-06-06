<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\UpdateCustomerRequest;
use App\Http\Resources\Api\V1\CustomerListResource;
use App\Http\Resources\Api\V1\CustomerProfileResource;
use App\Models\Booking;
use App\Models\ClinicActivityLog;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\CustomerReminder;
use App\Models\PriceQuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Customers Hub backend: list + filter + segment counts + profile +
 * customer-scoped tabs (bookings, complaints, quotes, timeline).
 *
 * Always scoped to auth('clinic')->id() — multi-tenant isolation is
 * absolute. Permissions enforced via the clinic.role middleware on
 * the routes (customers.view for read paths, customers.manage for
 * the PATCH).
 */
class CustomersController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $clinicId = (int) auth('clinic')->id();
        $q = $this->baseQuery($clinicId, $request);

        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);
        $rows = $q
            ->with(['tags:id,customer_id,label,color', 'notes:id,customer_id'])
            ->orderByDesc('last_interaction_at')
            ->paginate($perPage)
            ->withQueryString();

        return CustomerListResource::collection($rows);
    }

    /**
     * 5 counts powering the top stats bar AND the segments view.
     * Single query each — uses the composite indexes added in phase 2.
     */
    public function stats(): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();
        $monthStart = Carbon::now()->startOfMonth();
        $inactiveThreshold = Carbon::now()->subDays(90);

        $base = Customer::query()->where('clinic_id', $clinicId);

        $total = (clone $base)->count();
        $vip = (clone $base)->where('completed_bookings', '>=', Customer::VIP_THRESHOLD)->count();
        $repeat = (clone $base)
            ->whereBetween('completed_bookings', [Customer::REPEAT_THRESHOLD, Customer::VIP_THRESHOLD - 1])
            ->count();
        $newMonth = (clone $base)->where('first_seen_at', '>=', $monthStart)->count();
        $hasComplaint = (clone $base)->where('total_complaints', '>', 0)->count();
        $inactive = (clone $base)
            ->where(function ($w) use ($inactiveThreshold) {
                $w->where('last_interaction_at', '<', $inactiveThreshold)
                  ->orWhereNull('last_interaction_at');
            })
            ->count();
        $avgBookings = $total > 0
            ? round((clone $base)->sum('total_bookings') / $total, 1)
            : 0;

        return response()->json([
            'data' => [
                'total'          => $total,
                'vip'            => $vip,
                'repeat'         => $repeat,
                'new_month'      => $newMonth,
                'has_complaint'  => $hasComplaint,
                'inactive_90d'   => $inactive,
                'avg_bookings'   => $avgBookings,
            ],
        ]);
    }

    public function show(Customer $customer): CustomerProfileResource
    {
        $this->ensureClinicOwns($customer);
        $customer->load(['tags:id,customer_id,label,color']);
        return new CustomerProfileResource($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerProfileResource
    {
        $this->ensureClinicOwns($customer);
        $customer->update($request->validated());
        return new CustomerProfileResource($customer->fresh()->load(['tags:id,customer_id,label,color']));
    }

    /**
     * Live-aggregated activity feed: bookings + complaints + quotes
     * synthesized as events plus the ClinicActivityLog rows whose
     * subject is one of this customer's bookings.
     */
    public function timeline(Customer $customer, Request $request): JsonResponse
    {
        $this->ensureClinicOwns($customer);
        $limit = min(max((int) $request->input('limit', 50), 1), 200);

        $events = collect();

        // Bookings — one event per booking creation.
        Booking::query()
            ->where('customer_id', $customer->id)
            ->with('service:id,name')
            ->orderByDesc('created_at')
            ->limit(40)
            ->get()
            ->each(function (Booking $b) use ($events) {
                $events->push([
                    'kind'       => 'booking',
                    'at'         => $b->created_at,
                    'title_key'  => 'customer.timeline.booking_created',
                    'reference'  => $b->reference_code,
                    'status'     => $b->status,
                    'detail'     => $b->service?->name,
                    'link_to'    => null, // future: link to Kanban card
                ]);
            });

        Complaint::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->each(function (Complaint $c) use ($events) {
                $events->push([
                    'kind'      => 'complaint',
                    'at'        => $c->created_at,
                    'title_key' => 'customer.timeline.complaint_filed',
                    'reference' => $c->reference_code,
                    'status'    => $c->status,
                    'detail'    => $c->subject,
                    'link_to'   => null,
                ]);
            });

        PriceQuoteRequest::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->each(function (PriceQuoteRequest $q) use ($events) {
                $events->push([
                    'kind'      => 'quote_request',
                    'at'        => $q->created_at,
                    'title_key' => 'customer.timeline.quote_requested',
                    'reference' => null,
                    'status'    => $q->status,
                    'detail'    => $q->service_name,
                    'link_to'   => null,
                ]);
            });

        // Contact reminders set for this customer (one event each).
        CustomerReminder::query()
            ->where('customer_id', $customer->id)
            ->with('assigneeMember:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->each(function (CustomerReminder $r) use ($events) {
                $events->push([
                    'kind'      => 'reminder',
                    'at'        => $r->created_at,
                    'title_key' => 'customer.timeline.reminder_set',
                    'reference' => null,
                    'status'    => $r->status,
                    'detail'    => $r->note ?: $r->assigneeMember?->name,
                    'link_to'   => null,
                ]);
            });

        // Team activities on this customer's bookings.
        $bookingIds = Booking::where('customer_id', $customer->id)->pluck('id');
        if ($bookingIds->isNotEmpty()) {
            ClinicActivityLog::query()
                ->where('clinic_id', $customer->clinic_id)
                ->where('model_type', Booking::class)
                ->whereIn('model_id', $bookingIds)
                ->orderByDesc('created_at')
                ->limit(40)
                ->get()
                ->each(function (ClinicActivityLog $log) use ($events) {
                    $events->push([
                        'kind'        => 'team_action',
                        'at'          => $log->created_at,
                        'title_key'   => "clinic_team_activity.action.{$log->action}",
                        'action_slug' => $log->action,
                        'actor_name'  => $log->actor_name,
                        'actor_role'  => $log->actor_role,
                        'summary'     => $log->summary,
                    ]);
                });
        }

        $sorted = $events
            ->sortByDesc(fn($e) => $e['at']?->getTimestamp() ?? 0)
            ->take($limit)
            ->values()
            ->map(function ($e) {
                $e['at'] = $e['at']?->toIso8601String();
                return $e;
            });

        return response()->json(['data' => $sorted->all()]);
    }

    public function bookings(Customer $customer): JsonResponse
    {
        $this->ensureClinicOwns($customer);
        $rows = Booking::query()
            ->where('customer_id', $customer->id)
            ->with(['service:id,name', 'assignee'])
            ->orderByDesc('appointment_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn(Booking $b) => [
                'id'             => $b->id,
                'reference_code' => $b->reference_code,
                'service_name'   => $b->service?->name,
                'status'         => $b->status,
                'appointment_at' => $b->appointment_at?->toIso8601String(),
                'created_at'     => $b->created_at?->toIso8601String(),
                'assignee_name'  => $b->assignee?->name,
            ]);
        return response()->json(['data' => $rows]);
    }

    public function complaints(Customer $customer): JsonResponse
    {
        $this->ensureClinicOwns($customer);
        $rows = Complaint::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn(Complaint $c) => [
                'id'             => $c->id,
                'reference_code' => $c->reference_code,
                'subject'        => $c->subject,
                'status'         => $c->status,
                'priority'       => $c->priority,
                'created_at'     => $c->created_at?->toIso8601String(),
            ]);
        return response()->json(['data' => $rows]);
    }

    // NOTE: priceQuotes() was removed — a customer's price-quote history is
    // restricted to the platform admin and must not be exposed to clinics.

    // ---------- internal ----------

    private function baseQuery(int $clinicId, Request $request)
    {
        // Segment/range/window/search logic lives in CustomerSegmentFilter so
        // the Hub list and campaign audience resolution stay in lockstep.
        $q = Customer::query()->where('clinic_id', $clinicId);

        return \App\Services\CustomerSegmentFilter::apply($q, [
            'search'           => $request->input('search'),
            'segment'          => $request->input('segment'),
            'booking_range'    => $request->input('booking_range'),
            'has_notes'        => $request->boolean('has_notes'),
            'last_interaction' => $request->input('last_interaction'),
        ]);
    }

    private function ensureClinicOwns(Customer $customer): void
    {
        $clinicId = (int) auth('clinic')->id();
        abort_unless($customer->clinic_id === $clinicId, 404);
    }
}
