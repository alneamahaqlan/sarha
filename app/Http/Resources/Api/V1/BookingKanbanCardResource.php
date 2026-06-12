<?php

namespace App\Http\Resources\Api\V1;

use App\Services\CustomerInsightService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight card shape rendered on each Kanban column. Heavy detail
 * (Timeline, customer 360, full booker block) is fetched separately
 * on side-panel open to keep the board load small.
 *
 * Phase 2: derives signals from the Customer entity (preloaded by
 * the caller via CustomerInsightService::preload).
 */
class BookingKanbanCardResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var CustomerInsightService $insights */
        $insights = app(CustomerInsightService::class);
        $signals = $insights->insightsFor($this->resource);
        $suggest = $insights->suggestionsFor($this->id);
        $heat    = $insights->heatFor($this->resource, $suggest);
        $customerTags = $insights->customerTagsFor($this->resource);

        return [
            'id'             => $this->id,
            'customer_id'    => $this->customer_id,
            'reference_code' => $this->reference_code,
            'customer_name'  => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'service'        => $this->whenLoaded('service', fn() => $this->service ? [
                'id'   => $this->service->id,
                'name' => $this->service->name,
            ] : null),
            'value'          => (float) ($this->services_value ?? 0),
            'services_count' => (int) ($this->services_count ?? 0),
            'is_incomplete'  => $this->isIncomplete(),
            'status'         => $this->status,
            'kanban_column'  => $this->kanbanColumn(),
            'sub_badge'      => $this->subBadge(),
            'appointment_at' => $this->appointment_at?->toIso8601String(),
            'created_at'     => $this->created_at?->toIso8601String(),
            'acquisition_source' => $this->acquisition_source,
            'stage_id'       => $this->stage_id,
            'follow_up_priority' => (int) ($this->customer?->follow_up_priority ?? 0),
            'is_for_relative'=> ! is_null($this->relative_id),
            'auto_tags'      => [
                'is_vip'             => $signals['is_vip'],
                'is_repeat'          => $signals['is_repeat'],
                'is_new'             => $signals['is_new'],
                'has_open_complaint' => $signals['has_open_complaint'],
                'cancel_risk'        => $signals['cancel_risk'],
            ],
            'suggestions'    => $suggest,
            'heat'           => $heat,
            'assignee'       => $this->assigneePayload(),
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

    private function subBadge(): ?string
    {
        $now = now();

        return match ($this->status) {
            \App\Models\Booking::STATUS_NEW       => 'awaiting_contact',
            \App\Models\Booking::STATUS_CONTACTED => 'contacted',
            \App\Models\Booking::STATUS_APPOINTMENT_SET => $this->appointment_at
                ? ($this->appointment_at->isToday() ? 'today'
                    : ($this->appointment_at->lte($now->copy()->addHours(48)) ? 'within_48h'
                        : ($this->appointment_at->lte($now->copy()->addDays(7)) ? 'within_week' : 'scheduled')))
                : 'scheduled',
            \App\Models\Booking::STATUS_COMPLETED => 'attended',
            \App\Models\Booking::STATUS_NO_SHOW   => 'no_show',
            \App\Models\Booking::STATUS_CANCELLED => 'cancelled',
            default => null,
        };
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
