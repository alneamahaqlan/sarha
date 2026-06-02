<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A customer's saved service or offer (polymorphic). Clinic favourites
 * keep their own `favorites` pivot — this table covers the entities that
 * can be deleted/expire yet must stay visible in the saved list.
 */
class SavedItem extends Model
{
    protected $fillable = ['user_id', 'favoritable_type', 'favoritable_id'];

    /** Resolve even soft-deleted targets so "محذوف" items still render. */
    public function favoritable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }
}
