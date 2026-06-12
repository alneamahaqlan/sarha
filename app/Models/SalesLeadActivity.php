<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only timeline entry on a SalesLead. Written both by the admin
 * (manual call/whatsapp/email/note/meeting) and automatically by the
 * controller on create + status changes.
 *
 * Immutable: no edit endpoints. admin_name is a snapshot so removing the
 * admin later keeps the row readable.
 */
class SalesLeadActivity extends Model
{
    /** Manual entry types the admin can log. */
    public const TYPE_NOTE     = 'note';
    public const TYPE_CALL     = 'call';
    public const TYPE_WHATSAPP = 'whatsapp';
    public const TYPE_EMAIL    = 'email';
    public const TYPE_MEETING  = 'meeting';
    /** System-generated on status transitions. */
    public const TYPE_STATUS_CHANGE = 'status_change';

    /** Manual types an admin may POST. */
    public const MANUAL_TYPES = [
        self::TYPE_NOTE, self::TYPE_CALL, self::TYPE_WHATSAPP,
        self::TYPE_EMAIL, self::TYPE_MEETING,
    ];

    /** Types that count as a real outreach → bump last_contact_at. */
    public const CONTACT_TYPES = [
        self::TYPE_CALL, self::TYPE_WHATSAPP, self::TYPE_EMAIL, self::TYPE_MEETING,
    ];

    protected $fillable = [
        'sales_lead_id', 'admin_id', 'admin_name', 'type', 'body', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta'       => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function lead()
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
