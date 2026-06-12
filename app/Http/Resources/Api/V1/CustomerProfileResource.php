<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Booking;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full profile shape for the Customer Hub profile page. Heavier
 * than the list resource — includes the last booking summary, total
 * value of completed services, and tag/note relations.
 */
class CustomerProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        $lastBooking = $this->bookings()
            ->with(['service:id,name', 'assignee'])
            ->orderByDesc('appointment_at')
            ->orderByDesc('created_at')
            ->first();

        // Sum of the NET price of services taken on this customer's
        // completed bookings — "إجمالي قيمة الخدمات" (reflects the
        // per-customer discounts the team entered, not the catalog price).
        $totalValue = (float) \App\Models\BookingService::query()
            ->whereIn('booking_id', Booking::query()
                ->where('customer_id', $this->id)
                ->where('status', Booking::STATUS_COMPLETED)
                ->select('id'))
            ->sum('net_price');

        $cancelledCount = (int) Booking::query()
            ->where('customer_id', $this->id)
            ->whereIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->count();

        // Active bookings of this customer that still have no services
        // recorded — drives the "complete the data" banner on the profile.
        $incompleteBookings = (int) Booking::query()
            ->where('customer_id', $this->id)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->whereDoesntHave('services')
            ->count();

        $interestedServices = $this->interestedServices()
            ->with('service:id,name')
            ->get()
            ->map(fn($i) => ['id' => $i->service_id, 'name' => $i->service?->name])
            ->all();

        return [
            'id'                   => $this->id,
            'clinic_id'            => $this->clinic_id,
            'name'                 => $this->name,
            'phone'                => $this->phone,
            'email'                => $this->email,
            'marketing_opt_out'    => (bool) $this->marketing_opt_out,
            'user_id'              => $this->user_id,
            'first_seen_at'        => $this->first_seen_at?->toIso8601String(),
            'last_seen_at'         => $this->last_seen_at?->toIso8601String(),
            'last_interaction_at'  => $this->last_interaction_at?->toIso8601String(),
            'last_interaction_type'=> $this->last_interaction_type,
            'follow_up_priority'   => (int) $this->follow_up_priority,
            'auto_tags' => [
                'is_vip'              => $this->is_vip,
                'is_repeat'           => $this->is_repeat,
                'is_new'              => $this->is_new,
                'has_prior_complaint' => $this->has_prior_complaint,
            ],
            'totals' => [
                'bookings'             => $this->total_bookings,
                'completed_bookings'   => $this->completed_bookings,
                'cancelled_bookings'   => $cancelledCount,
                'complaints'           => $this->total_complaints,
                'quote_requests'       => $this->total_quote_requests,
                'service_value'        => $totalValue,
                'incomplete_bookings'  => $incompleteBookings,
            ],
            'interested_services' => $interestedServices,
            'last_booking' => $lastBooking ? [
                'id'             => $lastBooking->id,
                'reference_code' => $lastBooking->reference_code,
                'service_name'   => $lastBooking->service?->name,
                'status'         => $lastBooking->status,
                'appointment_at' => $lastBooking->appointment_at?->toIso8601String(),
                'assignee_name'  => $lastBooking->assignee?->name,
            ] : null,
            'tags' => $this->whenLoaded('tags', fn() => $this->tags->map(fn($t) => [
                'id'    => $t->id,
                'label' => $t->label,
                'color' => $t->color,
            ])->all(), []),
        ];
    }
}
