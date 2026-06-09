<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ClinicCampaignResource extends JsonResource
{
    public function toArray($request): array
    {
        $total = (int) $this->total_recipients;
        $sent  = (int) $this->sent_count;

        return [
            'id'               => $this->id,
            'type'             => $this->type,
            'name'             => $this->name,
            'message_template' => $this->message_template,
            'image_path'       => $this->image_path,
            'image_url'        => $this->image_path ? Storage::url($this->image_path) : null,
            'audience'         => $this->audience,
            'status'           => $this->status,
            'managed_status'   => $this->managed_status,
            'total_recipients' => $total,
            'sent_count'       => $sent,
            'progress'         => $total > 0 ? (int) round($sent / $total * 100) : 0,
            'created_by_name'  => $this->created_by_name,
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
