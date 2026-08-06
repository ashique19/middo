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
use App\Models\PackageSubscriptionEvent;
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
                    'unit_price' => (int) ($selection['unit_price'] ?? 0),
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
                // Persist local 01… only — never SMS-normalized 880… on users.mobile.
                $localMobile = CorporateOrderPrepayment::toLocalMobile($receiverMobile);
                if ($localMobile !== '' && $localMobile !== (string) $locked->mobile) {
                    $taken = User::query()
                        ->where('mobile', $localMobile)
                        ->whereKeyNot($locked->id)
                        ->exists();
                    if (! $taken) {
                        $profileUpdate['mobile'] = $localMobile;
                    }
                }
            }
            $locked->update($profileUpdate);

            return [
                'subscription' => $subscription->fresh(['package', 'selections.menuItem', 'orders.menuItem']),
                'orders' => collect(),
            ];
        });
    }

    /**
     * Operations confirms one or more delivery dates + menus from the corporate selection.
     * Partial confirms are allowed; remaining days stay open until later.
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

            if (! $lockedSub->canReceiveScheduleAssignments()) {
                throw new RuntimeException(
                    'This subscription is fully scheduled. Use swap/skip on individual days.'
                );
            }

            $normalized = collect($assignments)
                ->map(fn ($row) => [
                    'date' => Carbon::parse($row['date'] ?? null)->toDateString(),
                    'menu_item_id' => (int) ($row['menu_item_id'] ?? 0),
                ])
                ->filter(fn ($row) => $row['menu_item_id'] > 0)
                ->values();

            if ($normalized->isEmpty()) {
                throw new RuntimeException('Confirm at least one delivery date.');
            }

            if ($normalized->pluck('date')->unique()->count() !== $normalized->count()) {
                throw new RuntimeException('Each delivery date can only be assigned once.');
            }

            $alreadyDated = $lockedSub->orders()
                ->get()
                ->mapWithKeys(fn (Order $order) => [
                    $order->delivery_date->toDateString() => true,
                ]);

            $omitted = PackageBilling::normalizeOmittedWeekdays($lockedSub->omitted_weekdays ?? []);
            $available = PackageBilling::availableDatesInMonth(
                (string) ($lockedSub->target_month ?: $lockedSub->start_date->format('Y-m')),
                $omitted
            )->flip();

            $remainingCounts = $lockedSub->remainingSelectionCounts();
            $unitPrices = $lockedSub->selections
                ->mapWithKeys(fn ($sel) => [(int) $sel->menu_item_id => (int) $sel->unit_price]);
            $assignedCounts = [];

            foreach ($normalized as $row) {
                if ($alreadyDated->has($row['date'])) {
                    throw new RuntimeException($row['date'].' is already confirmed for this package.');
                }

                if (! $available->has($row['date'])) {
                    throw new RuntimeException(
                        $row['date'].' is not an eligible delivery date (outside month, omitted weekday, or past cutoff).'
                    );
                }

                $menuId = $row['menu_item_id'];
                if (! array_key_exists($menuId, $remainingCounts)) {
                    throw new RuntimeException('Menu item #'.$menuId.' was not part of the corporate selection.');
                }

                $assignedCounts[$menuId] = ($assignedCounts[$menuId] ?? 0) + 1;
                if ($assignedCounts[$menuId] > (int) $remainingCounts[$menuId]) {
                    $name = MenuItem::query()->whereKey($menuId)->value('name') ?? ('#'.$menuId);
                    throw new RuntimeException(
                        'Menu "'.$name.'" only has '.(int) $remainingCounts[$menuId]
                        .' remaining prepaid day(s).'
                    );
                }
            }

            $qty = max(1, (int) $lockedSub->quantity);
            $createdOrderIds = [];

            foreach ($normalized->sortBy('date')->values() as $row) {
                $unitPrice = (int) ($unitPrices[(int) $row['menu_item_id']] ?? 0);
                if ($unitPrice < 1) {
                    $unitPrice = (int) (MenuItem::query()->whereKey($row['menu_item_id'])->value('price') ?? 0);
                }
                $lineTotal = $unitPrice * $qty;

                $order = Order::create([
                    'user_id' => $lockedSub->user_id,
                    'menu_item_id' => $row['menu_item_id'],
                    'package_subscription_id' => $lockedSub->id,
                    'quantity' => $qty,
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
            $orders = Order::query()
                ->whereIn('id', $createdOrderIds)
                ->with('menuItem')
                ->orderBy('delivery_date')
                ->get();
            foreach ($orders as $order) {
                $grouper->assignOrder($order->load('user'), $actor->id);
            }

            $freshSub = $lockedSub->fresh(['selections', 'orders']);
            $this->syncScheduleStatus($freshSub);

            $dayParts = $orders->map(function (Order $order) {
                $date = $order->delivery_date?->format('M d, Y') ?? 'unknown date';
                $menu = $order->menuItem?->name ?? ('menu #'.$order->menu_item_id);

                return $date.' — '.$menu;
            })->values()->all();

            $summary = count($dayParts) === 1
                ? 'Confirmed '.$dayParts[0].'.'
                : 'Confirmed '.count($dayParts).' delivery day(s): '.implode('; ', $dayParts).'.';

            $this->recordEvent(
                $lockedSub->id,
                PackageSubscriptionEvent::TYPE_SCHEDULE_ASSIGNED,
                $summary,
                [
                    'order_ids' => $createdOrderIds,
                    'dates' => $orders->map(fn (Order $o) => $o->delivery_date->toDateString())->values()->all(),
                    'menus' => $orders->map(fn (Order $o) => [
                        'order_id' => $o->id,
                        'date' => $o->delivery_date->toDateString(),
                        'menu_item_id' => (int) $o->menu_item_id,
                        'menu_name' => $o->menuItem?->name,
                    ])->values()->all(),
                ],
                $actor->id
            );

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
    public function skipDay(User $user, Order $order, string $reason = ''): array
    {
        throw new RuntimeException('Package day cancel and refund is handled by Middo operations only.');
    }

    /**
     * Admin/operation skip of a package day (refunds the corporate wallet).
     *
     * @return array{order: Order, refunded_amount: int}
     */
    public function skipDayAsStaff(User $actor, Order $order, string $reason = ''): array
    {
        $this->assertStaffActor($actor);

        $owner = User::query()->findOrFail($order->user_id);

        return $this->skipDayInternal($owner, $order, $actor->id, $reason);
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

                $this->recordEvent(
                    $lockedSub->id,
                    PackageSubscriptionEvent::TYPE_REMAINING_CANCELLED,
                    'Cancelled subscription before schedule. Refunded Tk '.number_format($refunded).'.',
                    ['cancelled_orders' => 0, 'refunded_amount' => $refunded],
                    $actor->id
                );

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

            // Partially scheduled packages leave prepaid days without orders.
            // Refund that residual so cancelRemaining returns the full unused prepaid.
            $residual = PackageRefund::unscheduledPrepaidResidual($lockedSub->fresh(['orders']));
            if ($residual > 0) {
                WalletLedger::credit(
                    $owner,
                    $residual,
                    WalletTransaction::TYPE_REFUND,
                    'Package subscription cancelled — refund for unscheduled prepaid days on subscription #'.$lockedSub->id,
                    $lockedSub
                );
                $refunded += $residual;
            }

            $lockedSub->update([
                'status' => PackageSubscription::STATUS_CANCELLED,
            ]);

            $this->recordEvent(
                $lockedSub->id,
                PackageSubscriptionEvent::TYPE_REMAINING_CANCELLED,
                'Cancelled '.$cancelled.' remaining day(s). Refunded Tk '.number_format($refunded).'.',
                ['cancelled_orders' => $cancelled, 'refunded_amount' => $refunded],
                $actor->id
            );

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

            $this->recordEvent(
                $lockedSub->id,
                PackageSubscriptionEvent::TYPE_DELIVERY_UPDATED,
                'Updated delivery details on '.$orders->count().' future pending order(s).',
                [
                    'updated_orders' => $orders->count(),
                    'delivery_time' => $deliveryTime,
                ],
                $actor->id
            );

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

            $subscription = $locked->packageSubscription()->with('selections')->first();
            $unitPrice = (int) ($subscription?->selections
                ->firstWhere('menu_item_id', (int) $menu->id)
                ?->unit_price ?? $menu->price);
            $lineTotal = $unitPrice * max(1, (int) $locked->quantity);

            $locked->update([
                'menu_item_id' => $menu->id,
                'total_amount' => $lineTotal,
                'amount_paid' => $lineTotal,
                'prepaid_amount' => $lineTotal,
                'updated_by' => $actor->id,
            ]);

            $fresh = $locked->fresh(['menuItem', 'user', 'packageSubscription.package']);
            app(MealOrderGrouper::class)->assignOrder($fresh, $actor->id);

            $this->recordEvent(
                (int) $fresh->package_subscription_id,
                PackageSubscriptionEvent::TYPE_MENU_SWAPPED,
                'Swapped order #'.$fresh->id.' to '.$menu->name.'.',
                [
                    'order_id' => $fresh->id,
                    'menu_item_id' => $menu->id,
                    'delivery_date' => $fresh->delivery_date?->toDateString(),
                ],
                $actor->id
            );

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

        $this->recordEvent(
            $subscription->id,
            PackageSubscriptionEvent::TYPE_FORCE_COMPLETED,
            'Subscription force-completed.',
            null,
            $actor->id
        );

        return $subscription->fresh(['package', 'user']);
    }

    protected function skipDayInternal(User $walletOwner, Order $order, int $actorId, string $reason = ''): array
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

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A cancellation reason is required.');
        }

        if (mb_strlen($reason) > 500) {
            throw new RuntimeException('Cancellation reason must be 500 characters or fewer.');
        }

        return DB::transaction(function () use ($walletOwner, $order, $actorId, $reason) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->order_status !== 'pending' || ! OrderCutoff::allowsModification($locked)) {
                throw new RuntimeException(OrderCutoff::modificationDeniedMessage());
            }

            $locked->loadMissing('packageSubscription.selections');
            $refund = PackageRefund::orderRefundAmount($locked);
            $subscriptionId = (int) $locked->package_subscription_id;
            $menuItemId = (int) $locked->menu_item_id;
            $this->cancelPackageOrder(
                $locked,
                $walletOwner,
                $actorId,
                $refund,
                'Package day cancelled — refund for order #'.$locked->id
            );

            // Keep the menu tagged on the cancelled order and shrink prepaid quota
            // (do not free the slot for another date — same as unconfirmed cancel).
            $subscription = PackageSubscription::query()
                ->with('selections')
                ->lockForUpdate()
                ->find($subscriptionId);
            if ($subscription) {
                $selection = $subscription->selections->firstWhere('menu_item_id', $menuItemId);
                if ($selection && (int) $selection->day_count > 0) {
                    $selection->update(['day_count' => (int) $selection->day_count - 1]);
                }
                $subscription->update([
                    'billable_days' => max(0, (int) $subscription->billable_days - 1),
                ]);
                $this->syncScheduleStatus($subscription->fresh(['selections', 'orders']));
            }

            $this->recordEvent(
                $subscriptionId,
                PackageSubscriptionEvent::TYPE_DAY_CANCELLED,
                'Cancelled order #'.$locked->id.' and refunded Tk '.number_format($refund).'.',
                [
                    'order_id' => $locked->id,
                    'delivery_date' => $locked->delivery_date?->toDateString(),
                    'menu_item_id' => $menuItemId,
                    'refunded_amount' => $refund,
                    'reason' => $reason,
                    'reduced_selection_day_count' => true,
                ],
                $actorId
            );

            return [
                'order' => $locked->fresh('menuItem'),
                'refunded_amount' => $refund,
            ];
        });
    }

    /**
     * Undo a confirmed pending day: remove the order (no refund) so it returns to the unconfirmed list.
     *
     * @return array{subscription: PackageSubscription, order_id: int}
     */
    public function unconfirmDay(User $actor, Order $order): array
    {
        $this->assertStaffActor($actor);

        if (! $order->package_subscription_id) {
            throw new RuntimeException('This order is not part of a meal package.');
        }

        if ($order->order_status !== 'pending') {
            throw new RuntimeException('Only pending package days can be unconfirmed.');
        }

        if (! OrderCutoff::allowsModification($order)) {
            throw new RuntimeException(OrderCutoff::modificationDeniedMessage());
        }

        return DB::transaction(function () use ($actor, $order) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->order_status !== 'pending' || ! OrderCutoff::allowsModification($locked)) {
                throw new RuntimeException(OrderCutoff::modificationDeniedMessage());
            }

            $subscriptionId = (int) $locked->package_subscription_id;
            $orderId = (int) $locked->id;
            $date = $locked->delivery_date?->toDateString();
            $locked->loadMissing('menuItem');
            $menuName = $locked->menuItem?->name ?? ('#'.$locked->menu_item_id);
            $menuItemId = (int) $locked->menu_item_id;

            try {
                app(OrderGroupManager::class)->ungroup($locked->id);
            } catch (\Throwable) {
                // Order may already be ungrouped.
            }

            $locked->delete();

            $subscription = PackageSubscription::query()->findOrFail($subscriptionId);
            $this->syncScheduleStatus($subscription);

            $this->recordEvent(
                $subscriptionId,
                PackageSubscriptionEvent::TYPE_DAY_UNCONFIRMED,
                'Unconfirmed '.$date.' ('.$menuName.') — order #'.$orderId.' removed.',
                [
                    'order_id' => $orderId,
                    'delivery_date' => $date,
                    'menu_item_id' => $menuItemId,
                ],
                $actor->id
            );

            return [
                'subscription' => $subscription->fresh(['package', 'user', 'selections.menuItem', 'orders.menuItem']),
                'order_id' => $orderId,
            ];
        });
    }

    /**
     * Cancel an unconfirmed prepaid day: create a cancelled order placeholder, refund,
     * and shrink the menu selection quota so the day is not free to reassign.
     *
     * @return array{order: Order, refunded_amount: int, subscription: PackageSubscription}
     */
    public function cancelUnscheduledDay(
        User $actor,
        PackageSubscription $subscription,
        string $date,
        int $menuItemId,
        string $reason = '',
    ): array {
        $this->assertStaffActor($actor);

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A cancellation reason is required.');
        }

        if (mb_strlen($reason) > 500) {
            throw new RuntimeException('Cancellation reason must be 500 characters or fewer.');
        }

        $date = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($actor, $subscription, $date, $menuItemId, $reason) {
            /** @var PackageSubscription $lockedSub */
            $lockedSub = PackageSubscription::query()
                ->with('selections')
                ->lockForUpdate()
                ->findOrFail($subscription->id);

            if ($lockedSub->status !== PackageSubscription::STATUS_ACTIVE) {
                throw new RuntimeException('Only active subscriptions can cancel prepaid days.');
            }

            $occupied = $lockedSub->orders()
                ->whereDate('delivery_date', $date)
                ->exists();
            if ($occupied) {
                throw new RuntimeException($date.' already has a delivery day on this package.');
            }

            $omitted = PackageBilling::normalizeOmittedWeekdays($lockedSub->omitted_weekdays ?? []);
            $available = PackageBilling::availableDatesInMonth(
                (string) ($lockedSub->target_month ?: $lockedSub->start_date->format('Y-m')),
                $omitted
            );
            if (! $available->contains($date)) {
                throw new RuntimeException(
                    $date.' is not an eligible delivery date (outside month, omitted weekday, or past cutoff).'
                );
            }

            $remaining = $lockedSub->remainingSelectionCounts();
            if (! array_key_exists($menuItemId, $remaining) || (int) $remaining[$menuItemId] < 1) {
                throw new RuntimeException('No remaining prepaid quota for the selected menu.');
            }

            /** @var PackageSubscriptionSelection|null $selection */
            $selection = $lockedSub->selections->firstWhere('menu_item_id', $menuItemId);
            if (! $selection || (int) $selection->day_count < 1) {
                throw new RuntimeException('Selected menu is not part of this package.');
            }

            $unitPrice = (int) $selection->unit_price;
            if ($unitPrice < 1) {
                $unitPrice = (int) (MenuItem::query()->whereKey($menuItemId)->value('price') ?? 0);
            }
            $qty = max(1, (int) $lockedSub->quantity);
            $lineTotal = $unitPrice * $qty;

            $order = Order::create([
                'user_id' => $lockedSub->user_id,
                'menu_item_id' => $menuItemId,
                'package_subscription_id' => $lockedSub->id,
                'quantity' => $qty,
                'delivery_date' => $date,
                'delivery_time' => $lockedSub->delivery_time,
                'total_amount' => $lineTotal,
                'amount_paid' => $lineTotal,
                'prepaid_amount' => $lineTotal,
                'cash_collected' => 0,
                'address' => $lockedSub->address,
                'receiver_name' => $lockedSub->receiver_name,
                'receiver_mobile' => $lockedSub->receiver_mobile,
                'area_id' => $lockedSub->area_id,
                'order_status' => 'cancelled',
                'payment_status' => 'paid',
                'payment_method' => 'balance',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $refund = PackageRefund::orderRefundAmount($order->fresh('packageSubscription'));
            $owner = User::query()->lockForUpdate()->findOrFail($lockedSub->user_id);
            if ($refund > 0) {
                WalletLedger::credit(
                    $owner,
                    $refund,
                    WalletTransaction::TYPE_REFUND,
                    'Package unconfirmed day cancelled — refund for order #'.$order->id,
                    $order
                );
            }

            $selection->update(['day_count' => max(0, (int) $selection->day_count - 1)]);
            $lockedSub->update([
                'billable_days' => max(0, (int) $lockedSub->billable_days - 1),
            ]);

            $this->syncScheduleStatus($lockedSub->fresh(['selections', 'orders']));

            $menuName = MenuItem::query()->whereKey($menuItemId)->value('name') ?? ('#'.$menuItemId);
            $this->recordEvent(
                $lockedSub->id,
                PackageSubscriptionEvent::TYPE_DAY_CANCELLED,
                'Cancelled unconfirmed '.$date.' ('.$menuName.') and refunded Tk '.number_format($refund).'.',
                [
                    'order_id' => $order->id,
                    'delivery_date' => $date,
                    'menu_item_id' => $menuItemId,
                    'refunded_amount' => $refund,
                    'reason' => $reason,
                    'unscheduled' => true,
                    'reduced_selection_day_count' => true,
                ],
                $actor->id
            );

            return [
                'order' => $order->fresh('menuItem'),
                'refunded_amount' => $refund,
                'subscription' => $lockedSub->fresh(['package', 'user', 'selections.menuItem', 'orders.menuItem']),
            ];
        });
    }

    /**
     * Re-activate a cancelled package day (future only): debit the prior refund and restore pending.
     *
     * @return array{order: Order, debited_amount: int}
     */
    public function reactivateDay(User $actor, Order $order): array
    {
        $this->assertStaffActor($actor);
        $actor->loadMissing('role');

        if (! $order->package_subscription_id) {
            throw new RuntimeException('This order is not part of a meal package.');
        }

        if ($order->order_status !== 'cancelled') {
            throw new RuntimeException('Only cancelled package days can be re-activated.');
        }

        $subscription = PackageSubscription::query()->findOrFail($order->package_subscription_id);
        if ($subscription->status !== PackageSubscription::STATUS_ACTIVE) {
            throw new RuntimeException('Only active subscriptions can re-activate delivery days.');
        }

        if (! OrderCutoff::deliveryDateStillOpen($order)) {
            throw new RuntimeException('This delivery date is past the order cutoff and cannot be re-activated.');
        }

        return DB::transaction(function () use ($actor, $order, $subscription) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->order_status !== 'cancelled') {
                throw new RuntimeException('Only cancelled package days can be re-activated.');
            }

            if (! OrderCutoff::deliveryDateStillOpen($locked)) {
                throw new RuntimeException('This delivery date is past the order cutoff and cannot be re-activated.');
            }

            // Another non-cancelled order must not already occupy this date.
            $dateTaken = Order::query()
                ->where('package_subscription_id', $locked->package_subscription_id)
                ->where('order_status', '!=', 'cancelled')
                ->whereDate('delivery_date', $locked->delivery_date)
                ->where('id', '!=', $locked->id)
                ->exists();

            if ($dateTaken) {
                throw new RuntimeException(
                    $locked->delivery_date->toDateString().' already has an active delivery day.'
                );
            }

            /** @var PackageSubscription $freshSub */
            $freshSub = PackageSubscription::query()
                ->with(['selections', 'orders'])
                ->lockForUpdate()
                ->findOrFail($subscription->id);

            $menuId = (int) $locked->menu_item_id;
            $cancelEvent = PackageSubscriptionEvent::query()
                ->where('package_subscription_id', $freshSub->id)
                ->where('type', PackageSubscriptionEvent::TYPE_DAY_CANCELLED)
                ->latest('id')
                ->get()
                ->first(fn (PackageSubscriptionEvent $event) => (int) ($event->meta['order_id'] ?? 0) === (int) $locked->id);

            $cancelMeta = is_array($cancelEvent?->meta) ? $cancelEvent->meta : [];
            $reducedSelection = ! empty($cancelMeta['reduced_selection_day_count']);

            if ($reducedSelection) {
                $selection = $freshSub->selections->firstWhere('menu_item_id', $menuId);
                if ($selection) {
                    $selection->update(['day_count' => (int) $selection->day_count + 1]);
                }
                $freshSub->update([
                    'billable_days' => (int) $freshSub->billable_days + 1,
                ]);
                $freshSub = $freshSub->fresh(['selections', 'orders']);
            }

            $remaining = $freshSub->remainingSelectionCounts();
            if (! array_key_exists($menuId, $remaining) || (int) $remaining[$menuId] < 1) {
                throw new RuntimeException(
                    'No remaining prepaid quota for this menu. Unconfirm or cancel another day first.'
                );
            }

            $debit = (int) ($cancelMeta['refunded_amount'] ?? 0);
            if ($debit < 1) {
                $debit = PackageRefund::orderRefundAmount($locked);
            }
            if ($debit < 1) {
                throw new RuntimeException('Cannot re-activate a day with no prepaid amount.');
            }

            $owner = User::query()->lockForUpdate()->findOrFail($locked->user_id);
            WalletLedger::debit(
                $owner,
                $debit,
                'Package day re-activated — debit for order #'.$locked->id,
                $locked
            );

            // Cancelled is terminal in OrderTransition; restore pending directly for package days.
            $locked->update([
                'order_status' => 'pending',
                'updated_by' => $actor->id,
            ]);

            $fresh = $locked->fresh(['menuItem', 'user', 'packageSubscription.package', 'area']);
            if (! $fresh->area_id && $fresh->user?->area_id) {
                $fresh->update(['area_id' => $fresh->user->area_id]);
                $fresh = $fresh->fresh(['menuItem', 'user', 'packageSubscription.package']);
            }

            app(MealOrderGrouper::class)->assignOrder($fresh, $actor->id);

            $this->syncScheduleStatus($freshSub->fresh(['selections', 'orders']));

            $menuName = $fresh->menuItem?->name ?? ('#'.$menuId);
            $this->recordEvent(
                (int) $locked->package_subscription_id,
                PackageSubscriptionEvent::TYPE_DAY_REACTIVATED,
                'Re-activated order #'.$locked->id.' ('.$menuName.') back to pending and debited Tk '.number_format($debit).'.',
                [
                    'order_id' => $locked->id,
                    'delivery_date' => $locked->delivery_date?->toDateString(),
                    'menu_item_id' => $menuId,
                    'debited_amount' => $debit,
                ],
                $actor->id
            );

            return [
                'order' => $fresh->fresh(['menuItem', 'orderGroup']),
                'debited_amount' => $debit,
            ];
        });
    }

    /**
     * Recalculate schedule_status / window dates from non-cancelled confirmed orders.
     */
    public function syncScheduleStatus(PackageSubscription $subscription): void
    {
        $fresh = $subscription->relationLoaded('orders') && $subscription->relationLoaded('selections')
            ? $subscription
            : $subscription->fresh(['selections', 'orders']);

        $remainingAfter = $fresh->remainingBillableDays();
        $allDates = $fresh->orders
            ->where('order_status', '!=', 'cancelled')
            ->sortBy(fn (Order $o) => $o->delivery_date?->toDateString().'-'.$o->id)
            ->values()
            ->map(fn (Order $o) => $o->delivery_date->toDateString())
            ->all();

        $confirmedCount = count($allDates);

        if ($confirmedCount === 0) {
            $scheduleStatus = PackageSubscription::SCHEDULE_AWAITING;
        } elseif ($remainingAfter > 0) {
            $scheduleStatus = PackageSubscription::SCHEDULE_PARTIAL;
        } else {
            $scheduleStatus = PackageSubscription::SCHEDULE_SCHEDULED;
        }

        $payload = ['schedule_status' => $scheduleStatus];
        if ($confirmedCount > 0) {
            $payload['start_date'] = $allDates[0];
            $payload['end_date'] = $allDates[array_key_last($allDates)];
        }

        $subscription->update($payload);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    protected function recordEvent(
        int $subscriptionId,
        string $type,
        string $summary,
        ?array $meta = null,
        ?int $actorId = null,
    ): void {
        PackageSubscriptionEvent::create([
            'package_subscription_id' => $subscriptionId,
            'type' => $type,
            'summary' => $summary,
            'meta' => $meta,
            'created_by' => $actorId,
        ]);
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
        $actor->loadMissing('role');
        $role = $actor->role?->name;
        if (! in_array($role, ['admin', 'operation'], true)) {
            throw new RuntimeException('Only admin or operation staff can perform this action.');
        }
    }
}
