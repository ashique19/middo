<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenRatingLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'kitchen_id',
        'actor_id',
        'old_rating',
        'new_rating',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_rating' => 'integer',
            'new_rating' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kitchen_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
