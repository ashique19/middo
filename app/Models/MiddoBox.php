<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MiddoBox extends Model
{
    protected $fillable = [
        'qr_code_id',
        'box_model_type',
        'held_by_user_id',
        'pickup_rider_id',
        'kitchen_id',
        'return_kitchen_id',
        'asset_status',
        'ready_for_pickup',
        'ready_for_pickup_at',
        'total_uses_count',
        'unit_cost_bdt',
        'last_scanned_at',
    ];

    protected $casts = [
        'total_uses_count' => 'integer',
        'unit_cost_bdt' => 'integer',
        'last_scanned_at' => 'datetime',
        'ready_for_pickup' => 'boolean',
        'ready_for_pickup_at' => 'datetime',
    ];

    public function heldByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by_user_id');
    }

    public function pickupRider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pickup_rider_id');
    }

    public function returnKitchen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_kitchen_id');
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kitchen_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MiddoBoxLog::class);
    }

    public function orderMiddoBoxes(): HasMany
    {
        return $this->hasMany(OrderMiddoBox::class);
    }

    public function requestBox(): HasOne
    {
        return $this->hasOne(KitchenBoxRequestBox::class);
    }

    public function warehouseHandoff(): HasOne
    {
        // Prefer the newest handoff row (a box can accumulate history over many returns).
        return $this->hasOne(KitchenWarehouseHandoff::class)->latestOfMany();
    }

    public function hasOpenWarehouseHandoff(): bool
    {
        $link = $this->relationLoaded('warehouseHandoff')
            ? $this->warehouseHandoff
            : $this->warehouseHandoff()->first();

        return $link !== null && in_array($link->status, KitchenWarehouseHandoff::openStatuses(), true);
    }

    public function isStagedForWarehousePickup(): bool
    {
        $link = $this->relationLoaded('warehouseHandoff')
            ? $this->warehouseHandoff
            : $this->warehouseHandoff()->first();

        return $link !== null
            && $this->isAtKitchen()
            && in_array($link->status, [
                KitchenWarehouseHandoff::STATUS_RUN_REQUESTED,
                KitchenWarehouseHandoff::STATUS_RUN_CLAIMED,
                KitchenWarehouseHandoff::STATUS_DISPATCHED,
            ], true);
    }

    /**
     * Free warehouse stock that can be staged against a kitchen box request.
     */
    public function scopeAvailableForKitchenStaging(Builder $query): Builder
    {
        return $query
            ->where('asset_status', 'at_middo_warehouse')
            ->whereNull('held_by_user_id')
            ->whereNull('kitchen_id')
            ->whereDoesntHave('requestBox', fn (Builder $q) => $q->openHandoff());
    }

    public function scopeStagedForKitchenPickup(Builder $query): Builder
    {
        return $query
            ->where('asset_status', 'at_middo_warehouse')
            ->whereNull('held_by_user_id')
            ->whereHas('requestBox', fn (Builder $q) => $q->where('status', KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP));
    }

    public function isAvailableForKitchenStaging(): bool
    {
        if ($this->asset_status !== 'at_middo_warehouse') {
            return false;
        }

        if ($this->held_by_user_id !== null || $this->kitchen_id !== null) {
            return false;
        }

        $link = $this->relationLoaded('requestBox')
            ? $this->requestBox
            : $this->requestBox()->first();

        return ! ($link && $link->isOpenHandoff());
    }

    public function isStagedForKitchenPickup(): bool
    {
        if ($this->asset_status !== 'at_middo_warehouse' || $this->held_by_user_id !== null) {
            return false;
        }

        $link = $this->relationLoaded('requestBox')
            ? $this->requestBox
            : $this->requestBox()->first();

        return $link?->status === KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP;
    }

    public function scopeAtKitchen(Builder $query, int $kitchenId): Builder
    {
        return $query
            ->where('kitchen_id', $kitchenId)
            ->where('held_by_user_id', $kitchenId);
    }

    public function scopeSendableAtKitchen(Builder $query, int $kitchenId): Builder
    {
        return $query
            ->atKitchen($kitchenId)
            ->where('asset_status', '!=', 'damaged')
            ->whereDoesntHave('orderMiddoBoxes')
            ->whereDoesntHave('warehouseHandoff', fn (Builder $q) => $q->open());
    }

    public function scopeDamagedAtKitchen(Builder $query, int $kitchenId): Builder
    {
        return $query
            ->atKitchen($kitchenId)
            ->where('asset_status', 'damaged');
    }

    public function isDamaged(): bool
    {
        return $this->asset_status === 'damaged';
    }

    public function isSendableFromKitchen(?int $kitchenId = null): bool
    {
        if (! $this->isAtKitchen($kitchenId)) {
            return false;
        }

        if ($this->isDamaged()) {
            return false;
        }

        if ($this->hasOpenWarehouseHandoff()) {
            return false;
        }

        return ! $this->orderMiddoBoxes()->exists();
    }

    public function scopeIncomingToKitchen(Builder $query, int $kitchenId): Builder
    {
        return $query
            ->where('kitchen_id', $kitchenId)
            ->where(function (Builder $inner) use ($kitchenId) {
                $inner->whereNull('held_by_user_id')
                    ->orWhere('held_by_user_id', '!=', $kitchenId);
            });
    }

    public function isAtKitchen(?int $kitchenId = null): bool
    {
        if ($this->kitchen_id === null || $this->held_by_user_id === null) {
            return false;
        }

        if ((int) $this->kitchen_id !== (int) $this->held_by_user_id) {
            return false;
        }

        return $kitchenId === null || (int) $this->kitchen_id === (int) $kitchenId;
    }

    public function isIncomingToKitchen(?int $kitchenId = null): bool
    {
        if ($this->kitchen_id === null) {
            return false;
        }

        if ($this->held_by_user_id !== null && $this->held_by_user_id === $this->kitchen_id) {
            return false;
        }

        return $kitchenId === null || $this->kitchen_id === $kitchenId;
    }

    public function locationLabel(): string
    {
        if ($this->isAtKitchen()) {
            return 'At kitchen';
        }

        if ($this->isIncomingToKitchen()) {
            return 'On the way to kitchen';
        }

        return match ($this->asset_status) {
            'at_middo_warehouse' => 'At Middo warehouse',
            'retired' => 'Retired',
            'maintenance' => 'Maintenance',
            'damaged' => 'Damaged',
            'lost' => 'Lost',
            default => str($this->asset_status)->headline()->toString(),
        };
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

    public static function generateBatch(int $count): Collection
    {
        return DB::transaction(function () use ($count) {
            $prefix = 'MB-';
            $nextNumber = static::nextQrCodeNumber();
            $created = collect();

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

                $created->push($box);
            }

            return $created;
        });
    }

    public function damagedReportedAt(): ?Carbon
    {
        $log = $this->logs()
            ->whereIn('log_action', ['marked_damaged_at_kitchen', 'returned_damaged_to_warehouse'])
            ->orderBy('id')
            ->first();

        return $log?->created_at;
    }

    public function retiredAt(): ?Carbon
    {
        if ($this->asset_status !== 'retired') {
            return null;
        }

        $log = $this->logs()
            ->where('log_action', 'retired_at_warehouse')
            ->orderByDesc('id')
            ->first();

        return $log?->created_at ?? $this->updated_at;
    }

    public function runCount(): int
    {
        $fromLogs = (int) $this->logs()
            ->where('log_action', 'picked_by_delivery_from_kitchen')
            ->count();

        return max((int) $this->total_uses_count, $fromLogs);
    }
}
