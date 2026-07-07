<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    public static function nextQrCodeNumber(): int
    {
        $prefix = 'MB-';

        $maxNumber = static::query()
            ->where('qr_code_id', 'like', $prefix.'%')
            ->pluck('qr_code_id')
            ->map(function (string $qrCodeId) use ($prefix) {
                if (! preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', $qrCodeId, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max();

        return ($maxNumber ?? 0) + 1;
    }

    public static function generateBatch(int $count): void
    {
        DB::transaction(function () use ($count) {
            $prefix = 'MB-';
            $nextNumber = static::nextQrCodeNumber();

            for ($i = 0; $i < $count; $i++) {
                $box = static::create([
                    'qr_code_id' => $prefix.str_pad((string) ($nextNumber + $i), 6, '0', STR_PAD_LEFT),
                    'box_model_type' => 'standard_insulated',
                    'asset_status' => 'at_middo_warehouse',
                    'total_uses_count' => 0,
                ]);

                MiddoBoxLog::create([
                    'middo_box_id' => $box->id,
                    'custody_status' => 'warehouse',
                    'log_action' => 'registered_at_warehouse',
                ]);
            }
        });
    }
}
