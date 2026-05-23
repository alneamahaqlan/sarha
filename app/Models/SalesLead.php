<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesLead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'clinic_name', 'contact_name', 'phone', 'email', 'license_number',
        'city_id', 'district', 'address',
        'status', 'notes', 'sales_notes',
        'assigned_to', 'next_follow_up_at', 'last_contact_at',
    ];

    protected function casts(): array
    {
        return [
            'next_follow_up_at' => 'datetime',
            'last_contact_at'   => 'datetime',
        ];
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
