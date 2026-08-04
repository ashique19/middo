<?php

namespace App\Livewire\Delivery;

use App\Livewire\Concerns\WithOrdersListView;
use App\Models\MiddoBoxLog;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class DeliveredOrders extends Component
{
    use WithOrdersListView;
    use WithPagination;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    #[On('order-payment-recorded')]
    public function onPaymentRecorded(string $message = 'Payment recorded.'): void
    {
        $this->statusMessage = $message;
        $this->errorMessage = null;
        $this->resetPage();
    }

    public function receiveBoxes(int $orderId): void
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

                if (! $order || (int) $order->delivery_rider_id !== (int) $riderId) {
                    throw new \RuntimeException('Order not found for your deliveries.');
                }

                if (! $order->isDelivered()) {
                    throw new \RuntimeException('Boxes can only be received after delivery.');
                }

                $alreadyReceived = MiddoBoxLog::query()
                    ->where('order_id', $order->id)
                    ->where('log_action', 'picked_from_corporate_by_delivery')
                    ->exists();

                if ($alreadyReceived) {
                    throw new \RuntimeException('Boxes for this order were already received.');
                }

                $boxes = $order->middoBoxes()->lockForUpdate()->get();

                if ($boxes->isEmpty()) {
                    throw new \RuntimeException('No Middo boxes linked to this order.');
                }

                foreach ($boxes as $box) {
                    if ((int) $box->held_by_user_id !== (int) $order->user_id) {
                        throw new \RuntimeException("{$box->qr_code_id} is not currently with the customer.");
                    }

                    $box->update([
                        'held_by_user_id' => $riderId,
                        'kitchen_id' => null,
                        'asset_status' => 'active',
                        'last_scanned_at' => now(),
                    ]);

                    MiddoBoxLog::create([
                        'order_id' => $order->id,
                        'middo_box_id' => $box->id,
                        'custody_status' => 'collected_by_rider',
                        'log_action' => 'picked_from_corporate_by_delivery',
                    ]);
                }

                return '#'.$order->id;
            });

            $this->statusMessage = "Received boxes for order {$label}.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not receive boxes.';
        }
    }

    protected function dateLabel(string $date): string
    {
        $today = now('Asia/Dhaka')->toDateString();
        $yesterday = now('Asia/Dhaka')->copy()->subDay()->toDateString();

        if ($date === $today) {
            return 'Today';
        }

        if ($date === $yesterday) {
            return 'Yesterday';
        }

        return Carbon::parse($date, 'Asia/Dhaka')->format('l, F-j');
    }

    public function render()
    {
        $riderId = Auth::id();

        $orders = Order::query()
            ->deliveredForRider($riderId)
            ->with(['menuItem', 'user', 'middoBoxes'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        $receivedOrderIds = MiddoBoxLog::query()
            ->whereIn('order_id', $orders->getCollection()->pluck('id')->filter()->all() ?: [0])
            ->where('log_action', 'picked_from_corporate_by_delivery')
            ->pluck('order_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $nodes = collect($orders->items())
            ->map(function (Order $order) use ($receivedOrderIds) {
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
                    'total_amount' => $order->total_amount,
                    'amount_paid' => $party['amount_paid'],
                    'amount_due' => $party['amount_due'],
                    'delivery_time' => $order->delivery_time,
                    'delivery_date' => $order->delivery_date->toDateString(),
                    'date_label' => $this->dateLabel($order->delivery_date->toDateString()),
                    'status_label' => str($order->order_status)->replace('_', ' ')->title()->toString(),
                    'order_status' => $order->order_status,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $party['payment_method'],
                    'payment_method_label' => $party['payment_method_label'],
                    'is_paid' => $order->isPaid(),
                    'boxes_received' => in_array($order->id, $receivedOrderIds, true),
                    'box_codes' => $order->middoBoxes->pluck('qr_code_id')->all(),
                    'menu_item' => ['name' => $order->menuItem?->name ?? 'Order'],
                ];
            })
            ->all();

        return view('livewire.delivery.delivered-orders', [
            'orders' => $orders,
            'nodes' => $nodes,
        ])->layout('delivery.layout.app', ['title' => 'Delivered Orders']);
    }
}
