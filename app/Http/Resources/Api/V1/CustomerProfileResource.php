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

        // Sum of completed-booking service prices — "إجمالي قيمة
        // الخدمات" surfaces only when services have a non-null price.
        $totalValue = (float) Booking::query()
            ->where('customer_id', $this->id)
            ->where('status', Booking::STATUS_COMPLETED)
            ->join('services', 'services.id', '=', 'bookings.service_id')
            ->sum('services.price');

        $cancelledCount = (int) Booking::query()
            ->where('customer_id', $this->id)
            ->whereIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->count();

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
            ],
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
