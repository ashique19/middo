<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    /**
     * Get all areas belonging to this city.
     */
    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }
}
