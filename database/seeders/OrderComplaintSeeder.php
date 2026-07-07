<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class OrderComplaintSeeder extends Seeder
{
    public function run(): void
    {
        $corporate = User::where('email', 'corporate@middo.com')->first();
        $admin = User::where('email', 'admin@middo.com')->first();

        if (! $corporate || ! $admin) {
            $this->command?->warn('Corporate or admin user not found. Run userSeeder first.');

            return;
        }

        OrderComplaint::query()->delete();

        $orders = Order::where('user_id', $corporate->id)
            ->orderBy('delivery_date')
            ->get();

        if ($orders->count() < 3) {
            $this->command?->warn('Not enough orders found. Run OrderSeeder first.');

            return;
        }

        $deliveredOrder = $orders->firstWhere('order_status', 'delivered');
        $cancelledOrder = $orders->firstWhere('order_status', 'cancelled');
        $processingOrder = $orders->firstWhere('order_status', 'processing');

        if ($deliveredOrder) {
            $root = OrderComplaint::create([
                'order_id' => $deliveredOrder->id,
                'parent_id' => null,
                'is_reply' => false,
                'category' => 'food_quality',
                'message' => 'Two meals arrived cold and the rice portion was smaller than usual. Please look into this.',
                'attachment' => null,
                'created_by' => $corporate->id,
                'updated_by' => $corporate->id,
            ]);
            $root->update([
                'created_at' => Carbon::now('Asia/Dhaka')->subDays(3)->setTime(14, 20),
                'updated_at' => Carbon::now('Asia/Dhaka')->subDays(3)->setTime(14, 20),
            ]);

            $replyOne = OrderComplaint::create([
                'order_id' => $deliveredOrder->id,
                'parent_id' => $root->id,
                'is_reply' => true,
                'category' => null,
                'message' => 'Sorry about that. We have flagged this with the kitchen and added a ৳200 credit to your account.',
                'attachment' => null,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
            $replyOne->update([
                'created_at' => Carbon::now('Asia/Dhaka')->subDays(2)->setTime(10, 15),
                'updated_at' => Carbon::now('Asia/Dhaka')->subDays(2)->setTime(10, 15),
            ]);

            $replyTwo = OrderComplaint::create([
                'order_id' => $deliveredOrder->id,
                'parent_id' => $root->id,
                'is_reply' => true,
                'category' => null,
                'message' => 'Please confirm if the credit reflects on your dashboard. We will monitor the next delivery closely.',
                'attachment' => null,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
            $replyTwo->update([
                'created_at' => Carbon::now('Asia/Dhaka')->subDays(1)->setTime(16, 40),
                'updated_at' => Carbon::now('Asia/Dhaka')->subDays(1)->setTime(16, 40),
            ]);
        }

        if ($cancelledOrder) {
            $complaint = OrderComplaint::create([
                'order_id' => $cancelledOrder->id,
                'parent_id' => null,
                'is_reply' => false,
                'category' => 'payment',
                'message' => 'Refund for this cancelled order has not appeared in my balance yet. Order was cancelled yesterday.',
                'attachment' => null,
                'created_by' => $corporate->id,
                'updated_by' => $corporate->id,
            ]);
            $complaint->update([
                'created_at' => Carbon::now('Asia/Dhaka')->subHours(8),
                'updated_at' => Carbon::now('Asia/Dhaka')->subHours(8),
            ]);
        }

        if ($processingOrder) {
            $root = OrderComplaint::create([
                'order_id' => $processingOrder->id,
                'parent_id' => null,
                'is_reply' => false,
                'category' => 'delivery',
                'message' => 'Can you confirm the delivery window for today? Our team lunch starts at 12:15 PM sharp.',
                'attachment' => null,
                'created_by' => $corporate->id,
                'updated_by' => $corporate->id,
            ]);
            $root->update([
                'created_at' => Carbon::now('Asia/Dhaka')->subHours(3),
                'updated_at' => Carbon::now('Asia/Dhaka')->subHours(3),
            ]);

            $reply = OrderComplaint::create([
                'order_id' => $processingOrder->id,
                'parent_id' => $root->id,
                'is_reply' => true,
                'category' => null,
                'message' => 'Your order is scheduled for the 12:00 PM window. The rider is expected between 11:30 AM and 11:45 AM.',
                'attachment' => null,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
            $reply->update([
                'created_at' => Carbon::now('Asia/Dhaka')->subHours(2),
                'updated_at' => Carbon::now('Asia/Dhaka')->subHours(2),
            ]);
        }

        $this->command?->info('Seeded order complaints with sample threads and admin replies.');
    }
}
