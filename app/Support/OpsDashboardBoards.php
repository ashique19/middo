<?php

namespace App\Support;

use App\Models\CashHandover;
use App\Models\KitchenBoxRequest;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\PackageSubscription;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Date-scoped boards for the Operation mobile home dashboard.
 */
class OpsDashboardBoards
{
    /**
     * @return array<string, mixed>
     */
    public static function forDate(string $date, int $userId): array
    {
        $day = Carbon::parse($date)->toDateString();
        $orders = self::ordersForDate($day);

        return [
            'date' => $day,
            'packages' => self::packagesBoard($day, $orders),
            'orders' => self::ordersBoard($orders),
            'grouping' => self::groupingBoard($orders),
            'cash_collection' => self::cashCollectionBoard($day, $orders),
            'box_requests' => self::boxRequestsBoard(),
            'complaints' => self::complaintsBoard(),
            'alerts_unread' => StaffAlerts::unreadCount($userId),
        ];
    }

    /**
     * @return Collection<int, Order>
     */
    protected static function ordersForDate(string $day): Collection
    {
        return Order::query()
            ->with([
                'menuItem:id,name',
                'user:id,first_name,last_name,mobile,company_name',
                'deliveryRider:id,first_name,last_name,mobile',
                'area:id,name',
                'orderGroup.kitchen:id,first_name,last_name',
                'packageSubscription.package:id,name',
                'cashHandoverOrder.handover',
            ])
            ->whereDate('delivery_date', $day)
            ->orderBy('delivery_time')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Order>  $dayOrders
     * @return array<string, mixed>
     */
    protected static function packagesBoard(string $day, Collection $dayOrders): array
    {
        $month = Carbon::parse($day)->format('Y-m');

        $unassigned = PackageSubscription::query()
            ->with([
                'user:id,first_name,last_name,mobile,company_name',
                'package:id,name',
                'orders:id,package_subscription_id,delivery_date,order_status',
            ])
            ->where('status', PackageSubscription::STATUS_ACTIVE)
            ->where('target_month', $month)
            ->whereIn('schedule_status', [
                PackageSubscription::SCHEDULE_AWAITING,
                PackageSubscription::SCHEDULE_PARTIAL,
            ])
            ->get()
            ->filter(function (PackageSubscription $sub) use ($day, $month) {
                $available = PackageBilling::availableDatesInMonth(
                    (string) ($sub->target_month ?: $month),
                    $sub->omitted_weekdays ?? []
                );

                if (! $available->contains($day)) {
                    return false;
                }

                return ! $sub->orders
                    ->where('order_status', '!=', OrderTransition::CANCELLED)
                    ->contains(function (Order $order) use ($day) {
                        $orderDate = $order->delivery_date?->toDateString() ?? (string) $order->delivery_date;

                        return $orderDate === $day;
                    });
            })
            ->values()
            ->map(fn (PackageSubscription $sub) => [
                'id' => $sub->id,
                'package_name' => $sub->package?->name ?? 'Package',
                'customer_name' => $sub->user?->company_name ?: $sub->user?->name,
                'customer_mobile' => $sub->user?->mobile,
                'quantity' => (int) ($sub->quantity ?? 1),
                'schedule_status' => $sub->schedule_status,
                'target_month' => $sub->target_month,
                'date' => $day,
            ])
            ->all();

        $packageOrders = $dayOrders
            ->filter(fn (Order $order) => $order->package_subscription_id !== null)
            ->values()
            ->map(fn (Order $order) => self::orderCard($order))
            ->all();

        return [
            'unassigned_meals' => [
                'count' => count($unassigned),
                'items' => $unassigned,
            ],
            'orders' => [
                'count' => count($packageOrders),
                'items' => $packageOrders,
            ],
        ];
    }

    /**
     * @param  Collection<int, Order>  $dayOrders
     * @return array<string, mixed>
     */
    protected static function ordersBoard(Collection $dayOrders): array
    {
        return [
            'all' => self::orderTab($dayOrders->values()),
            'package' => self::orderTab(
                $dayOrders->filter(fn (Order $order) => $order->package_subscription_id !== null)->values()
            ),
            'individual' => self::orderTab(
                $dayOrders->filter(fn (Order $order) => $order->package_subscription_id === null)->values()
            ),
        ];
    }

    /**
     * @param  Collection<int, Order>  $dayOrders
     * @return array<string, mixed>
     */
    protected static function groupingBoard(Collection $dayOrders): array
    {
        $tabs = [
            'ungrouped' => collect(),
            'grouped_pending' => collect(),
            'accepted' => collect(),
            'packed' => collect(),
            'picked' => collect(),
            'delivered' => collect(),
            'failed' => collect(),
        ];

        foreach ($dayOrders as $order) {
            $status = (string) $order->order_status;
            $group = $order->orderGroup;

            if ($status === OrderTransition::CANCELLED) {
                $tabs['failed']->push($order);

                continue;
            }

            if (in_array($status, [OrderTransition::DELIVERED, OrderTransition::DELIVERED_AND_PAID], true)) {
                $tabs['delivered']->push($order);

                continue;
            }

            if ($status === OrderTransition::ON_THE_WAY_TO_DELIVERY) {
                $tabs['picked']->push($order);

                continue;
            }

            if ($status === OrderTransition::PACKED) {
                $tabs['packed']->push($order);

                continue;
            }

            if (in_array($status, [
                OrderTransition::PROCESSING,
                OrderTransition::READY,
                OrderTransition::RIDER_ASSIGNED,
            ], true)) {
                $tabs['accepted']->push($order);

                continue;
            }

            if ($group === null) {
                $tabs['ungrouped']->push($order);
            } elseif ($group->kitchen_id === null || $status === 'pending') {
                $tabs['grouped_pending']->push($order);
            } else {
                $tabs['accepted']->push($order);
            }
        }

        return collect($tabs)
            ->map(fn (Collection $rows) => self::orderTab($rows->values()))
            ->all();
    }

    /**
     * @param  Collection<int, Order>  $dayOrders
     * @return array<string, mixed>
     */
    protected static function cashCollectionBoard(string $day, Collection $dayOrders): array
    {
        $atRider = $dayOrders
            ->filter(function (Order $order) {
                if (! in_array($order->order_status, [
                    OrderTransition::DELIVERED,
                    OrderTransition::DELIVERED_AND_PAID,
                ], true)) {
                    return false;
                }

                if ($order->dueToMiddoAmount() <= 0) {
                    return false;
                }

                $handover = $order->cashHandoverOrder?->handover;
                if ($handover && in_array($handover->status, [
                    CashHandover::STATUS_PENDING,
                    CashHandover::STATUS_ACCEPTED,
                    CashHandover::STATUS_PROPOSED_REJECT,
                ], true)) {
                    return false;
                }

                return true;
            })
            ->values()
            ->map(fn (Order $order) => array_merge(self::orderCard($order), [
                'cash_due' => $order->dueToMiddoAmount(),
                'rider_name' => $order->deliveryRider?->name,
            ]))
            ->all();

        $kitchen = self::handoversForDate($day, CashHandover::TARGET_KITCHEN);
        $middo = self::handoversForDate($day, CashHandover::TARGET_MIDDO);

        return [
            'at_rider' => [
                'count' => count($atRider),
                'amount' => (int) collect($atRider)->sum('cash_due'),
                'items' => $atRider,
            ],
            'kitchen' => [
                'count' => count($kitchen),
                'amount' => (int) collect($kitchen)->sum('amount'),
                'items' => $kitchen,
            ],
            'middo' => [
                'count' => count($middo),
                'amount' => (int) collect($middo)->sum('amount'),
                'items' => $middo,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function handoversForDate(string $day, string $target): array
    {
        return CashHandover::query()
            ->with([
                'rider:id,first_name,last_name,mobile',
                'orders:id,delivery_date,order_status,cash_due_to_middo,cash_collected',
            ])
            ->where('target', $target)
            ->whereIn('status', [
                CashHandover::STATUS_PENDING,
                CashHandover::STATUS_ACCEPTED,
                CashHandover::STATUS_PROPOSED_REJECT,
            ])
            ->where(function ($query) use ($day) {
                $query->whereDate('created_at', $day)
                    ->orWhereDate('accepted_at', $day)
                    ->orWhereHas('orders', fn ($oq) => $oq->whereDate('delivery_date', $day));
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (CashHandover $handover) => [
                'id' => $handover->id,
                'amount' => (int) $handover->amount,
                'status' => $handover->status,
                'target' => $handover->target,
                'rider_name' => $handover->rider?->name,
                'rider_mobile' => $handover->rider?->mobile,
                'created_at' => $handover->created_at?->toIso8601String(),
                'accepted_at' => $handover->accepted_at?->toIso8601String(),
                'order_ids' => $handover->orders->pluck('id')->values()->all(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected static function boxRequestsBoard(): array
    {
        $requests = KitchenBoxRequest::query()
            ->with(['kitchen:id,first_name,last_name,mobile'])
            ->where('status', KitchenBoxRequest::STATUS_PENDING)
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (KitchenBoxRequest $request) => OperationApiPresenter::boxRequest($request))
            ->all();

        return [
            'count' => count($requests),
            'items' => $requests,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function complaintsBoard(): array
    {
        $rows = OrderComplaint::query()
            ->with([
                'order.menuItem:id,name',
                'order.user:id,first_name,last_name,mobile,company_name',
            ])
            ->where('is_reply', false)
            ->where('status', OrderComplaint::STATUS_OPEN)
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (OrderComplaint $complaint) => OperationApiPresenter::complaint($complaint))
            ->all();

        return [
            'count' => count($rows),
            'items' => $rows,
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{count: int, items: list<array<string, mixed>>}
     */
    protected static function orderTab(Collection $orders): array
    {
        $items = $orders->map(fn (Order $order) => self::orderCard($order))->values()->all();

        return [
            'count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function orderCard(Order $order): array
    {
        $party = $order->partyPayload();
        $badge = self::paymentBadge($order);

        return [
            'id' => $order->id,
            'order_status' => $order->order_status,
            'delivery_date' => $order->delivery_date?->toDateString() ?? $order->delivery_date,
            'delivery_time' => $order->delivery_time,
            'quantity' => (int) $order->quantity,
            'menu_name' => $order->menuItem?->name,
            'customer_name' => $party['customer_name'] ?? ($order->user?->company_name ?: $order->user?->name),
            'customer_mobile' => $order->user?->mobile,
            'area_name' => $order->area?->name,
            'rider_name' => $order->deliveryRider?->name,
            'group_id' => $order->orderGroup?->id,
            'group_name' => $order->orderGroup?->name,
            'kitchen_id' => $order->orderGroup?->kitchen_id,
            'kitchen_name' => $order->orderGroup?->kitchen?->name,
            'is_package' => $order->package_subscription_id !== null,
            'package_name' => $order->packageSubscription?->package?->name,
            'payment_status' => $order->payment_status,
            'amount_due' => $order->amountDue(),
            'amount_paid' => $order->amountPaidValue(),
            'payment_badge' => $badge['key'],
            'payment_badge_label' => $badge['label'],
            'address' => $order->address,
        ];
    }

    /**
     * @return array{key: string, label: string}
     */
    protected static function paymentBadge(Order $order): array
    {
        if ($order->order_status === OrderTransition::CANCELLED) {
            return ['key' => 'rotten', 'label' => 'Cancelled'];
        }

        if (
            $order->isPaid()
            || $order->order_status === OrderTransition::DELIVERED_AND_PAID
            || $order->amountDue() <= 0
        ) {
            return ['key' => 'paid', 'label' => 'Paid'];
        }

        return ['key' => 'unpaid', 'label' => 'Unpaid'];
    }
}
