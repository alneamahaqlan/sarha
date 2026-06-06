<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A patient re-engagement campaign. v1 is manual: the clinic sends each
 * recipient a personalised WhatsApp message (deep-link) and marks it sent;
 * the campaign tracks progress. No bulk auto-send.
 */
class ClinicCampaign extends Model
{
    use HasFactory;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'clinic_id', 'name', 'message_template', 'audience', 'status',
        'total_recipients', 'sent_count',
        'created_by_type', 'created_by_id', 'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'audience'         => 'array',
            'total_recipients' => 'integer',
            'sent_count'       => 'integer',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function recipients()
    {
        return $this->hasMany(CampaignRecipient::class, 'campaign_id');
    }

    public function createdBy()
    {
        return $this->morphTo('created_by');
    }

    public function scopeForClinic(Builder $q, int $clinicId): Builder
    {
        return $q->where('clinic_id', $clinicId);
    }
}
