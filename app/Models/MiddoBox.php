<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MiddoBox extends Model
{
    protected $fillable = [
        'qr_code_id',
        'box_model_type',
        'held_by_user_id',
        'asset_status',
        'total_uses_count',
        'last_scanned_at',
    ];

    protected $casts = [
        'total_uses_count' => 'integer',
        'last_scanned_at' => 'datetime',
    ];

    public function heldByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by_user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MiddoBoxLog::class);
    }
}
