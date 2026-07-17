<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\Order;
use App\Support\FcmClient;
use App\Support\OrderStatusPushCopy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendOrderStatusPush implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $orderId,
        public string $status,
    ) {}

    public function handle(): void
    {
        $order = Order::query()
            ->with(['menuItem:id,name', 'user:id'])
            ->find($this->orderId);

        if (! $order || ! $order->user_id) {
            return;
        }

        $copy = OrderStatusPushCopy::forStatus(
            $this->status,
            $order->id,
            $order->menuItem?->name,
        );

        if ($copy === null) {
            return;
        }

        $tokens = DeviceToken::query()
            ->where('user_id', $order->user_id)
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            Log::debug('No device tokens for order status push', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'status' => $this->status,
            ]);

            return;
        }

        $result = FcmClient::sendToTokens(
            $tokens,
            $copy['title'],
            $copy['body'],
            [
                'type' => 'order_status',
                'order_id' => (string) $order->id,
                'order_status' => $this->status,
            ],
        );

        Log::info('Order status push dispatched', [
            'order_id' => $order->id,
            'status' => $this->status,
            'result' => $result,
        ]);
    }
}
