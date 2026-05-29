<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A platform-level report filed by an end-customer against the platform
 * (bug, suggestion, abuse). When clinic_id is set the report concerns
 * a specific complex but is still routed to admins, not to that
 * complex — the customer→clinic feedback path stays in complaints.
 */
class CustomerReport extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES      = ['bug', 'suggestion', 'clinic_concern', 'inappropriate', 'other'];
    public const PRIORITIES = ['low', 'medium', 'high'];
    public const STATUSES   = ['new', 'in_review', 'resolved', 'rejected'];

    protected $fillable = [
        'reference_code', 'user_id', 'clinic_id',
        'type', 'priority', 'status',
        'subject', 'description',
        'admin_notes', 'resolution',
        'assigned_admin_id', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function user()           { return $this->belongsTo(User::class); }
    public function clinic()         { return $this->belongsTo(Clinic::class); }
    public function assignedAdmin()  { return $this->belongsTo(Admin::class, 'assigned_admin_id'); }
}
