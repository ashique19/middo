<?php

namespace App\Support;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\PackageSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CouponService
{
    public static function normalizeCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    /**
     * Cart / eligibility context passed from checkout.
     *
     * @param  array{
     *     area_id?: int|null,
     *     menu_item_ids?: list<int>,
     *     quantity?: int,
     *     company_id?: int|null,
     *     charge_lines?: list<array{charge_id?:int|null,category?:string,amount?:int}>
     * }  $scope
     * @return array{coupon: Coupon, discount_amount: int, final_amount: int, original_amount: int}
     */
    public function quote(string $code, User $user, string $context, int $subtotal, array $scope = []): array
    {
        $coupon = $this->findApplicable($code, $user, $context, $subtotal, $scope);
        $discount = $this->discountAmount($coupon, $subtotal, $scope);

        return [
            'coupon' => $coupon,
            'discount_amount' => $discount,
            'original_amount' => $subtotal,
            'final_amount' => max(0, $subtotal - $discount),
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function findApplicable(string $code, User $user, string $context, int $subtotal, array $scope = []): Coupon
    {
        $normalized = self::normalizeCode($code);
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'coupon_code' => ['Enter a coupon code.'],
            ]);
        }

        /** @var Coupon|null $coupon */
        $coupon = Coupon::query()->where('code', $normalized)->first();
        if (! $coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon code is invalid.'],
            ]);
        }

        $this->assertUsable($coupon, $user, $context, $subtotal, $scope);

        return $coupon;
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function assertUsable(Coupon $coupon, User $user, string $context, int $subtotal, array $scope = []): void
    {
        if (! $coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon is inactive.'],
            ]);
        }

        $now = now(OrderCutoff::timezone());
        if ($coupon->starts_at && $now->lt($coupon->starts_at)) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon is not active yet.'],
            ]);
        }
        if ($coupon->ends_at && $now->gt($coupon->ends_at)) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon has expired.'],
            ]);
        }

        if ($context === CouponRedemption::CONTEXT_ORDER && ! $coupon->appliesToOrders()) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon cannot be used on menu orders.'],
            ]);
        }
        if ($context === CouponRedemption::CONTEXT_PACKAGE && ! $coupon->appliesToPackages()) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon cannot be used on meal packages.'],
            ]);
        }

        if (! $coupon->isWaiveCharge() && $subtotal < (int) $coupon->min_subtotal) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Minimum order amount for this coupon is ৳'.number_format((int) $coupon->min_subtotal).'.'],
            ]);
        }

        if ($coupon->isWaiveCharge()) {
            $waivable = $this->matchingChargesTotal($coupon, $scope);
            if ($waivable < 1) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['This coupon waives charges, but no matching charges are on this checkout.'],
                ]);
            }
        }

        $this->assertEligibility($coupon, $user, $scope);

        if ($coupon->usage_limit !== null) {
            $used = CouponRedemption::query()->where('coupon_id', $coupon->id)->count();
            if ($used >= (int) $coupon->usage_limit) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['This coupon has reached its usage limit.'],
                ]);
            }
        }

        $perUser = max(1, (int) $coupon->per_user_limit);
        $userUsed = CouponRedemption::query()
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $user->id)
            ->count();
        if ($userUsed >= $perUser) {
            throw ValidationException::withMessages([
                'coupon_code' => ['You have already used this coupon the maximum number of times.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function discountAmount(Coupon $coupon, int $subtotal, array $scope = []): int
    {
        if ($coupon->isWaiveCharge()) {
            $matching = $this->matchingChargesTotal($coupon, $scope);
            if ($matching < 1) {
                return 0;
            }
            $discount = $matching;
            if ($coupon->max_discount !== null) {
                $discount = min($discount, (int) $coupon->max_discount);
            }

            return max(0, $discount);
        }

        if ($subtotal < 1) {
            return 0;
        }

        $discount = match ($coupon->type) {
            Coupon::TYPE_PERCENT => (int) floor($subtotal * ((int) $coupon->value) / 100),
            Coupon::TYPE_FIXED => (int) $coupon->value,
            default => throw new RuntimeException('Unsupported coupon type.'),
        };

        if ($coupon->max_discount !== null) {
            $discount = min($discount, (int) $coupon->max_discount);
        }

        return max(0, min($discount, $subtotal));
    }

    /**
     * Sum charge line amounts that this waive coupon targets.
     *
     * @param  array<string, mixed>  $scope
     */
    public function matchingChargesTotal(Coupon $coupon, array $scope = []): int
    {
        $lines = $scope['charge_lines'] ?? [];
        if (! is_array($lines) || $lines === []) {
            return 0;
        }

        $total = 0;
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            if (! $this->chargeLineMatchesCoupon($coupon, $line)) {
                continue;
            }
            $total += max(0, (int) ($line['amount'] ?? 0));
        }

        return $total;
    }

    /**
     * @param  array{charge_id?:int|null,category?:string,amount?:int}  $line
     */
    public function chargeLineMatchesCoupon(Coupon $coupon, array $line): bool
    {
        if ($coupon->waive_charge_id) {
            return (int) ($line['charge_id'] ?? 0) === (int) $coupon->waive_charge_id;
        }

        if ($coupon->waive_charge_category) {
            return (string) ($line['category'] ?? '') === (string) $coupon->waive_charge_category;
        }

        // Null category + null charge id ⇒ any charge line.
        return (int) ($line['amount'] ?? 0) > 0;
    }

    /**
     * Allocate a cart-level discount across line totals (largest remainder).
     *
     * @param  list<int>  $lineTotals
     * @return list<int>
     */
    public function allocateDiscount(array $lineTotals, int $discountAmount): array
    {
        $subtotal = array_sum($lineTotals);
        if ($subtotal < 1 || $discountAmount < 1) {
            return array_fill(0, count($lineTotals), 0);
        }

        $discountAmount = min($discountAmount, $subtotal);
        $shares = [];
        $allocated = 0;

        foreach ($lineTotals as $i => $line) {
            $raw = ($line / $subtotal) * $discountAmount;
            $share = (int) floor($raw);
            $shares[$i] = [
                'amount' => $share,
                'frac' => $raw - $share,
            ];
            $allocated += $share;
        }

        $remaining = $discountAmount - $allocated;
        uasort($shares, fn ($a, $b) => $b['frac'] <=> $a['frac']);
        foreach (array_keys($shares) as $i) {
            if ($remaining < 1) {
                break;
            }
            $shares[$i]['amount']++;
            $remaining--;
        }

        ksort($shares);

        return array_map(fn ($row) => (int) $row['amount'], $shares);
    }

    /**
     * Allocate a waive-charge discount across per-date matching fee totals.
     *
     * @param  list<int>  $feeTotalsByLine
     * @return list<int>
     */
    public function allocateWaiveAcrossFees(array $feeTotalsByLine, int $discountAmount): array
    {
        return $this->allocateDiscount($feeTotalsByLine, $discountAmount);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $scope
     */
    public function redeem(
        Coupon $coupon,
        User $user,
        string $context,
        int $originalAmount,
        int $discountAmount,
        ?Order $order = null,
        ?PackageSubscription $subscription = null,
        array $metadata = [],
        array $scope = [],
    ): CouponRedemption {
        return DB::transaction(function () use (
            $coupon,
            $user,
            $context,
            $originalAmount,
            $discountAmount,
            $order,
            $subscription,
            $metadata,
            $scope
        ) {
            /** @var Coupon $locked */
            $locked = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);
            $this->assertUsable($locked, $user, $context, $originalAmount, $scope);

            $discountAmount = min($discountAmount, $this->discountAmount($locked, $originalAmount, $scope));

            return CouponRedemption::create([
                'coupon_id' => $locked->id,
                'user_id' => $user->id,
                'code' => $locked->code,
                'context' => $context,
                'original_amount' => $originalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => max(0, $originalAmount - $discountAmount),
                'order_id' => $order?->id,
                'package_subscription_id' => $subscription?->id,
                'metadata' => $metadata ?: null,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    protected function assertEligibility(Coupon $coupon, User $user, array $scope): void
    {
        $menuIds = $coupon->eligibleMenuItemIds();
        if ($menuIds !== []) {
            $cartMenus = array_values(array_unique(array_filter(array_map(
                'intval',
                $scope['menu_item_ids'] ?? []
            ))));
            if ($cartMenus === []) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['This coupon is limited to specific menus.'],
                ]);
            }
            foreach ($cartMenus as $menuId) {
                if (! in_array($menuId, $menuIds, true)) {
                    throw ValidationException::withMessages([
                        'coupon_code' => ['This coupon does not apply to the selected menu.'],
                    ]);
                }
            }
        }

        $areaIds = $coupon->eligibleAreaIds();
        if ($areaIds !== []) {
            $areaId = (int) ($scope['area_id'] ?? 0);
            if ($areaId < 1 || ! in_array($areaId, $areaIds, true)) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['This coupon is not valid for your delivery area.'],
                ]);
            }
        }

        $companyIds = $coupon->eligibleCompanyIds();
        if ($companyIds !== []) {
            $companyId = (int) ($scope['company_id'] ?? $user->company_id ?? 0);
            if ($companyId < 1 || ! in_array($companyId, $companyIds, true)) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['This coupon is not available for your company.'],
                ]);
            }
        }

        if ($coupon->firstOrderOnly() && $this->userHasPriorPurchase($user)) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon is only for first-time orders.'],
            ]);
        }

        $minQty = $coupon->minQuantity();
        if ($minQty !== null) {
            $qty = (int) ($scope['quantity'] ?? 0);
            if ($qty < $minQty) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['This coupon requires at least '.$minQty.' seats/items.'],
                ]);
            }
        }
    }

    protected function userHasPriorPurchase(User $user): bool
    {
        $hasOrder = Order::query()
            ->where('user_id', $user->id)
            ->where('order_status', '!=', 'cancelled')
            ->exists();

        if ($hasOrder) {
            return true;
        }

        return PackageSubscription::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', PackageSubscription::STATUS_CANCELLED)
            ->exists();
    }
}
