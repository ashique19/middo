<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class OrderLogSeeder extends Seeder
{
    public function run(): void
    {
        $corporate = User::where('email', 'corporate@middo.com')->first();

        if (! $corporate) {
            $this->command?->warn('Corporate user not found. Run userSeeder first.');

            return;
        }

        OrderLog::query()->delete();

        $orders = Order::where('user_id', $corporate->id)->get();

        if ($orders->isEmpty()) {
            $this->command?->warn('No orders found. Run OrderSeeder first.');

            return;
        }

        foreach ($orders as $order) {
            $this->seedLogsForOrder($order, $corporate->id);
        }

        $this->command?->info("Seeded {$orders->count()} order lifecycles into order_logs.");
    }

    protected function seedLogsForOrder(Order $order, int $performedBy): void
    {
        $deliveryDate = Carbon::parse($order->delivery_date, 'Asia/Dhaka')->startOfDay();
        $createdAt = $deliveryDate->copy()->subDays(min(7, max(2, $deliveryDate->diffInDays(now('Asia/Dhaka')) + 1)))->setTime(10, 15);

        $this->writeLog(
            order: $order,
            event: 'created',
            metadata: ['snapshot' => $this->snapshot($order, 'pending', 'pending')],
            loggedAt: $createdAt,
            performedBy: $performedBy,
        );

        if ($order->payment_status === 'paid') {
            $this->writeLog(
                order: $order,
                event: 'payment_status_changed',
                metadata: ['changes' => ['payment_status' => ['from' => 'pending', 'to' => 'paid']]],
                loggedAt: $createdAt->copy()->addMinutes(20),
                performedBy: $performedBy,
            );
        }

        if (in_array($order->order_status, ['processing', 'delivered'], true)) {
            $this->writeLog(
                order: $order,
                event: 'order_status_changed',
                metadata: ['changes' => ['order_status' => ['from' => 'pending', 'to' => 'processing']]],
                loggedAt: $deliveryDate->copy()->subDay()->setTime(17, 30),
                performedBy: $performedBy,
            );
        }

        if ($order->order_status === 'delivered') {
            $this->writeLog(
                order: $order,
                event: 'order_status_changed',
                metadata: ['changes' => ['order_status' => ['from' => 'processing', 'to' => 'delivered']]],
                loggedAt: $this->deliveryTimestamp($deliveryDate, $order->delivery_time),
                performedBy: $performedBy,
            );
        }

        if ($order->order_status === 'cancelled') {
            $cancelledAt = $deliveryDate->copy()->subHours(6);

            $this->writeLog(
                order: $order,
                event: 'order_status_changed',
                metadata: ['changes' => ['order_status' => ['from' => 'pending', 'to' => 'cancelled']]],
                loggedAt: $cancelledAt,
                performedBy: $performedBy,
            );

            if ($order->payment_status === 'returned') {
                $this->writeLog(
                    order: $order,
                    event: 'payment_status_changed',
                    metadata: ['changes' => ['payment_status' => ['from' => 'paid', 'to' => 'returned']]],
                    loggedAt: $cancelledAt->copy()->addMinutes(45),
                    performedBy: $performedBy,
                );
            }
        }
    }

    protected function deliveryTimestamp(Carbon $deliveryDate, string $deliveryTime): Carbon
    {
        return $deliveryDate->copy()->setTimeFromTimeString(
            Carbon::parse($deliveryTime, 'Asia/Dhaka')->format('H:i:s')
        );
    }

    protected function snapshot(Order $order, string $orderStatus, string $paymentStatus): array
    {
        return [
            'user_id' => $order->user_id,
            'menu_item_id' => $order->menu_item_id,
            'quantity' => $order->quantity,
            'delivery_date' => $order->delivery_date->toDateString(),
            'delivery_time' => $order->delivery_time,
            'total_amount' => $order->total_amount,
            'address' => $order->address,
            'order_status' => $orderStatus,
            'payment_status' => $paymentStatus,
            'created_by' => $order->created_by,
            'updated_by' => $order->updated_by,
        ];
    }

    protected function writeLog(
        Order $order,
        string $event,
        array $metadata,
        Carbon $loggedAt,
        int $performedBy,
    ): void {
        $log = new OrderLog([
            'order_id' => $order->id,
            'event' => $event,
            'metadata' => $metadata,
            'performed_by' => $performedBy,
        ]);

        $log->created_at = $loggedAt;
        $log->updated_at = $loggedAt;
        $log->save();
    }
}
