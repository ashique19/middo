<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MiddoBankAccount extends Model
{
    protected $fillable = [
        'name',
        'bank_name',
        'account_number',
        'branch',
        'is_default',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(MiddoBankLedgerEntry::class, 'middo_bank_account_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function label(): string
    {
        $parts = array_filter([$this->name, $this->bank_name, $this->account_number]);

        return implode(' · ', $parts);
    }
}
