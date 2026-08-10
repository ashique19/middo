<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const TYPE_WAIVE_CHARGE = 'waive_charge';

    public const APPLIES_ORDERS = 'orders';

    public const APPLIES_PACKAGES = 'packages';

    public const APPLIES_BOTH = 'both';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'waive_charge_category',
        'waive_charge_id',
        'value',
        'min_subtotal',
        'max_discount',
        'usage_limit',
        'per_user_limit',
        'applies_to',
        'eligibility',
        'starts_at',
        'ends_at',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'value' => 'integer',
        'min_subtotal' => 'integer',
        'max_discount' => 'integer',
        'usage_limit' => 'integer',
        'per_user_limit' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'eligibility' => 'array',
        'waive_charge_id' => 'integer',
    ];

    public static function types(): array
    {
        return [
            self::TYPE_FIXED,
            self::TYPE_PERCENT,
            self::TYPE_WAIVE_CHARGE,
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function waiveCharge(): BelongsTo
    {
        return $this->belongsTo(Charge::class, 'waive_charge_id');
    }

    public function appliesToOrders(): bool
    {
        return in_array($this->applies_to, [self::APPLIES_ORDERS, self::APPLIES_BOTH], true);
    }

    public function appliesToPackages(): bool
    {
        return in_array($this->applies_to, [self::APPLIES_PACKAGES, self::APPLIES_BOTH], true);
    }

    public function isWaiveCharge(): bool
    {
        return $this->type === self::TYPE_WAIVE_CHARGE;
    }

    public function eligibilityRules(): array
    {
        $rules = $this->eligibility;
        if (! is_array($rules)) {
            return [];
        }

        return $rules;
    }

    /**
     * @return list<int>
     */
    public function eligibleMenuItemIds(): array
    {
        return $this->intListFromEligibility('menu_item_ids');
    }

    /**
     * @return list<int>
     */
    public function eligibleAreaIds(): array
    {
        return $this->intListFromEligibility('area_ids');
    }

    /**
     * @return list<int>
     */
    public function eligibleCompanyIds(): array
    {
        return $this->intListFromEligibility('company_ids');
    }

    public function firstOrderOnly(): bool
    {
        return (bool) ($this->eligibilityRules()['first_order_only'] ?? false);
    }

    public function minQuantity(): ?int
    {
        $value = $this->eligibilityRules()['min_quantity'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        $qty = (int) $value;

        return $qty > 0 ? $qty : null;
    }

    public function effectLabel(): string
    {
        return match ($this->type) {
            self::TYPE_PERCENT => $this->value.'%'
                .($this->max_discount ? ' (max ৳'.number_format($this->max_discount).')' : ''),
            self::TYPE_FIXED => '৳'.number_format($this->value),
            self::TYPE_WAIVE_CHARGE => 'Waive '
                .($this->waive_charge_category ?: 'any')
                .' charges'
                .($this->max_discount ? ' (max ৳'.number_format($this->max_discount).')' : ''),
            default => (string) $this->type,
        };
    }

    public function redemptionCount(): int
    {
        return $this->redemptions()->count();
    }

    /**
     * @return list<int>
     */
    protected function intListFromEligibility(string $key): array
    {
        $raw = $this->eligibilityRules()[$key] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $raw), fn (int $id) => $id > 0)));
    }
}
