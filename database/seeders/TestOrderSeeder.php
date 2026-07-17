<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TestOrderSeeder extends Seeder
{
    public function run(): void
    {
        $corporate = User::whereHas('role', fn ($query) => $query->where('name', 'corporate'))->first();

        if (! $corporate) {
            $this->command?->warn('Corporate user not found. Run userSeeder first.');

            return;
        }

        $menuItems = MenuItem::all();

        if ($menuItems->isEmpty()) {
            $this->command?->warn('No menu items found. Run MenuItemSeeder first.');

            return;
        }

        $today = Carbon::now('Asia/Dhaka')->startOfDay();
        $addresses = [
            'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212',
            'Plot 12, Block C, Banani, Dhaka 1213',
            '88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212',
            'Navana Tower, Gulshan-1, Dhaka 1212',
        ];
        $deliveryTimes = ['11:30 AM', '12:00 PM', '12:30 PM'];
        $futureStatuses = ['pending', 'pending', 'pending', 'processing'];
        $created = 0;

        // 200 orders randomly across the next 4 days (today + 3 ahead)
        for ($i = 0; $i < 200; $i++) {
            $menuItem = $menuItems->random();
            $qty = random_int(1, 5);
            $daysAhead = random_int(0, 3);

            $total = (int) ($menuItem->price * $qty);
            $paymentStatus = random_int(0, 1) ? 'paid' : 'pending';
            $paid = $paymentStatus === 'paid' ? $total : 0;

            Order::create([
                'user_id' => $corporate->id,
                'menu_item_id' => $menuItem->id,
                'quantity' => $qty,
                'delivery_date' => $today->copy()->addDays($daysAhead)->toDateString(),
                'delivery_time' => $deliveryTimes[array_rand($deliveryTimes)],
                'total_amount' => $total,
                'amount_paid' => $paid,
                'prepaid_amount' => $paid,
                'cash_collected' => 0,
                'address' => $addresses[array_rand($addresses)],
                'receiver_name' => trim($corporate->first_name.' '.$corporate->last_name),
                'receiver_mobile' => $corporate->mobile,
                'order_status' => $futureStatuses[array_rand($futureStatuses)],
                'payment_status' => $paymentStatus,
                'created_by' => $corporate->id,
                'updated_by' => $corporate->id,
            ]);

            $created++;
        }

        // 50 orders randomly across the past 7 days
        for ($i = 0; $i < 50; $i++) {
            $menuItem = $menuItems->random();
            $qty = random_int(1, 5);
            $daysAgo = random_int(1, 7);

            $total = (int) ($menuItem->price * $qty);
            $orderStatus = random_int(0, 9) < 9 ? 'delivered' : 'cancelled';
            $paymentStatus = random_int(0, 9) < 9 ? 'paid' : 'returned';
            $paid = $paymentStatus === 'paid' ? $total : 0;

            Order::create([
                'user_id' => $corporate->id,
                'menu_item_id' => $menuItem->id,
                'quantity' => $qty,
                'delivery_date' => $today->copy()->subDays($daysAgo)->toDateString(),
                'delivery_time' => $deliveryTimes[array_rand($deliveryTimes)],
                'total_amount' => $total,
                'amount_paid' => $paid,
                'prepaid_amount' => $paid,
                'cash_collected' => $orderStatus === 'delivered' && $paymentStatus === 'paid' ? $total : 0,
                'address' => $addresses[array_rand($addresses)],
                'receiver_name' => trim($corporate->first_name.' '.$corporate->last_name),
                'receiver_mobile' => $corporate->mobile,
                'order_status' => $orderStatus,
                'payment_status' => $paymentStatus,
                'created_by' => $corporate->id,
                'updated_by' => $corporate->id,
            ]);

            $created++;
        }

        $this->command?->info("Seeded {$created} test orders (200 future · 50 past).");
    }
}
