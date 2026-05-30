<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerNote extends Model
{
    protected $fillable = [
        'customer_id', 'body', 'is_pinned',
        'created_by_type', 'created_by_id', 'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned'  => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->morphTo('created_by');
    }
}
