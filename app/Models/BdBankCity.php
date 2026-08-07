<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BdBankCity extends Model
{
    protected $fillable = [
        'bd_bank_id',
        'name',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(BdBank::class, 'bd_bank_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(BdBankBranch::class)->orderBy('name');
    }
}
