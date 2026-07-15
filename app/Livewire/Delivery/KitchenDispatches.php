<?php

namespace App\Livewire\Delivery;

use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Order;
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

        $riderId = Auth::id();

        try {
            $label = DB::transaction(function () use ($orderId, $riderId) {
                $order = Order::with(['middoBoxes', 'orderGroup.kitchen', 'menuItem', 'user'])
                    ->whereKey($orderId)
                    ->lockForUpdate()
                    ->first();

                if (! $order || ! $order->isAwaitingRiderAccept()) {
                    throw new \RuntimeException('This kitchen dispatch is no longer available to accept.');
                }

                $boxes = $order->middoBoxes()->lockForUpdate()->get();

                if ($boxes->count() !== (int) $order->quantity) {
                    throw new \RuntimeException('Reserved boxes for this order are incomplete.');
                }

                foreach ($boxes as $box) {
                    $kitchenId = $order->orderGroup?->kitchen_id;

                    if ($kitchenId && ! $box->isAtKitchen($kitchenId)) {
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

                $order->update([
                    'delivery_rider_id' => $riderId,
                    'updated_by' => $riderId,
                ]);

                return '#'.$order->id;
            });

            $this->statusMessage = "Accepted order {$label}. Head to the kitchen, then deliver to the consumer.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not accept this order.';
        }
    }

    public function deliverToConsumer(int $orderId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        $riderId = Auth::id();

        try {
            $label = DB::transaction(function () use ($orderId, $riderId) {
                $order = Order::with(['middoBoxes', 'user'])
                    ->whereKey($orderId)
                    ->lockForUpdate()
                    ->first();

                if (! $order || $order->delivery_rider_id !== $riderId) {
                    throw new \RuntimeException('You can only deliver orders you have accepted.');
                }

                if ($order->order_status === 'delivered') {
                    throw new \RuntimeException('This order was already delivered.');
                }

                if ($order->dispatched_at === null) {
                    throw new \RuntimeException('This order has not been kitchen-dispatched.');
                }

                $boxes = $order->middoBoxes()->lockForUpdate()->get();

                foreach ($boxes as $box) {
                    if ((int) $box->held_by_user_id !== (int) $riderId) {
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

                $order->update([
                    'order_status' => 'delivered',
                    'updated_by' => $riderId,
                ]);

                return '#'.$order->id;
            });

            $this->statusMessage = "Delivered order {$label} to the consumer.";
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
        $riderId = Auth::id();

        $orders = Order::query()
            ->kitchenDispatched()
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
                $kitchenName = $order->orderGroup?->kitchen?->name ?? 'Kitchen';

                return [
                    'id' => $order->id,
                    'menu_name' => $order->menuItem?->name ?? 'Order',
                    'customer_name' => trim(($order->user?->first_name ?? '').' '.($order->user?->last_name ?? '')) ?: 'N/A',
                    'address' => $order->address,
                    'quantity' => $order->quantity,
                    'delivery_time' => $order->delivery_time,
                    'date_label' => $this->dateLabel($order->delivery_date->toDateString()),
                    'kitchen_name' => $kitchenName,
                    'box_codes' => $order->middoBoxes->pluck('qr_code_id')->all(),
                    'awaiting_accept' => $order->isAwaitingRiderAccept(),
                    'accepted_by_me' => (int) $order->delivery_rider_id === (int) $riderId,
                    'accepted_by_other' => $order->delivery_rider_id !== null
                        && (int) $order->delivery_rider_id !== (int) $riderId,
                    'rider_name' => $order->deliveryRider?->name,
                ];
            })
            ->all();

        return view('livewire.delivery.kitchen-dispatches', [
            'orders' => $orders,
            'nodes' => $nodes,
        ])->layout('layouts.private.app', ['title' => 'Kitchen Dispatches']);
    }
}
