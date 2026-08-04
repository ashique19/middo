<?php

namespace App\Livewire\Delivery;

use App\Models\MiddoBoxLog;
use App\Models\Order;
use App\Models\User;
use App\Support\DeliveryAreaScope;
use App\Support\OrderMoneyFlow;
use App\Support\OrderTransition;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class KitchenDispatches extends Component
{
    use WithPagination;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function acceptOrder(int $orderId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        $riderId = (int) Auth::id();

        try {
            $label = DB::transaction(function () use ($orderId, $riderId) {
                $order = Order::query()
                    ->whereKey($orderId)
                    ->lockForUpdate()
                    ->first();

                if (! $order || ! $order->isAwaitingRiderAccept()) {
                    throw new \RuntimeException('This kitchen dispatch is no longer available to accept.');
                }

                $rider = User::query()->find($riderId);
                if (! $rider || ! DeliveryAreaScope::riderMayAccept($order, $rider)) {
                    throw new \RuntimeException('This run is outside your service areas.');
                }

                $boxes = $order->middoBoxes()->lockForUpdate()->get();

                if ($boxes->count() !== (int) $order->quantity) {
                    throw new \RuntimeException('Reserved boxes for this order are incomplete.');
                }

                $kitchenId = DB::table('order_group_orders')
                    ->join('order_groups', 'order_groups.id', '=', 'order_group_orders.order_group_id')
                    ->where('order_group_orders.order_id', $order->id)
                    ->value('order_groups.kitchen_id');

                foreach ($boxes as $box) {
                    if ($kitchenId && ! $box->isAtKitchen((int) $kitchenId)) {
                        throw new \RuntimeException("{$box->qr_code_id} is not at the kitchen anymore.");
                    }

                    $box->update([
                        'held_by_user_id' => $riderId,
                        'kitchen_id' => null,
                        'asset_status' => 'active',
                        'total_uses_count' => $box->total_uses_count + 1,
                        'last_scanned_at' => now(),
                    ]);

                    MiddoBoxLog::create([
                        'order_id' => $order->id,
                        'middo_box_id' => $box->id,
                        'custody_status' => 'in_transit',
                        'log_action' => 'picked_by_delivery_from_kitchen',
                    ]);
                }

                OrderTransition::apply($order, OrderTransition::ON_THE_WAY_TO_DELIVERY, [
                    'delivery_rider_id' => $riderId,
                    'updated_by' => $riderId,
                ]);

                OrderMoneyFlow::accrueDeliveryShareOnRunStart($order->fresh(['menuItem', 'orderGroup']), $rider);

                return '#'.$order->id;
            });

            $this->statusMessage = "Accepted order {$label}. Status is now On the way to delivery.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not accept this order.';
        }
    }

    public function deliverToConsumer(int $orderId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        $riderId = (int) Auth::id();

        try {
            $label = DB::transaction(function () use ($orderId, $riderId) {
                $order = Order::query()
                    ->whereKey($orderId)
                    ->lockForUpdate()
                    ->first();

                if (! $order || ! $order->isAssignedToRider($riderId)) {
                    throw new \RuntimeException('You can only deliver orders you have accepted.');
                }

                if (! $order->isOnTheWayToDelivery()) {
                    throw new \RuntimeException('This order is not on the way to delivery.');
                }

                $boxes = $order->middoBoxes()->lockForUpdate()->get();

                foreach ($boxes as $box) {
                    if ((int) $box->held_by_user_id !== $riderId) {
                        throw new \RuntimeException("{$box->qr_code_id} is not in your custody.");
                    }

                    $box->update([
                        'held_by_user_id' => $order->user_id,
                        'kitchen_id' => null,
                        'asset_status' => 'active',
                        'last_scanned_at' => now(),
                    ]);

                    MiddoBoxLog::create([
                        'order_id' => $order->id,
                        'middo_box_id' => $box->id,
                        'custody_status' => 'with_customer',
                        'log_action' => 'delivered_to_corporate',
                    ]);
                }

                $toStatus = $order->amountDue() === 0
                    ? OrderTransition::DELIVERED_AND_PAID
                    : OrderTransition::DELIVERED;

                OrderTransition::apply($order, $toStatus, [
                    'payment_status' => $toStatus === OrderTransition::DELIVERED_AND_PAID ? 'paid' : $order->payment_status,
                    'updated_by' => $riderId,
                ]);

                return '#'.$order->id;
            });

            $this->statusMessage = "Delivered order {$label}. Boxes are now with the customer.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not complete delivery.';
        }
    }

    protected function dateLabel(string $date): string
    {
        $today = now('Asia/Dhaka')->toDateString();
        $tomorrow = now('Asia/Dhaka')->copy()->addDay()->toDateString();

        if ($date === $today) {
            return 'Today';
        }

        if ($date === $tomorrow) {
            return 'Tomorrow';
        }

        return Carbon::parse($date, 'Asia/Dhaka')->format('l, F-j');
    }

    public function render()
    {
        $rider = Auth::user();
        $riderId = (int) $rider->id;

        $orders = Order::query()
            ->kitchenDispatched()
            ->tap(fn ($q) => DeliveryAreaScope::applyKitchenDispatchedVisibleToRider($q, $rider))
            ->with([
                'menuItem',
                'user',
                'deliveryRider',
                'orderGroup.kitchen',
                'middoBoxes',
            ])
            ->orderBy('dispatched_at')
            ->paginate(20);

        $nodes = collect($orders->items())
            ->map(function (Order $order) use ($riderId) {
                $kitchen = $order->orderGroup?->kitchen;
                $party = $order->partyPayload();

                return [
                    'id' => $order->id,
                    'menu_name' => $order->menuItem?->name ?? 'Order',
                    'customer_name' => $party['customer_name'],
                    'account_holder_name' => $party['account_holder_name'],
                    'receiver_name' => $party['receiver_name'],
                    'receiver_mobile' => $party['receiver_mobile'],
                    'has_separate_receiver' => $party['has_separate_receiver'],
                    'address' => $order->address,
                    'quantity' => $order->quantity,
                    'amount_due' => $party['amount_due'],
                    'amount_paid' => $party['amount_paid'],
                    'delivery_time' => $order->delivery_time,
                    'date_label' => $this->dateLabel($order->delivery_date->toDateString()),
                    'kitchen_name' => $kitchen?->name ?? 'Kitchen',
                    'kitchen_mobile' => $kitchen?->mobile,
                    'kitchen_address' => $kitchen?->address,
                    'box_codes' => $order->middoBoxes->pluck('qr_code_id')->all(),
                    'status_label' => str($order->order_status)->replace('_', ' ')->title()->toString(),
                    'awaiting_accept' => $order->isAwaitingRiderAccept(),
                    'can_mark_delivered' => $order->isAssignedToRider((int) $riderId)
                        && $order->isOnTheWayToDelivery(),
                    'accepted_by_other' => $order->delivery_rider_id !== null
                        && ! $order->isAssignedToRider((int) $riderId),
                    'rider_name' => $order->deliveryRider?->name,
                ];
            })
            ->all();

        return view('livewire.delivery.kitchen-dispatches', [
            'orders' => $orders,
            'nodes' => $nodes,
        ])->layout('delivery.layout.app', ['title' => 'Kitchen Dispatches']);
    }
}
