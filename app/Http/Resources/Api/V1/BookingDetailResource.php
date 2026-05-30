<?php

namespace App\Http\Resources\Api\V1;

use App\Services\CustomerInsightService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full payload for the Kanban side-panel — booking detail + customer
 * 360 summary + assignee + tags. The Timeline (activity log) is
 * fetched via /booking-activities to keep this responseu cacheable.
 */
class BookingDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var CustomerInsightService $insights */
        $insights = app(CustomerInsightService::class);
        $insights->preload($this->clinic_id, collect([$this->resource]));

        $phone   = (string) $this->customer_phone;
        $signals = $insights->insightsFor($phone);
        $suggest = $insights->suggestionsFor($this->id);
        $heat    = $insights->heatFor($phone, $suggest);

        return [
            'id'             => $this->id,
            'reference_code' => $this->reference_code,
            'customer_name'  => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'notes'          => $this->notes,
            'clinic_notes'   => $this->clinic_notes,
            'status'         => $this->status,
            'kanban_column'  => $this->kanbanColumn(),
            'appointment_at' => $this->appointment_at?->toIso8601String(),
            'source'         => $this->source,
            'is_for_relative'=> ! is_null($this->relative_id),
            'service'        => $this->whenLoaded('service', fn() => $this->service ? [
                'id'   => $this->service->id,
                'name' => $this->service->name,
                'price'=> $this->service->price ?? null,
            ] : null),
            'booker'         => $this->whenLoaded('booker', fn() => $this->booker ? [
                'id'    => $this->booker->id,
                'name'  => $this->booker->name,
                'phone' => $this->booker->phone,
            ] : null),
            'relative'       => $this->whenLoaded('relative', fn() => $this->relative ? [
                'id'                 => $this->relative->id,
                'name'               => $this->relative->name,
                'relationship_type'  => $this->relative->relationship_type,
                'relationship_label' => $this->relative->relationship_label,
                'phone'              => $this->relative->phone,
            ] : null),
            'assignee'       => $this->assigneePayload(),
            'auto_tags'      => [
                'is_vip'             => $signals['is_vip'],
                'is_repeat'          => $signals['is_repeat'],
                'is_new'             => $signals['is_new'],
                'has_open_complaint' => $signals['has_open_complaint'],
                'cancel_risk'        => $signals['cancel_risk'],
                'completed_count'    => $signals['completed_count'],
                'total_bookings'     => $signals['total_bookings'],
                'first_seen'         => $signals['first_seen'],
            ],
            'suggestions'    => $suggest,
            'heat'           => $heat,
            'tags'           => $this->whenLoaded('tags', fn() => $this->tags->map(fn($t) => [
                'id'    => $t->id,
                'label' => $t->label,
                'color' => $t->color,
                'scope' => 'booking',
            ])->all(), []),
            'customer_tags'  => collect($insights->customerTagsFor($this->clinic_id, $phone))
                ->map(fn($t) => [
                    'id'    => $t->id,
                    'label' => $t->label,
                    'color' => $t->color,
                    'scope' => 'customer',
                ])->all(),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }

    private function kanbanColumn(): string
    {
        foreach (\App\Models\Booking::KANBAN_GROUPS as $col => $statuses) {
            if (in_array($this->status, $statuses, true)) {
                return $col;
            }
        }
        return 'new';
    }

    private function assigneePayload(): ?array
    {
        if (! $this->assignee_id || ! $this->assignee_type) return null;
        $assignee = $this->assignee;
        if (! $assignee) return null;

        return [
            'type'  => class_basename($this->assignee_type),
            'id'    => $assignee->getKey(),
            'name'  => $assignee->name ?? '—',
            'role'  => $assignee instanceof \App\Models\ClinicTeamMember
                ? $assignee->role
                : 'owner',
        ];
    }
}
