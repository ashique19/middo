<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $corporate = User::where('email', 'corporate@middo.com')->first();

        if (! $corporate) {
            $this->command?->warn('Corporate user not found. Run userSeeder first.');

            return;
        }

        if (Order::where('user_id', $corporate->id)->exists()) {
            Order::where('user_id', $corporate->id)->delete();
            $this->command?->info('Cleared existing orders for corporate user.');
        }

        $menuItems = MenuItem::all();

        if ($menuItems->isEmpty()) {
            $this->command?->warn('No menu items found. Run MenuItemSeeder first.');

            return;
        }

        $address = 'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212';
        $deliveryTimes = ['11:30 AM', '12:00 PM', '12:30 PM'];
        $today = Carbon::now('Asia/Dhaka')->startOfDay();
        $maxQty = max(1, (int) config('middo.max_order_qty_allowed', 5));

        // Past delivered lunches (order history)
        $pastOrders = [
            ['days_ago' => 28, 'qty' => 5, 'status' => 'delivered', 'payment' => 'paid'],
            ['days_ago' => 25, 'qty' => 4, 'status' => 'delivered', 'payment' => 'paid'],
            ['days_ago' => 21, 'qty' => 3, 'status' => 'delivered', 'payment' => 'paid'],
            ['days_ago' => 18, 'qty' => 5, 'status' => 'delivered', 'payment' => 'paid'],
            ['days_ago' => 14, 'qty' => 2, 'status' => 'delivered', 'payment' => 'paid'],
            ['days_ago' => 10, 'qty' => 4, 'status' => 'delivered', 'payment' => 'paid'],
            ['days_ago' => 7, 'qty' => 5, 'status' => 'delivered', 'payment' => 'paid'],
            ['days_ago' => 4, 'qty' => 3, 'status' => 'delivered', 'payment' => 'paid'],
            ['days_ago' => 2, 'qty' => 4, 'status' => 'delivered', 'payment' => 'paid'],
            ['days_ago' => 1, 'qty' => 2, 'status' => 'cancelled', 'payment' => 'returned'],
        ];

        foreach ($pastOrders as $index => $config) {
            $menuItem = $menuItems[$index % $menuItems->count()];
            $deliveryDate = $today->copy()->subDays($config['days_ago']);
            $qty = min($config['qty'], $maxQty);

            Order::create([
                'user_id' => $corporate->id,
                'menu_item_id' => $menuItem->id,
                'quantity' => $qty,
                'delivery_date' => $deliveryDate->toDateString(),
                'delivery_time' => $deliveryTimes[$index % count($deliveryTimes)],
                'total_amount' => $menuItem->price * $qty,
                'address' => $address,
                'order_status' => $config['status'],
                'payment_status' => $config['payment'],
                'created_by' => $corporate->id,
                'updated_by' => $corporate->id,
            ]);
        }

        // Upcoming scheduled lunches
        $upcomingOrders = [
            ['days_ahead' => 0, 'qty' => 3, 'status' => 'processing', 'payment' => 'paid'],
            ['days_ahead' => 1, 'qty' => 5, 'status' => 'pending', 'payment' => 'pending'],
            ['days_ahead' => 2, 'qty' => 4, 'status' => 'pending', 'payment' => 'pending'],
            ['days_ahead' => 4, 'qty' => 2, 'status' => 'pending', 'payment' => 'pending'],
            ['days_ahead' => 5, 'qty' => 5, 'status' => 'pending', 'payment' => 'pending'],
            ['days_ahead' => 7, 'qty' => 3, 'status' => 'pending', 'payment' => 'pending'],
            ['days_ahead' => 10, 'qty' => 4, 'status' => 'pending', 'payment' => 'pending'],
            ['days_ahead' => 12, 'qty' => 1, 'status' => 'pending', 'payment' => 'pending'],
        ];

        foreach ($upcomingOrders as $index => $config) {
            $menuItem = $menuItems[($index + 2) % $menuItems->count()];
            $deliveryDate = $today->copy()->addDays($config['days_ahead']);
            $qty = min($config['qty'], $maxQty);

            Order::create([
                'user_id' => $corporate->id,
                'menu_item_id' => $menuItem->id,
                'quantity' => $qty,
                'delivery_date' => $deliveryDate->toDateString(),
                'delivery_time' => $deliveryTimes[$index % count($deliveryTimes)],
                'total_amount' => $menuItem->price * $qty,
                'address' => $address,
                'order_status' => $config['status'],
                'payment_status' => $config['payment'],
                'created_by' => $corporate->id,
                'updated_by' => $corporate->id,
            ]);
        }

        $this->command?->info('Seeded '.count($pastOrders).' past and '.count($upcomingOrders).' upcoming orders for corporate user.');
    }
}
