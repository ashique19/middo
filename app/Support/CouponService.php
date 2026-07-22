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
     * @return array{coupon: Coupon, discount_amount: int, final_amount: int, original_amount: int}
     */
    public function quote(string $code, User $user, string $context, int $subtotal): array
    {
        $coupon = $this->findApplicable($code, $user, $context, $subtotal);
        $discount = $this->discountAmount($coupon, $subtotal);

        return [
            'coupon' => $coupon,
            'discount_amount' => $discount,
            'original_amount' => $subtotal,
            'final_amount' => max(0, $subtotal - $discount),
        ];
    }

    public function findApplicable(string $code, User $user, string $context, int $subtotal): Coupon
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

        $this->assertUsable($coupon, $user, $context, $subtotal);

        return $coupon;
    }

    public function assertUsable(Coupon $coupon, User $user, string $context, int $subtotal): void
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

        if ($subtotal < (int) $coupon->min_subtotal) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Minimum order amount for this coupon is ৳'.number_format((int) $coupon->min_subtotal).'.'],
            ]);
        }

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

    public function discountAmount(Coupon $coupon, int $subtotal): int
    {
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
     * @param  array<string, mixed>  $metadata
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
    ): CouponRedemption {
        return DB::transaction(function () use (
            $coupon,
            $user,
            $context,
            $originalAmount,
            $discountAmount,
            $order,
            $subscription,
            $metadata
        ) {
            /** @var Coupon $locked */
            $locked = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);
            $this->assertUsable($locked, $user, $context, $originalAmount);

            $discountAmount = min($discountAmount, $this->discountAmount($locked, $originalAmount));

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
}
