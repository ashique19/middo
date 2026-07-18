<?php

namespace App\Support;

use App\Contracts\PaymentGateway;
use App\Models\Area;
use App\Models\City;
use App\Models\MealPackage;
use App\Models\Order;
use App\Models\PackageSubscription;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PackageSubscriptionService
{
    /**
     * Fully prepaid package purchase: debit wallet (or consume gateway), create subscription + orders.
     *
     * @param  array<int>  $omittedWeekdays
     * @param  array<string>  $extraSkippedDates
     * @return array{subscription: PackageSubscription, orders: \Illuminate\Support\Collection<int, Order>}
     */
    public function subscribe(
        User $user,
        MealPackage $package,
        int $quantity,
        array $omittedWeekdays,
        array $extraSkippedDates,
        string $receiverName,
        string $receiverMobile,
        string $addressLine,
        int $cityId,
        int $areaId,
        string $deliveryTime,
        string $paymentMethod = 'balance',
        ?string $gatewayPaymentToken = null,
    ): array {
        if (! $package->isPublished()) {
            throw new RuntimeException('This package is not available for purchase.');
        }

        $omittedWeekdays = PackageBilling::normalizeOmittedWeekdays($omittedWeekdays);
        $quote = PackageBilling::quote($package, $quantity, $omittedWeekdays, $extraSkippedDates);

        if ($quote['billable_days'] < 1) {
            throw new RuntimeException('No billable delivery days remain for this package. Adjust omitted weekdays or choose another package.');
        }

        $city = City::findOrFail($cityId);
        $area = Area::findOrFail($areaId);
        if ((int) $area->city_id !== (int) $city->id) {
            throw new RuntimeException('Selected area does not belong to the selected city.');
        }

        $fullAddress = trim($addressLine).', '.$area->name.', '.$city->name;
        $total = (int) $quote['total_amount'];

        return DB::transaction(function () use (
            $user,
            $package,
            $quantity,
            $omittedWeekdays,
            $quote,
            $receiverName,
            $receiverMobile,
            $fullAddress,
            $addressLine,
            $cityId,
            $areaId,
            $deliveryTime,
            $paymentMethod,
            $gatewayPaymentToken,
            $total
        ) {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($paymentMethod === 'balance') {
                if ((int) $locked->balance < $total) {
                    throw new RuntimeException('Insufficient Middo Balance. Top up your wallet to purchase this package.');
                }
            } elseif ($paymentMethod === 'gateway') {
                if (! filled($gatewayPaymentToken)) {
                    throw new RuntimeException('Start online payment before confirming the package.');
                }

                $fingerprint = [
                    'meal_package_id' => (int) $package->id,
                    'quantity' => $quantity,
                    'omitted_weekdays' => $omittedWeekdays,
                    'amount' => $total,
                ];

                $consumed = app(PaymentGateway::class)->consumePaid(
                    $gatewayPaymentToken,
                    (int) $locked->id,
                    $total,
                    $fingerprint
                );

                if (! ($consumed['ok'] ?? false)) {
                    throw new RuntimeException($consumed['message'] ?? 'Complete online payment first.');
                }
            } else {
                throw new RuntimeException('Invalid payment method.');
            }

            $dates = collect($quote['days'])->pluck('date')->all();
            $startDate = $dates[0] ?? $package->start_date->toDateString();
            $endDate = $dates[array_key_last($dates)] ?? $package->end_date->toDateString();

            $subscription = PackageSubscription::create([
                'user_id' => $locked->id,
                'meal_package_id' => $package->id,
                'quantity' => $quantity,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'omitted_weekdays' => $omittedWeekdays,
                'billable_days' => $quote['billable_days'],
                'price_per_day' => $quote['price_per_day'],
                'total_amount' => $total,
                'amount_paid' => $total,
                'payment_status' => 'paid',
                'status' => PackageSubscription::STATUS_ACTIVE,
                'delivery_time' => $deliveryTime,
                'address' => $fullAddress,
                'receiver_name' => $receiverName,
                'receiver_mobile' => $receiverMobile,
                'area_id' => $areaId,
                'created_by' => $locked->id,
            ]);

            if ($paymentMethod === 'balance') {
                WalletLedger::debit(
                    $locked,
                    $total,
                    'Package prepayment: '.$package->name,
                    $subscription
                );
            }

            $createdOrderIds = [];
            foreach ($quote['days'] as $day) {
                $lineTotal = (int) $day['line_total'];
                $order = Order::create([
                    'user_id' => $locked->id,
                    'menu_item_id' => $day['menu_item_id'],
                    'package_subscription_id' => $subscription->id,
                    'quantity' => $quantity,
                    'delivery_date' => $day['date'],
                    'delivery_time' => $deliveryTime,
                    'total_amount' => $lineTotal,
                    'amount_paid' => $lineTotal,
                    'prepaid_amount' => $lineTotal,
                    'cash_collected' => 0,
                    'address' => $fullAddress,
                    'receiver_name' => $receiverName,
                    'receiver_mobile' => $receiverMobile,
                    'area_id' => $areaId,
                    'order_status' => 'pending',
                    'payment_status' => 'paid',
                    'payment_method' => $paymentMethod,
                    'created_by' => $locked->id,
                    'updated_by' => $locked->id,
                ]);
                $createdOrderIds[] = $order->id;
            }

            $grouper = app(MealOrderGrouper::class);
            $orders = Order::query()->whereIn('id', $createdOrderIds)->get();
            foreach ($orders as $order) {
                $grouper->assignOrder($order->load('user'), $locked->id);
            }

            $profileMatches = CorporateOrderPrepayment::profileMatchesReceiver(
                $locked,
                $receiverName,
                $receiverMobile
            );
            $profileUpdate = [
                'address' => $addressLine,
                'city_id' => $cityId,
                'area_id' => $areaId,
                'is_mobile_verified' => true,
            ];
            if ($profileMatches) {
                $profileUpdate['mobile'] = CorporateOrderPrepayment::normalizeMobile($receiverMobile);
            }
            $locked->update($profileUpdate);

            return [
                'subscription' => $subscription->fresh(['package', 'orders.menuItem']),
                'orders' => $orders->load('menuItem'),
            ];
        });
    }

    /**
     * Skip a pending package delivery day before cutoff; refund line amount to wallet.
     */
    public function skipDay(User $user, Order $order): Order
    {
        if ((int) $order->user_id !== (int) $user->id) {
            throw new RuntimeException('Order not found.');
        }

        if (! $order->package_subscription_id) {
            throw new RuntimeException('This order is not part of a meal package.');
        }

        if ($order->order_status !== 'pending') {
            throw new RuntimeException('Only pending package days can be skipped.');
        }

        if (! OrderCutoff::allowsModification($order)) {
            throw new RuntimeException(OrderCutoff::modificationDeniedMessage());
        }

        return DB::transaction(function () use ($user, $order) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->order_status !== 'pending' || ! OrderCutoff::allowsModification($locked)) {
                throw new RuntimeException(OrderCutoff::modificationDeniedMessage());
            }

            $refund = (int) ($locked->amount_paid ?: $locked->total_amount);
            if ($refund > 0) {
                WalletLedger::credit(
                    $user,
                    $refund,
                    WalletTransaction::TYPE_REFUND,
                    'Package day skipped — refund for order #'.$locked->id,
                    $locked
                );
            }

            $locked->update([
                'order_status' => 'cancelled',
                'updated_by' => $user->id,
            ]);

            return $locked->fresh('menuItem');
        });
    }
}
