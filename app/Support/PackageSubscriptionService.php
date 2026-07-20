<?php

namespace App\Support;

use App\Contracts\PaymentGateway;
use App\Models\Area;
use App\Models\City;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PackageSubscription;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PackageSubscriptionService
{
    /**
     * Fully prepaid package purchase: debit wallet (or consume gateway), create subscription + orders.
     *
     * @param  array<int>  $omittedWeekdays
     * @param  array<string>  $extraSkippedDates
     * @return array{subscription: PackageSubscription, orders: Collection<int, Order>}
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

        return $this->skipDayInternal($user, $order, $user->id);
    }

    /**
     * Admin/operation skip of a package day (refunds the corporate wallet).
     */
    public function skipDayAsStaff(User $actor, Order $order): Order
    {
        $this->assertStaffActor($actor);

        $owner = User::query()->findOrFail($order->user_id);

        return $this->skipDayInternal($owner, $order, $actor->id);
    }

    /**
     * Cancel all remaining skippable package days and mark the subscription cancelled.
     *
     * @return array{subscription: PackageSubscription, cancelled_orders: int, refunded_amount: int}
     */
    public function cancelRemaining(User $actor, PackageSubscription $subscription): array
    {
        $this->assertStaffActor($actor);

        return DB::transaction(function () use ($actor, $subscription) {
            /** @var PackageSubscription $lockedSub */
            $lockedSub = PackageSubscription::query()->lockForUpdate()->findOrFail($subscription->id);

            if ($lockedSub->status === PackageSubscription::STATUS_CANCELLED) {
                throw new RuntimeException('Subscription is already cancelled.');
            }

            $owner = User::query()->lockForUpdate()->findOrFail($lockedSub->user_id);
            $orders = Order::query()
                ->where('package_subscription_id', $lockedSub->id)
                ->where('order_status', 'pending')
                ->orderBy('delivery_date')
                ->lockForUpdate()
                ->get();

            $cancelled = 0;
            $refunded = 0;

            foreach ($orders as $order) {
                if (! OrderCutoff::allowsModification($order)) {
                    continue;
                }

                $lineRefund = (int) ($order->amount_paid ?: $order->total_amount);
                $this->cancelPackageOrder($order, $owner, $actor->id, $lineRefund, 'Package subscription cancelled — refund for order #'.$order->id);
                $cancelled++;
                $refunded += $lineRefund;
            }

            $lockedSub->update([
                'status' => PackageSubscription::STATUS_CANCELLED,
            ]);

            return [
                'subscription' => $lockedSub->fresh(['package', 'user', 'orders.menuItem']),
                'cancelled_orders' => $cancelled,
                'refunded_amount' => $refunded,
            ];
        });
    }

    /**
     * Update delivery details on the subscription and all future pending package orders.
     *
     * @return array{subscription: PackageSubscription, updated_orders: int}
     */
    public function updateDeliveryDetails(
        User $actor,
        PackageSubscription $subscription,
        string $deliveryTime,
        string $address,
        string $receiverName,
        string $receiverMobile,
        ?int $areaId = null,
    ): array {
        $this->assertStaffActor($actor);

        $deliveryTime = trim($deliveryTime);
        $address = trim($address);
        $receiverName = trim($receiverName);
        $receiverMobile = trim($receiverMobile);

        if ($deliveryTime === '' || $address === '' || $receiverName === '' || $receiverMobile === '') {
            throw new RuntimeException('Delivery time, address, receiver name, and mobile are required.');
        }

        return DB::transaction(function () use (
            $actor,
            $subscription,
            $deliveryTime,
            $address,
            $receiverName,
            $receiverMobile,
            $areaId
        ) {
            /** @var PackageSubscription $lockedSub */
            $lockedSub = PackageSubscription::query()->lockForUpdate()->findOrFail($subscription->id);

            $payload = [
                'delivery_time' => $deliveryTime,
                'address' => $address,
                'receiver_name' => $receiverName,
                'receiver_mobile' => $receiverMobile,
            ];
            if ($areaId) {
                $payload['area_id'] = $areaId;
            }

            $lockedSub->update($payload);

            $today = now(OrderCutoff::timezone())->toDateString();
            $orders = Order::query()
                ->where('package_subscription_id', $lockedSub->id)
                ->where('order_status', 'pending')
                ->whereDate('delivery_date', '>=', $today)
                ->lockForUpdate()
                ->get();

            $orderPayload = [
                'delivery_time' => $deliveryTime,
                'address' => $address,
                'receiver_name' => $receiverName,
                'receiver_mobile' => $receiverMobile,
                'updated_by' => $actor->id,
            ];
            if ($areaId) {
                $orderPayload['area_id'] = $areaId;
            }

            foreach ($orders as $order) {
                $order->update($orderPayload);
            }

            return [
                'subscription' => $lockedSub->fresh(['package', 'user', 'area', 'orders.menuItem']),
                'updated_orders' => $orders->count(),
            ];
        });
    }

    /**
     * Swap the menu for one pending package day and re-group the order.
     */
    public function swapDayMenu(User $actor, Order $order, int $newMenuItemId): Order
    {
        $this->assertStaffActor($actor);

        if (! $order->package_subscription_id) {
            throw new RuntimeException('This order is not part of a meal package.');
        }

        if ($order->order_status !== 'pending') {
            throw new RuntimeException('Only pending package days can change menu.');
        }

        if (! OrderCutoff::allowsModification($order)) {
            throw new RuntimeException(OrderCutoff::modificationDeniedMessage());
        }

        $menu = MenuItem::query()->findOrFail($newMenuItemId);

        return DB::transaction(function () use ($actor, $order, $menu) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->order_status !== 'pending' || ! OrderCutoff::allowsModification($locked)) {
                throw new RuntimeException(OrderCutoff::modificationDeniedMessage());
            }

            if ((int) $locked->menu_item_id === (int) $menu->id) {
                return $locked->fresh(['menuItem', 'packageSubscription.package']);
            }

            app(OrderGroupManager::class)->ungroup($locked->id);

            $locked->update([
                'menu_item_id' => $menu->id,
                'updated_by' => $actor->id,
            ]);

            $fresh = $locked->fresh(['menuItem', 'user', 'packageSubscription.package']);
            app(MealOrderGrouper::class)->assignOrder($fresh, $actor->id);

            return $fresh->fresh(['menuItem', 'orderGroup', 'packageSubscription.package']);
        });
    }

    /**
     * Skip every pending package order on a delivery date (holiday bulk skip).
     *
     * @return array{skipped: int, refunded_amount: int, order_ids: array<int>}
     */
    public function bulkSkipDate(User $actor, string $deliveryDate): array
    {
        $this->assertStaffActor($actor);

        return DB::transaction(function () use ($actor, $deliveryDate) {
            $orders = Order::query()
                ->whereNotNull('package_subscription_id')
                ->where('order_status', 'pending')
                ->whereDate('delivery_date', $deliveryDate)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $skipped = 0;
            $refunded = 0;
            $ids = [];

            foreach ($orders as $order) {
                if (! OrderCutoff::allowsModification($order)) {
                    continue;
                }

                $owner = User::query()->findOrFail($order->user_id);
                $lineRefund = (int) ($order->amount_paid ?: $order->total_amount);
                $this->cancelPackageOrder(
                    $order,
                    $owner,
                    $actor->id,
                    $lineRefund,
                    'Package holiday skip — refund for order #'.$order->id
                );
                $skipped++;
                $refunded += $lineRefund;
                $ids[] = $order->id;
            }

            return [
                'skipped' => $skipped,
                'refunded_amount' => $refunded,
                'order_ids' => $ids,
            ];
        });
    }

    /**
     * Admin-only: force-complete a subscription without further refunds.
     */
    public function forceComplete(User $actor, PackageSubscription $subscription): PackageSubscription
    {
        if ($actor->role?->name !== 'admin') {
            throw new RuntimeException('Only admins can force-complete subscriptions.');
        }

        $subscription->update(['status' => PackageSubscription::STATUS_COMPLETED]);

        return $subscription->fresh(['package', 'user']);
    }

    protected function skipDayInternal(User $walletOwner, Order $order, int $actorId): Order
    {
        if (! $order->package_subscription_id) {
            throw new RuntimeException('This order is not part of a meal package.');
        }

        if ($order->order_status !== 'pending') {
            throw new RuntimeException('Only pending package days can be skipped.');
        }

        if (! OrderCutoff::allowsModification($order)) {
            throw new RuntimeException(OrderCutoff::modificationDeniedMessage());
        }

        return DB::transaction(function () use ($walletOwner, $order, $actorId) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->order_status !== 'pending' || ! OrderCutoff::allowsModification($locked)) {
                throw new RuntimeException(OrderCutoff::modificationDeniedMessage());
            }

            $refund = (int) ($locked->amount_paid ?: $locked->total_amount);
            $this->cancelPackageOrder(
                $locked,
                $walletOwner,
                $actorId,
                $refund,
                'Package day skipped — refund for order #'.$locked->id
            );

            return $locked->fresh('menuItem');
        });
    }

    protected function cancelPackageOrder(
        Order $order,
        User $walletOwner,
        int $actorId,
        int $refund,
        string $refundDescription
    ): void {
        if ($refund > 0) {
            WalletLedger::credit(
                $walletOwner,
                $refund,
                WalletTransaction::TYPE_REFUND,
                $refundDescription,
                $order
            );
        }

        $order->update([
            'order_status' => 'cancelled',
            'updated_by' => $actorId,
        ]);

        try {
            app(OrderGroupManager::class)->ungroup($order->id);
        } catch (\Throwable) {
            // Order may already be ungrouped.
        }
    }

    protected function assertStaffActor(User $actor): void
    {
        $role = $actor->role?->name;
        if (! in_array($role, ['admin', 'operation'], true)) {
            throw new RuntimeException('Only admin or operation staff can perform this action.');
        }
    }
}
