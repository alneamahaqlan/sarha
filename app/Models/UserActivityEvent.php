<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per "meaningful" user action the existing domain tables
 * don't already record. The super-admin "comprehensive user profile"
 * timeline unions this table with bookings / complaints / AI logs /
 * favorites / quotes to build the full activity feed.
 *
 * Event types in v1:
 *   - auth:     login, logout, otp_verified
 *   - browse:   search, filter, clinic_view, compare
 *   - intent:   action_click (whatsapp/call/directions/book)
 *   - account:  account_edit
 */
class UserActivityEvent extends Model
{
    use HasFactory;

    // Stable type constants the logger + filter both depend on. New
    // types can be added by appending here AND wiring an icon/label
    // on the React side; nothing else needs to change.
    public const TYPE_LOGIN          = 'login';
    public const TYPE_LOGOUT         = 'logout';
    public const TYPE_OTP_VERIFIED   = 'otp_verified';
    public const TYPE_SEARCH         = 'search';
    public const TYPE_FILTER         = 'filter';
    public const TYPE_CLINIC_VIEW    = 'clinic_view';
    public const TYPE_COMPARE        = 'compare';
    public const TYPE_ACTION_CLICK   = 'action_click';
    public const TYPE_ACCOUNT_EDIT   = 'account_edit';

    public const ALL_TYPES = [
        self::TYPE_LOGIN, self::TYPE_LOGOUT, self::TYPE_OTP_VERIFIED,
        self::TYPE_SEARCH, self::TYPE_FILTER, self::TYPE_CLINIC_VIEW,
        self::TYPE_COMPARE, self::TYPE_ACTION_CLICK, self::TYPE_ACCOUNT_EDIT,
    ];

    protected $fillable = [
        'user_id', 'type', 'title', 'details',
        'related_clinic_id', 'related_url', 'visit_session_id',
        'ip', 'user_agent', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'details'     => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function relatedClinic()
    {
        return $this->belongsTo(Clinic::class, 'related_clinic_id');
    }

    public function visitSession()
    {
        return $this->belongsTo(UserVisitSession::class, 'visit_session_id');
    }
}
