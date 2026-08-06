<?php

namespace App\Support;

use App\Contracts\PaymentGateway;
use App\Models\Area;
use App\Models\City;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menu checkout online payment: after OTP, create gateway session with a draft;
 * when payment succeeds, place the order without another confirmation click.
 */
class CorporateOrderGatewayCheckout
{
    public const PURPOSE = 'order_checkout';

    /**
     * @param  array<string, int>  $quantities  date => qty
     * @return array{token: string, amount: int, paid: bool, payment_url: string}
     */
    public static function start(
        User $user,
        array $quantities,
        int $menuItemId,
        string $customerName,
        string $mobile,
        string $addressLine1,
        int $cityId,
        int $areaId,
        string $deliveryWindow,
        int $chargeAmount,
        string $couponCode = '',
        int $couponDiscount = 0
    ): array {
        $activeOrders = array_filter($quantities, fn ($qty) => (int) $qty > 0);
        $fingerprint = self::fingerprintPayload(
            $menuItemId,
            $customerName,
            $mobile,
            $activeOrders,
            $chargeAmount
        );

        $checkout = app(PaymentGateway::class)->createCheckout(
            (int) $user->id,
            $chargeAmount,
            array_merge($fingerprint, ['purpose' => self::PURPOSE])
        );

        $token = $checkout['token'];
        Cache::put(self::draftKey($token), [
            'user_id' => (int) $user->id,
            'menu_item_id' => $menuItemId,
            'customer_name' => $customerName,
            'mobile' => $mobile,
            'address_line1' => $addressLine1,
            'city_id' => $cityId,
            'area_id' => $areaId,
            'delivery_window' => $deliveryWindow,
            'quantities' => $activeOrders,
            'charge_amount' => $chargeAmount,
            'coupon_code' => $couponCode,
            'coupon_discount' => $couponDiscount,
            'fingerprint' => $fingerprint,
        ], now()->addMinutes(45));

        Cache::forget(self::doneKey($token));

        return $checkout;
    }

    /**
     * @return array{ok: bool, message?: string, order_ids?: list<int>, already_done?: bool}
     */
    public static function completeIfPaid(string $token): array
    {
        $done = Cache::get(self::doneKey($token));
        if (is_array($done) && ($done['ok'] ?? false)) {
            return [
                'ok' => true,
                'already_done' => true,
                'order_ids' => $done['order_ids'] ?? [],
                'message' => $done['message'] ?? 'Order already placed.',
            ];
        }

        $gateway = app(PaymentGateway::class);
        $payload = $gateway->find($token);
        if (! is_array($payload)) {
            return ['ok' => false, 'message' => 'Payment session expired or invalid.'];
        }

        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        if (($metadata['purpose'] ?? null) !== self::PURPOSE) {
            return ['ok' => false, 'message' => 'Not an order checkout payment.'];
        }

        if (! ($payload['paid'] ?? false)) {
            return ['ok' => false, 'message' => 'Payment is not completed yet.'];
        }

        $draft = Cache::get(self::draftKey($token));
        if (! is_array($draft)) {
            return ['ok' => false, 'message' => 'Checkout draft expired. Start checkout again.'];
        }

        $user = User::query()->find((int) ($draft['user_id'] ?? 0));
        if (! $user) {
            return ['ok' => false, 'message' => 'Account not found for this payment.'];
        }

        if ((int) ($payload['user_id'] ?? 0) !== (int) $user->id) {
            return ['ok' => false, 'message' => 'Payment session does not belong to this account.'];
        }

        try {
            $orderIds = self::placePaidOrder($user, $draft, $token);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $result = [
            'ok' => true,
            'order_ids' => $orderIds,
            'message' => 'Your meal track has been scheduled successfully!',
        ];
        Cache::put(self::doneKey($token), $result, now()->addMinutes(45));
        Cache::forget(self::draftKey($token));

        return $result;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return list<int>
     */
    protected static function placePaidOrder(User $user, array $draft, string $token): array
    {
        $activeOrders = [];
        foreach (($draft['quantities'] ?? []) as $date => $qty) {
            $qty = (int) $qty;
            if ($qty > 0) {
                $activeOrders[(string) $date] = $qty;
            }
        }

        if ($activeOrders === []) {
            throw new \RuntimeException('No delivery dates in checkout draft.');
        }

        foreach (array_keys($activeOrders) as $date) {
            if (OrderCutoff::isPastForDeliveryDate((string) $date)) {
                throw new \RuntimeException(OrderCutoff::placementDeniedMessage((string) $date));
            }
        }

        $menuItemId = (int) ($draft['menu_item_id'] ?? 0);
        $menu = MenuItem::query()->find($menuItemId);
        if (! $menu) {
            throw new \RuntimeException('Menu item no longer available.');
        }

        $customerName = (string) ($draft['customer_name'] ?? '');
        $mobile = (string) ($draft['mobile'] ?? '');
        $addressLine1 = (string) ($draft['address_line1'] ?? '');
        $cityId = (int) ($draft['city_id'] ?? 0);
        $areaId = (int) ($draft['area_id'] ?? 0);
        $deliveryWindow = (string) ($draft['delivery_window'] ?? '12:00 PM');
        $chargeAmount = (int) ($draft['charge_amount'] ?? 0);
        $couponCode = strtoupper(trim((string) ($draft['coupon_code'] ?? '')));
        $couponDiscount = max(0, (int) ($draft['coupon_discount'] ?? 0));

        $fingerprint = is_array($draft['fingerprint'] ?? null)
            ? $draft['fingerprint']
            : self::fingerprintPayload($menuItemId, $customerName, $mobile, $activeOrders, $chargeAmount);

        $consumed = app(PaymentGateway::class)->consumePaid(
            $token,
            (int) $user->id,
            $chargeAmount,
            array_merge($fingerprint, ['purpose' => self::PURPOSE])
        );

        if (! ($consumed['ok'] ?? false)) {
            throw new \RuntimeException($consumed['message'] ?? 'Could not confirm online payment.');
        }

        $chargeQuote = app(ChargeService::class)->quoteOrderCart($areaId ?: null, $menuItemId, $activeOrders);
        $perOrderCharges = $chargeQuote['per_order'] ?? [];

        $lineTotals = [];
        foreach ($activeOrders as $date => $qty) {
            $food = (int) round((float) $menu->price * (int) $qty);
            $fees = (int) collect($perOrderCharges[(string) $date] ?? [])->sum('amount');
            $lineTotals[] = $food + $fees;
        }
        $cartTotal = (int) array_sum($lineTotals);
        $discountAmount = min($couponDiscount, $cartTotal);
        $discountShares = app(CouponService::class)->allocateDiscount($lineTotals, $discountAmount);
        $netLineTotals = [];
        foreach ($lineTotals as $i => $gross) {
            $netLineTotals[] = max(0, (int) $gross - (int) ($discountShares[$i] ?? 0));
        }
        $allocations = CorporateOrderPrepayment::allocate($chargeAmount, $netLineTotals);

        $couponId = null;
        if ($couponCode !== '' && $discountAmount > 0) {
            $couponId = app(CouponService::class)
                ->findApplicable($couponCode, $user, CouponRedemption::CONTEXT_ORDER, $cartTotal)
                ->id;
        }

        $profileMatches = CorporateOrderPrepayment::profileMatchesReceiver($user, $customerName, $mobile);

        return DB::transaction(function () use (
            $activeOrders,
            $user,
            $allocations,
            $discountShares,
            $profileMatches,
            $couponId,
            $cartTotal,
            $discountAmount,
            $perOrderCharges,
            $menu,
            $customerName,
            $mobile,
            $addressLine1,
            $cityId,
            $areaId,
            $deliveryWindow
        ) {
            $cityModel = City::find($cityId);
            $areaModel = Area::find($areaId);
            $fullAddress = trim($addressLine1).', '.($areaModel?->name ?? '').', '.($cityModel?->name ?? '');
            $createdOrderIds = [];
            $index = 0;
            $firstOrder = null;
            $chargeService = app(ChargeService::class);
            $userId = (int) $user->id;

            foreach ($activeOrders as $date => $qty) {
                $foodTotal = (int) round((float) $menu->price * $qty);
                $feeLines = $perOrderCharges[(string) $date] ?? [];
                $feesTotal = (int) collect($feeLines)->sum('amount');
                $lineTotal = $foodTotal + $feesTotal;
                $amountPaid = (int) ($allocations[$index] ?? 0);
                $lineDiscount = (int) ($discountShares[$index] ?? 0);
                $index++;

                $order = Order::create([
                    'user_id' => $userId,
                    'menu_item_id' => $menu->id,
                    'quantity' => $qty,
                    'delivery_date' => $date,
                    'delivery_time' => $deliveryWindow,
                    'total_amount' => $lineTotal,
                    'charges_amount' => $feesTotal,
                    'amount_paid' => $amountPaid,
                    'prepaid_amount' => $amountPaid,
                    'cash_collected' => 0,
                    'discount_amount' => $lineDiscount,
                    'coupon_id' => $couponId,
                    'address' => $fullAddress,
                    'receiver_name' => $customerName,
                    'receiver_mobile' => $mobile,
                    'area_id' => $areaId,
                    'order_status' => 'pending',
                    'payment_status' => $amountPaid >= max(0, $lineTotal - $lineDiscount) && $lineTotal > 0 ? 'paid' : 'pending',
                    'payment_method' => OrderPaymentMethod::GATEWAY,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
                $chargeService->attachToOrder($order, $feeLines);
                $createdOrderIds[] = $order->id;
                $firstOrder ??= $order;
            }

            if ($couponId && $discountAmount > 0 && $firstOrder) {
                app(CouponService::class)->redeem(
                    Coupon::query()->findOrFail($couponId),
                    $user,
                    CouponRedemption::CONTEXT_ORDER,
                    $cartTotal,
                    $discountAmount,
                    $firstOrder,
                    null,
                    ['order_ids' => $createdOrderIds]
                );
            }

            $grouper = app(MealOrderGrouper::class);
            foreach (Order::query()->whereIn('id', $createdOrderIds)->get() as $order) {
                $grouper->assignOrder($order->load('user'), $userId);
            }

            $profileUpdate = [
                'address' => $addressLine1,
                'city_id' => $cityId,
                'area_id' => $areaId,
                'is_mobile_verified' => true,
            ];
            if ($profileMatches) {
                $profileUpdate['mobile'] = $mobile;
            }
            $user->update($profileUpdate);

            return $createdOrderIds;
        });
    }

    /**
     * @param  array<string, int>  $activeOrders
     * @return array{menu_item_id: int, receiver_name: string, mobile: string, dates: list<array{date: string, quantity: int}>, amount: int}
     */
    public static function fingerprintPayload(
        int $menuItemId,
        string $customerName,
        string $mobile,
        array $activeOrders,
        int $chargeAmount
    ): array {
        return [
            'menu_item_id' => $menuItemId,
            'receiver_name' => CorporateOrderPrepayment::normalizeName($customerName),
            'mobile' => CorporateOrderPrepayment::normalizeMobile($mobile),
            'dates' => collect($activeOrders)->map(fn ($qty, $date) => [
                'date' => (string) $date,
                'quantity' => (int) $qty,
            ])->values()->all(),
            'amount' => $chargeAmount,
        ];
    }

    public static function isOrderCheckout(?array $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        return ($metadata['purpose'] ?? null) === self::PURPOSE;
    }

    protected static function draftKey(string $token): string
    {
        return 'order_gateway_draft_'.$token;
    }

    protected static function doneKey(string $token): string
    {
        return 'order_gateway_done_'.$token;
    }
}
