<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClinicActivityLog;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\PriceQuoteRequest;
use App\Support\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Customer 360 for the Kanban side-panel — backed by the Customer
 * entity from phase 1.
 *
 * URL still takes a phone (kept the contract stable for the frontend);
 * resolves to the Customer row internally. Past bookings / complaints
 * / quotes come from the Customer's relations, so we no longer scan
 * the source tables by phone string.
 */
class CustomerProfileController extends Controller
{
    public function show(string $phone): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();
        $normalized = PhoneNormalizer::normalizeOrSelf($phone);

        $customer = Customer::query()
            ->where('clinic_id', $clinicId)
            ->where('phone', $normalized)
            ->with('platformCustomer.aliases')
            ->first();

        if (! $customer) {
            // Defensive fallback: no Customer row yet (legacy / race).
            return response()->json(['data' => $this->emptyShape($phone)]);
        }

        // Name shown to THIS clinic: the customer's self-registered name
        // once verified, otherwise the earliest name this clinic gave.
        // "Alternative names" are the OTHER names this clinic entered —
        // never names from other clinics (those stay private to them).
        [$displayName, $isVerified, $alternatives] =
            $this->resolveClinicNames($customer, $clinicId);

        $bookings = Booking::query()
            ->where('customer_id', $customer->id)
            ->with('service:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $complaints = Complaint::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $quotes = PriceQuoteRequest::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $lastActivity = ClinicActivityLog::query()
            ->where('clinic_id', $clinicId)
            ->where(function ($q) use ($customer) {
                $q->whereJsonContains('summary->customer_phone', $customer->phone)
                  ->orWhere(function ($w) use ($customer) {
                      $w->where('model_type', Booking::class)
                        ->whereIn('model_id', Booking::where('customer_id', $customer->id)->pluck('id'));
                  });
            })
            ->orderByDesc('created_at')
            ->first();

        return response()->json([
            'data' => [
                'customer_id'   => $customer->id,
                'phone'         => $customer->phone,
                'name'          => $displayName,
                // True when the displayed name is the customer's own
                // OTP-verified platform name (adopted over clinic input).
                'name_verified' => $isVerified,
                // Other names THIS clinic recorded for the same number.
                'alternative_names' => $alternatives,
                'email'         => $customer->email,
                // Phase 3: notes are a thread now. Surface up to 3
                // pinned-first entries for the side panel's quick view.
                'pinned_notes'  => $customer->notes()->limit(3)->get()->map(fn($n) => [
                    'id'              => $n->id,
                    'body'            => $n->body,
                    'is_pinned'       => $n->is_pinned,
                    'created_by_name' => $n->created_by_name,
                    'created_at'      => $n->created_at?->toIso8601String(),
                ])->all(),
                'summary'     => [
                    'total_bookings'     => $customer->total_bookings,
                    'completed_count'    => $customer->completed_bookings,
                    'first_seen'         => $customer->first_seen_at?->toIso8601String(),
                    'is_vip'             => $customer->is_vip,
                    'is_repeat'          => $customer->is_repeat,
                    'is_new'             => $customer->is_new,
                    'has_open_complaint' => $customer->has_prior_complaint,
                    'cancel_risk'        => Booking::where('customer_id', $customer->id)
                        ->whereIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
                        ->count() >= 2,
                ],
                'bookings' => $bookings->map(fn(Booking $b) => [
                    'id'             => $b->id,
                    'reference_code' => $b->reference_code,
                    'service_name'   => $b->service?->name,
                    'status'         => $b->status,
                    'appointment_at' => $b->appointment_at?->toIso8601String(),
                    'created_at'     => $b->created_at?->toIso8601String(),
                ])->all(),
                'complaints' => $complaints->map(fn(Complaint $c) => [
                    'id'             => $c->id,
                    'reference_code' => $c->reference_code,
                    'subject'        => $c->subject,
                    'status'         => $c->status,
                    'priority'       => $c->priority,
                    'created_at'     => $c->created_at?->toIso8601String(),
                ])->all(),
                'price_quotes' => $quotes->map(fn(PriceQuoteRequest $q) => [
                    'id'           => $q->id,
                    'service_name' => $q->service_name,
                    'status'       => $q->status,
                    'created_at'   => $q->created_at?->toIso8601String(),
                ])->all(),
                'last_activity' => $lastActivity ? [
                    'action'     => $lastActivity->action,
                    'actor_name' => $lastActivity->actor_name,
                    'created_at' => Carbon::parse($lastActivity->created_at)->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    /**
     * Resolve what name this clinic should see plus its own alternatives.
     *
     * @return array{0:string,1:bool,2:list<string>}  [displayName, isVerified, alternatives]
     */
    private function resolveClinicNames(Customer $customer, int $clinicId): array
    {
        $platform = $customer->platformCustomer;

        // No unified identity yet (legacy row): fall back to the
        // per-clinic name, no alternatives.
        if (! $platform) {
            return [$customer->name ?: '—', false, []];
        }

        $verified    = (bool) $platform->is_phone_verified;
        $displayName = $platform->displayNameForClinic($clinicId);

        // This clinic's own names, earliest first, excluding whatever is
        // currently shown as the primary. Deduped, order preserved.
        $alternatives = $platform->aliases
            ->where('clinic_id', $clinicId)
            ->sortBy('created_at')
            ->pluck('name')
            ->reject(fn (string $n) => $n === $displayName || $n === '—')
            ->unique()
            ->values()
            ->all();

        return [$displayName ?: '—', $verified, $alternatives];
    }

    private function emptyShape(string $phone): array
    {
        return [
            'customer_id'  => null,
            'phone'        => $phone,
            'name'         => '—',
            'name_verified'     => false,
            'alternative_names' => [],
            'email'        => null,
            'pinned_notes' => [],
            'summary'      => [
                'total_bookings'     => 0, 'completed_count' => 0, 'first_seen' => null,
                'is_vip' => false, 'is_repeat' => false, 'is_new' => true,
                'has_open_complaint' => false, 'cancel_risk' => false,
            ],
            'bookings'     => [],
            'complaints'   => [],
            'price_quotes' => [],
            'last_activity'=> null,
        ];
    }
}
