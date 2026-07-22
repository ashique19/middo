<?php

namespace App\Observers;

use App\Jobs\SendOrderStatusPush;
use App\Models\Order;
use App\Support\OrderAudit;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function created(Order $order): void
    {
        OrderAudit::record($order, 'created', [
            'snapshot' => OrderAudit::snapshot($order),
        ]);

        try {
            \App\Support\OrderMoneyFlow::onOrderCreated($order);
        } catch (\Throwable $e) {
            Log::warning('Order money flow create failed', [
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

        $event = OrderAudit::resolveEvent($order, $diff);

        OrderAudit::record($order, $event, [
            'changes' => $diff,
            'source' => $order->package_subscription_id ? 'package' : 'menu',
        ]);

        try {
            \App\Support\OrderMoneyFlow::onOrderUpdated($order);
        } catch (\Throwable $e) {
            Log::warning('Order money flow update failed', [
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
                Log::warning('Failed to dispatch order status push', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function deleting(Order $order): void
    {
        OrderAudit::record($order, 'deleted', [
            'snapshot' => OrderAudit::snapshot($order),
        ]);
    }
}
