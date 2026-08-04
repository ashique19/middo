<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'city_id'])]
class Area extends Model
{
    /**
     * Get the city that this area belongs to.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
