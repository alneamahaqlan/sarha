<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One customer inside a campaign, snapshotted at creation. Tracks its own
 * send status so the campaign can show progress.
 */
class CampaignRecipient extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT    = 'sent';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'campaign_id', 'customer_id',
        'name_snapshot', 'phone_snapshot',
        'status', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function campaign()
    {
        return $this->belongsTo(ClinicCampaign::class, 'campaign_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeForStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }
}
