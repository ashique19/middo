<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Charge extends Model
{
    public const CATEGORY_DELIVERY = 'delivery';

    public const CATEGORY_HANDLING = 'handling';

    public const CATEGORY_PACKAGING = 'packaging';

    public const CATEGORY_OTHER = 'other';

    public const CALC_PER_DELIVERY = 'per_delivery';

    public const CALC_PER_ITEM = 'per_item';

    public const CALC_PER_CHECKOUT = 'per_checkout';

    public const APPLIES_ORDERS = 'orders';

    public const APPLIES_PACKAGES = 'packages';

    public const APPLIES_BOTH = 'both';

    protected $fillable = [
        'name',
        'category',
        'description',
        'amount',
        'calculation',
        'area_id',
        'menu_item_id',
        'applies_to',
        'is_active',
        'starts_at',
        'ends_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public static function categories(): array
    {
        return [
            self::CATEGORY_DELIVERY,
            self::CATEGORY_HANDLING,
            self::CATEGORY_PACKAGING,
            self::CATEGORY_OTHER,
        ];
    }

    public static function calculations(): array
    {
        return [
            self::CALC_PER_DELIVERY,
            self::CALC_PER_ITEM,
            self::CALC_PER_CHECKOUT,
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function orderCharges(): HasMany
    {
        return $this->hasMany(OrderCharge::class);
    }

    public function packageSubscriptionCharges(): HasMany
    {
        return $this->hasMany(PackageSubscriptionCharge::class);
    }

    public function appliesToOrders(): bool
    {
        return in_array($this->applies_to, [self::APPLIES_ORDERS, self::APPLIES_BOTH], true);
    }

    public function appliesToPackages(): bool
    {
        return in_array($this->applies_to, [self::APPLIES_PACKAGES, self::APPLIES_BOTH], true);
    }

    public function matchesScope(?int $areaId, ?int $menuItemId): bool
    {
        if ($this->area_id !== null && (int) $this->area_id !== (int) $areaId) {
            return false;
        }

        if ($this->menu_item_id !== null && (int) $this->menu_item_id !== (int) $menuItemId) {
            return false;
        }

        return true;
    }

    public function scopeLabel(): string
    {
        $parts = [];
        if ($this->area_id) {
            $parts[] = 'Area #'.$this->area_id;
        }
        if ($this->menu_item_id) {
            $parts[] = 'Menu #'.$this->menu_item_id;
        }

        return $parts === [] ? 'Global' : implode(' · ', $parts);
    }
}
