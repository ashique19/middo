<?php

namespace App\Observers;

use App\Jobs\SendOrderStatusPush;
use App\Models\Order;
use App\Models\OrderLog;
use Illuminate\Support\Facades\Auth;

class OrderObserver
{
    public function created(Order $order): void
    {
        $this->writeLog($order, 'created', [
            'snapshot' => $this->snapshot($order),
        ]);

        try {
            \App\Support\OrderMoneyFlow::onOrderCreated($order);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Order money flow create failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(Order $order): void
    {
        $changes = collect($order->getChanges())
            ->except(['updated_at'])
            ->all();

        if ($changes === []) {
            return;
        }

        $diff = collect($changes)
            ->mapWithKeys(fn ($value, $key) => [
                $key => [
                    'from' => $order->getOriginal($key),
                    'to' => $value,
                ],
            ])
            ->all();

        $event = $this->resolveEvent($diff);

        $this->writeLog($order, $event, ['changes' => $diff]);

        try {
            \App\Support\OrderMoneyFlow::onOrderUpdated($order);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Order money flow update failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        if (array_key_exists('order_status', $changes)) {
            try {
                // Run inline so pushes work on hosts without a queue worker / SSH.
                SendOrderStatusPush::dispatchSync(
                    $order->id,
                    (string) $order->order_status,
                );
            } catch (\Throwable $e) {
                // Push must never roll back / break the order save itself.
                \Illuminate\Support\Facades\Log::warning('Failed to dispatch order status push', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function deleting(Order $order): void
    {
        $this->writeLog($order, 'deleted', [
            'snapshot' => $this->snapshot($order),
        ]);
    }

    protected function resolveEvent(array $diff): string
    {
        $keys = array_keys($diff);

        if ($keys === ['order_status']) {
            return 'order_status_changed';
        }

        if ($keys === ['payment_status']) {
            return 'payment_status_changed';
        }

        if ($keys === ['quantity', 'total_amount'] || $keys === ['total_amount', 'quantity']) {
            return 'quantity_changed';
        }

        return 'updated';
    }

    protected function snapshot(Order $order): array
    {
        return $order->only([
            'user_id',
            'menu_item_id',
            'quantity',
            'delivery_date',
            'delivery_time',
            'total_amount',
            'address',
            'order_status',
            'payment_status',
            'created_by',
            'updated_by',
        ]);
    }

    protected function writeLog(Order $order, string $event, ?array $metadata = null): void
    {
        OrderLog::create([
            'order_id' => $order->id,
            'event' => $event,
            'metadata' => $metadata,
            'performed_by' => Auth::id() ?? $order->updated_by ?? $order->created_by,
        ]);
    }
}
