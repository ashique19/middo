<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Coupon;

/**
 * Delivery fees are independent of other charges.
 * Formula (inclusive VAT, admin rate — default 15%):
 *   net_delivery = max(0, delivery_charge − delivery_coupon)
 *   delivery_ex_vat = round(net / (1 + rate/100))
 *   delivery_vat = net − delivery_ex_vat
 */
class DeliveryChargeVat
{
    /**
     * @param  list<array{charge_id?:int|null,category?:string,amount?:int,name?:string}>  $lines
     * @return array{
     *   delivery_lines: list<array>,
     *   other_lines: list<array>,
     *   delivery_gross: int,
     *   other_gross: int
     * }
     */
    public static function partitionLines(array $lines): array
    {
        $delivery = [];
        $other = [];
        $deliveryGross = 0;
        $otherGross = 0;

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $amount = max(0, (int) ($line['amount'] ?? 0));
            if (strtolower((string) ($line['category'] ?? '')) === Charge::CATEGORY_DELIVERY) {
                $delivery[] = $line;
                $deliveryGross += $amount;
            } else {
                $other[] = $line;
                $otherGross += $amount;
            }
        }

        return [
            'delivery_lines' => $delivery,
            'other_lines' => $other,
            'delivery_gross' => $deliveryGross,
            'other_gross' => $otherGross,
        ];
    }

    /**
     * How much of a coupon discount applies specifically to delivery fees.
     *
     * @param  list<array{charge_id?:int|null,category?:string,amount?:int}>  $chargeLines
     */
    public static function deliveryCouponAmount(?Coupon $coupon, int $discountAmount, array $chargeLines): int
    {
        $discountAmount = max(0, $discountAmount);
        if ($discountAmount < 1 || ! $coupon) {
            return 0;
        }

        $partition = self::partitionLines($chargeLines);
        $deliveryGross = $partition['delivery_gross'];
        if ($deliveryGross < 1) {
            return 0;
        }

        if ($coupon->isWaiveCharge()) {
            // Only count matching delivery lines for waive coupons.
            $matchingDelivery = 0;
            foreach ($partition['delivery_lines'] as $line) {
                if (app(CouponService::class)->chargeLineMatchesCoupon($coupon, $line)) {
                    $matchingDelivery += max(0, (int) ($line['amount'] ?? 0));
                }
            }

            return min($discountAmount, $matchingDelivery);
        }

        // Percent / fixed cart coupons are not "delivery coupons".
        return 0;
    }

    /**
     * @return array{
     *   delivery_gross:int,
     *   delivery_discount:int,
     *   delivery_net:int,
     *   other_gross:int,
     *   delivery_vat_rate_pct:float,
     *   delivery_vat_amount:int,
     *   delivery_ex_vat:int
     * }
     */
    public static function quote(?Coupon $coupon, int $discountAmount, array $chargeLines, ?float $ratePct = null): array
    {
        $partition = self::partitionLines($chargeLines);
        $deliveryDiscount = self::deliveryCouponAmount($coupon, $discountAmount, $chargeLines);
        $deliveryNet = max(0, $partition['delivery_gross'] - $deliveryDiscount);
        $rate = $ratePct ?? MiddoSettings::deliveryVatRatePct();
        $unbundled = self::unbundleInclusive($deliveryNet, $rate);

        return [
            'delivery_gross' => $partition['delivery_gross'],
            'delivery_discount' => $deliveryDiscount,
            'delivery_net' => $deliveryNet,
            'other_gross' => $partition['other_gross'],
            'delivery_vat_rate_pct' => $unbundled['rate'],
            'delivery_vat_amount' => $unbundled['vat'],
            'delivery_ex_vat' => $unbundled['ex_vat'],
        ];
    }

    /**
     * @return array{rate:float,ex_vat:int,vat:int,inclusive:int}
     */
    public static function unbundleInclusive(int $inclusiveAmount, float $ratePct): array
    {
        $inclusiveAmount = max(0, $inclusiveAmount);
        $rate = max(0.0, min(100.0, $ratePct));
        if ($inclusiveAmount < 1 || $rate <= 0) {
            return [
                'rate' => $rate,
                'ex_vat' => $inclusiveAmount,
                'vat' => 0,
                'inclusive' => $inclusiveAmount,
            ];
        }

        $exVat = (int) round($inclusiveAmount / (1 + ($rate / 100)));
        $vat = max(0, $inclusiveAmount - $exVat);

        return [
            'rate' => $rate,
            'ex_vat' => $exVat,
            'vat' => $vat,
            'inclusive' => $inclusiveAmount,
        ];
    }
}
