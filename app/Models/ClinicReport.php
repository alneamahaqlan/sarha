<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A clinic-side report raised AGAINST the platform (not a customer
 * complaint against the clinic). Reports are routed to the admin review
 * queue independent of complaints so the two concerns don't mix.
 */
class ClinicReport extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES      = ['technical', 'abusive_customer', 'fake_review', 'billing', 'suggestion', 'other'];
    public const PRIORITIES = ['low', 'medium', 'high'];
    public const STATUSES   = ['new', 'in_review', 'resolved', 'rejected'];

    protected $fillable = [
        'reference_code', 'clinic_id',
        'type', 'priority', 'status',
        'subject', 'description',
        'admin_notes', 'resolution',
        'assigned_admin_id', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }
}
