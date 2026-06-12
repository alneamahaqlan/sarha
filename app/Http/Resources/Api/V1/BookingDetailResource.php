<?php

namespace App\Http\Resources\Api\V1;

use App\Services\CustomerInsightService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full payload for the Kanban side-panel — booking detail + customer
 * insights + assignee + tags. Timeline is fetched via the activity
 * controller. Customer notes / counters come from the linked
 * Customer entity (phase 1).
 */
class BookingDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var CustomerInsightService $insights */
        $insights = app(CustomerInsightService::class);
        $insights->preload($this->clinic_id, collect([$this->resource]));

        $signals      = $insights->insightsFor($this->resource);
        $suggest      = $insights->suggestionsFor($this->id);
        $heat         = $insights->heatFor($this->resource, $suggest);
        $customerTags = $insights->customerTagsFor($this->resource);
        $customer     = $insights->customerFor($this->resource);

        return [
            'id'             => $this->id,
            'customer_id'    => $this->customer_id,
            'reference_code' => $this->reference_code,
            'customer_name'  => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'notes'          => $this->notes,
            'clinic_notes'   => $this->clinic_notes,
            'status'         => $this->status,
            'kanban_column'  => $this->kanbanColumn(),
            'appointment_at' => $this->appointment_at?->toIso8601String(),
            'source'         => $this->source,
            'acquisition_source' => $this->acquisition_source,
            'is_for_relative'=> ! is_null($this->relative_id),
            'service'        => $this->whenLoaded('service', fn() => $this->service ? [
                'id'   => $this->service->id,
                'name' => $this->service->name,
                'price'=> $this->service->price ?? null,
            ] : null),
            // Service line items taken on this card + the card's net value.
            'services'       => $this->whenLoaded('services', fn() => $this->services->map(fn($ln) => [
                'id'           => $ln->id,
                'service_id'   => $ln->service_id,
                'service_name' => $ln->service?->name,
                'list_price'   => $ln->list_price !== null ? (float) $ln->list_price : null,
                'net_price'    => (float) $ln->net_price,
            ])->all(), []),
            'value'          => (float) ($this->relationLoaded('services') ? $this->services->sum('net_price') : 0),
            'is_incomplete'  => $this->isIncomplete(),
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
            // Customer notes moved to a dedicated thread endpoint in
            // phase 3 — the side panel fetches them via
            // /clinic/customers/{id}/notes when opened.
            'suggestions'    => $suggest,
            'heat'           => $heat,
            'tags'           => $this->whenLoaded('tags', fn() => $this->tags->map(fn($t) => [
                'id'    => $t->id,
                'label' => $t->label,
                'color' => $t->color,
                'scope' => 'booking',
            ])->all(), []),
            'customer_tags'  => collect($customerTags)
                ->map(fn($t) => [
                    'id'    => $t->id,
                    'label' => $t->label,
                    'color' => $t->color,
                    'scope' => 'customer',
                ])->all(),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
            'created_by_name' => $this->created_by_name,
            'follow_up_priority' => (int) ($this->customer?->follow_up_priority ?? 0),
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
