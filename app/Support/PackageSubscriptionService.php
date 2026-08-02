<?php

namespace App\Support;

use App\Contracts\PaymentGateway;
use App\Models\Area;
use App\Models\City;
use App\Models\CouponRedemption;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PackageSubscription;
use App\Models\PackageSubscriptionSelection;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PackageSubscriptionService
{
    /**
     * Fully prepaid monthly package: corporate selects menus + day counts, omits weekdays,
     * pays upfront. Operations assigns exact dates afterward.
     *
     * @param  array<int>  $omittedWeekdays
     * @param  array<int, array{menu_item_id:int, day_count:int}>  $menuSelections
     * @return array{subscription: PackageSubscription, orders: Collection<int, Order>}
     */
    public function subscribe(
        User $user,
        MealPackage $package,
        int $quantity,
        array $omittedWeekdays,
        array $menuSelections,
        string $targetMonth,
        string $receiverName,
        string $receiverMobile,
        string $addressLine,
        int $cityId,
        int $areaId,
        string $deliveryTime,
        string $paymentMethod = 'balance',
        ?string $gatewayPaymentToken = null,
        ?string $couponCode = null,
    ): array {
        if (! $package->isPublished()) {
            throw new RuntimeException('This package is not available for purchase.');
        }

        $omittedWeekdays = PackageBilling::normalizeOmittedWeekdays($omittedWeekdays);
        $quote = PackageBilling::quoteFromSelections(
            $package,
            $quantity,
            $menuSelections,
            $omittedWeekdays,
            $targetMonth
        );

        PackageBilling::assertSelectionsFillMonth($quote);

        if (PackageSubscription::userHasPackageForMonth((int) $user->id, (string) $quote['target_month'])) {
            $label = Carbon::createFromFormat('Y-m', (string) $quote['target_month'])
                ->timezone(OrderCutoff::timezone())
                ->format('F Y');

            throw new RuntimeException(
                'You already ordered a package for '.$label.'. That month is locked — choose another month.'
            );
        }

        $city = City::findOrFail($cityId);
        $area = Area::findOrFail($areaId);
        if ((int) $area->city_id !== (int) $city->id) {
            throw new RuntimeException('Selected area does not belong to the selected city.');
        }

        $fullAddress = trim($addressLine).', '.$area->name.', '.$city->name;
        $chargesQuote = app(ChargeService::class)->quotePackage(
            $areaId,
            $quantity,
            collect($quote['selections'])
                ->map(fn ($row) => [
                    'menu_item_id' => (int) $row['menu_item_id'],
                    'day_count' => (int) $row['day_count'],
                ])
                ->values()
                ->all()
        );
        $chargesTotal = (int) ($chargesQuote['total'] ?? 0);

        $originalTotal = (int) $quote['total_amount'];
        $discountAmount = 0;
        $coupon = null;

        if (filled($couponCode)) {
            $quoted = app(CouponService::class)->quote(
                (string) $couponCode,
                $user,
                CouponRedemption::CONTEXT_PACKAGE,
                $originalTotal
            );
            $coupon = $quoted['coupon'];
            $discountAmount = (int) $quoted['discount_amount'];
        }

        $total = max(0, $originalTotal - $discountAmount) + $chargesTotal;

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
            $total,
            $originalTotal,
            $discountAmount,
            $coupon,
            $chargesTotal,
            $chargesQuote
        ) {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if (PackageSubscription::userHasPackageForMonth((int) $locked->id, (string) $quote['target_month'])) {
                $label = Carbon::createFromFormat('Y-m', (string) $quote['target_month'])
                    ->timezone(OrderCutoff::timezone())
                    ->format('F Y');

                throw new RuntimeException(
                    'You already ordered a package for '.$label.'. That month is locked — choose another month.'
                );
            }

            if ($paymentMethod === 'balance') {
                if ($total > 0 && (int) $locked->balance < $total) {
                    throw new RuntimeException('Insufficient Middo Balance. Top up your wallet to purchase this package.');
                }
            } elseif ($paymentMethod === 'gateway') {
                if ($total > 0) {
                    if (! filled($gatewayPaymentToken)) {
                        throw new RuntimeException('Start online payment before confirming the package.');
                    }

                    $fingerprint = PackageGatewayCheckout::cartMetadata(
                        (int) $package->id,
                        $quantity,
                        $omittedWeekdays,
                        (string) $quote['target_month'],
                        collect($quote['selections'])
                            ->map(fn ($row) => [
                                'menu_item_id' => (int) $row['menu_item_id'],
                                'day_count' => (int) $row['day_count'],
                            ])
                            ->values()
                            ->all(),
                        $total,
                        $areaId
                    );

                    $consumed = PackageGatewayCheckout::consumePayment(
                        $gatewayPaymentToken,
                        (int) $locked->id,
                        $total,
                        $fingerprint
                    );

                    if (! ($consumed['ok'] ?? false)) {
                        throw new RuntimeException($consumed['message'] ?? 'Complete online payment first.');
                    }
                }
            } else {
                throw new RuntimeException('Invalid payment method.');
            }

            $subscription = PackageSubscription::create([
                'user_id' => $locked->id,
                'meal_package_id' => $package->id,
                'quantity' => $quantity,
                'start_date' => $quote['start_date'],
                'end_date' => $quote['end_date'],
                'target_month' => $quote['target_month'],
                'omitted_weekdays' => $omittedWeekdays,
                'billable_days' => $quote['billable_days'],
                'price_per_day' => $quote['price_per_day'],
                'total_amount' => $originalTotal,
                'charges_amount' => $chargesTotal,
                'amount_paid' => $total,
                'payment_status' => 'paid',
                'coupon_id' => $coupon?->id,
                'discount_amount' => $discountAmount,
                'status' => PackageSubscription::STATUS_ACTIVE,
                'schedule_status' => PackageSubscription::SCHEDULE_AWAITING,
                'delivery_time' => $deliveryTime,
                'address' => $fullAddress,
                'receiver_name' => $receiverName,
                'receiver_mobile' => $receiverMobile,
                'area_id' => $areaId,
                'created_by' => $locked->id,
            ]);

            foreach ($quote['selections'] as $selection) {
                PackageSubscriptionSelection::create([
                    'package_subscription_id' => $subscription->id,
                    'menu_item_id' => $selection['menu_item_id'],
                    'day_count' => $selection['day_count'],
                ]);
            }

            app(ChargeService::class)->attachToPackage($subscription, $chargesQuote['lines'] ?? []);

            if ($paymentMethod === 'balance' && $total > 0) {
                WalletLedger::debit(
                    $locked,
                    $total,
                    'Package prepayment: '.$package->name,
                    $subscription
                );
            }

            if ($coupon && $discountAmount > 0) {
                app(CouponService::class)->redeem(
                    $coupon,
                    $locked,
                    CouponRedemption::CONTEXT_PACKAGE,
                    $originalTotal,
                    $discountAmount,
                    null,
                    $subscription,
                    [
                        'meal_package_id' => $package->id,
                        'target_month' => $quote['target_month'],
                    ]
                );
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
                'subscription' => $subscription->fresh(['package', 'selections.menuItem', 'orders.menuItem']),
                'orders' => collect(),
            ];
        });
    }

    /**
     * Operations assigns exact delivery dates + menus from the corporate selection.
     *
     * @param  array<int, array{date:string, menu_item_id:int}>  $assignments
     * @return array{subscription: PackageSubscription, orders: Collection<int, Order>}
     */
    public function assignSchedule(
        User $actor,
        PackageSubscription $subscription,
        array $assignments,
    ): array {
        $this->assertStaffActor($actor);

        return DB::transaction(function () use ($actor, $subscription, $assignments) {
            /** @var PackageSubscription $lockedSub */
            $lockedSub = PackageSubscription::query()
                ->with('selections')
                ->lockForUpdate()
                ->findOrFail($subscription->id);

            if ($lockedSub->status !== PackageSubscription::STATUS_ACTIVE) {
                throw new RuntimeException('Only active subscriptions can be scheduled.');
            }

            if ($lockedSub->isScheduled() && $lockedSub->orders()->exists()) {
                throw new RuntimeException('This subscription already has a delivery schedule. Use swap/skip on individual days.');
            }

            $normalized = collect($assignments)
                ->map(fn ($row) => [
                    'date' => Carbon::parse($row['date'] ?? null)->toDateString(),
                    'menu_item_id' => (int) ($row['menu_item_id'] ?? 0),
                ])
                ->filter(fn ($row) => $row['menu_item_id'] > 0)
                ->values();

            if ($normalized->isEmpty()) {
                throw new RuntimeException('Assign at least one delivery date.');
            }

            if ($normalized->pluck('date')->unique()->count() !== $normalized->count()) {
                throw new RuntimeException('Each delivery date can only be assigned once.');
            }

            $expectedDays = (int) $lockedSub->billable_days;
            if ($normalized->count() !== $expectedDays) {
                throw new RuntimeException(
                    'Assign exactly '.$expectedDays.' delivery day(s) to match the prepaid package.'
                );
            }

            $omitted = PackageBilling::normalizeOmittedWeekdays($lockedSub->omitted_weekdays ?? []);
            $available = PackageBilling::availableDatesInMonth(
                (string) ($lockedSub->target_month ?: $lockedSub->start_date->format('Y-m')),
                $omitted
            )->flip();

            $selectionCounts = $lockedSub->selections
                ->mapWithKeys(fn ($sel) => [(int) $sel->menu_item_id => (int) $sel->day_count]);
            $assignedCounts = [];

            foreach ($normalized as $row) {
                if (! $available->has($row['date'])) {
                    throw new RuntimeException(
                        $row['date'].' is not an eligible delivery date (outside month, omitted weekday, or past cutoff).'
                    );
                }

                $menuId = $row['menu_item_id'];
                if (! $selectionCounts->has($menuId)) {
                    throw new RuntimeException('Menu item #'.$menuId.' was not part of the corporate selection.');
                }

                $assignedCounts[$menuId] = ($assignedCounts[$menuId] ?? 0) + 1;
            }

            foreach ($selectionCounts as $menuId => $dayCount) {
                if ((int) ($assignedCounts[$menuId] ?? 0) !== (int) $dayCount) {
                    $name = MenuItem::query()->whereKey($menuId)->value('name') ?? ('#'.$menuId);
                    throw new RuntimeException(
                        'Menu "'.$name.'" must be assigned exactly '.$dayCount.' day(s).'
                    );
                }
            }

            $lineTotal = (int) $lockedSub->price_per_day * (int) $lockedSub->quantity;
            $createdOrderIds = [];

            foreach ($normalized->sortBy('date')->values() as $row) {
                $order = Order::create([
                    'user_id' => $lockedSub->user_id,
                    'menu_item_id' => $row['menu_item_id'],
                    'package_subscription_id' => $lockedSub->id,
                    'quantity' => $lockedSub->quantity,
                    'delivery_date' => $row['date'],
                    'delivery_time' => $lockedSub->delivery_time,
                    'total_amount' => $lineTotal,
                    'amount_paid' => $lineTotal,
                    'prepaid_amount' => $lineTotal,
                    'cash_collected' => 0,
                    'address' => $lockedSub->address,
                    'receiver_name' => $lockedSub->receiver_name,
                    'receiver_mobile' => $lockedSub->receiver_mobile,
                    'area_id' => $lockedSub->area_id,
                    'order_status' => 'pending',
                    'payment_status' => 'paid',
                    'payment_method' => 'balance',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $createdOrderIds[] = $order->id;
            }

            $grouper = app(MealOrderGrouper::class);
            $orders = Order::query()->whereIn('id', $createdOrderIds)->orderBy('delivery_date')->get();
            foreach ($orders as $order) {
                $grouper->assignOrder($order->load('user'), $actor->id);
            }

            $dates = $orders->pluck('delivery_date')->map(fn ($d) => $d->toDateString())->all();
            $lockedSub->update([
                'schedule_status' => PackageSubscription::SCHEDULE_SCHEDULED,
                'start_date' => $dates[0] ?? $lockedSub->start_date,
                'end_date' => $dates[array_key_last($dates)] ?? $lockedSub->end_date,
            ]);

            return [
                'subscription' => $lockedSub->fresh(['package', 'user', 'selections.menuItem', 'orders.menuItem']),
                'orders' => $orders->load('menuItem'),
            ];
        });
    }

    /**
     * Skip a pending package delivery day before cutoff; refund the allocated prepaid amount.
     *
     * @return array{order: Order, refunded_amount: int}
     */
    public function skipDay(User $user, Order $order): array
    {
        if ((int) $order->user_id !== (int) $user->id) {
            throw new RuntimeException('Order not found.');
        }

        return $this->skipDayInternal($user, $order, $user->id);
    }

    /**
     * Admin/operation skip of a package day (refunds the corporate wallet).
     *
     * @return array{order: Order, refunded_amount: int}
     */
    public function skipDayAsStaff(User $actor, Order $order): array
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

            // Unscheduled prepaid packages: refund the full prepaid amount.
            if ($lockedSub->isAwaitingSchedule() && $orders->isEmpty()) {
                $refund = PackageRefund::subscriptionPrepaidAmount($lockedSub);
                if ($refund > 0) {
                    WalletLedger::credit(
                        $owner,
                        $refund,
                        WalletTransaction::TYPE_REFUND,
                        'Package subscription cancelled before schedule — refund for subscription #'.$lockedSub->id,
                        $lockedSub
                    );
                    $refunded = $refund;
                }

                $lockedSub->update([
                    'status' => PackageSubscription::STATUS_CANCELLED,
                ]);

                return [
                    'subscription' => $lockedSub->fresh(['package', 'user', 'orders.menuItem', 'selections.menuItem']),
                    'cancelled_orders' => 0,
                    'refunded_amount' => $refunded,
                ];
            }

            foreach ($orders as $order) {
                if (! OrderCutoff::allowsModification($order)) {
                    continue;
                }

                $lineRefund = PackageRefund::orderRefundAmount($order);
                $this->cancelPackageOrder($order, $owner, $actor->id, $lineRefund, 'Package subscription cancelled — refund for order #'.$order->id);
                $cancelled++;
                $refunded += $lineRefund;
            }

            $lockedSub->update([
                'status' => PackageSubscription::STATUS_CANCELLED,
            ]);

            return [
                'subscription' => $lockedSub->fresh(['package', 'user', 'orders.menuItem', 'selections.menuItem']),
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
                $lineRefund = PackageRefund::orderRefundAmount($order);
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

    protected function skipDayInternal(User $walletOwner, Order $order, int $actorId): array
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

            $locked->loadMissing('packageSubscription.orders');
            $refund = PackageRefund::orderRefundAmount($locked);
            $this->cancelPackageOrder(
                $locked,
                $walletOwner,
                $actorId,
                $refund,
                'Package day skipped — refund for order #'.$locked->id
            );

            return [
                'order' => $locked->fresh('menuItem'),
                'refunded_amount' => $refund,
            ];
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

        OrderTransition::apply($order, OrderTransition::CANCELLED, [
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
