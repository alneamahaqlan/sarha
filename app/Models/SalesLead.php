<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesLead extends Model
{
    use HasFactory, SoftDeletes;

    /** Acquisition channels the lead came through. */
    public const SOURCES = [
        'referral', 'website', 'whatsapp', 'instagram', 'snapchat',
        'tiktok', 'cold_call', 'event', 'walk_in', 'other',
    ];

    /** Analysable reasons a lead was marked lost. */
    public const LOST_REASONS = [
        'price', 'no_response', 'chose_competitor', 'not_interested',
        'not_qualified', 'bad_timing', 'other',
    ];

    /** Terminal statuses — no follow-up reminders, score forced to extremes. */
    public const TERMINAL_STATUSES = ['converted', 'lost'];

    protected $fillable = [
        'clinic_name', 'contact_name', 'phone', 'email', 'license_number',
        'city_id', 'district', 'address',
        'status', 'source', 'lost_reason', 'lost_notes', 'notes', 'sales_notes',
        'assigned_to', 'next_follow_up_at', 'last_contact_at', 'followup_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'next_follow_up_at'    => 'datetime',
            'last_contact_at'      => 'datetime',
            'followup_notified_at' => 'datetime',
        ];
    }

    public function activities()
    {
        return $this->hasMany(SalesLeadActivity::class)->latest();
    }

    /**
     * Transparent rule-based lead score (0–100). Driven by pipeline stage,
     * engagement (logged activities), contact recency, profile completeness,
     * minus a penalty for an overdue follow-up. Computed — never stored — so
     * it can't drift from the signals it summarises.
     */
    public function getScoreAttribute(): int
    {
        if ($this->status === 'converted') return 100;
        if ($this->status === 'lost')      return 0;

        $stage = match ($this->status) {
            'negotiating' => 75,
            'interested'  => 50,
            'contacted'   => 25,
            default       => 10, // new
        };

        // Engagement: +5 per logged activity, capped at +20.
        $activityCount = $this->activities_count
            ?? ($this->relationLoaded('activities') ? $this->activities->count() : 0);
        $engagement = min((int) $activityCount * 5, 20);

        $recency = $this->last_contact_at && $this->last_contact_at->gt(now()->subDays(7)) ? 10 : 0;
        $profile = ($this->email ? 5 : 0) + ($this->license_number ? 5 : 0);
        $overdue = $this->isOverdueFollowup() ? -15 : 0;

        return max(0, min(100, $stage + $engagement + $recency + $profile + $overdue));
    }

    /** hot ≥ 70 · warm ≥ 40 · cold < 40 — drives the UI badge. */
    public function getScoreTierAttribute(): string
    {
        $s = $this->score;
        return $s >= 70 ? 'hot' : ($s >= 40 ? 'warm' : 'cold');
    }

    public function isOverdueFollowup(): bool
    {
        return $this->next_follow_up_at
            && $this->next_follow_up_at->isPast()
            && ! in_array($this->status, ['converted', 'lost']);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }
}
