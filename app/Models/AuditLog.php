<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = true;
    public const UPDATED_AT = null;

    protected $fillable = [
        'admin_id', 'admin_name', 'action', 'model_type',
        'model_id', 'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function auditable()
    {
        return $this->morphTo('model');
    }
}
