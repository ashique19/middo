<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderMiddoBox;
use App\Models\User;
use App\Support\KitchenCapacity;
use App\Support\MiddoSettings;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Deterministic pending order group + Middo box for Playwright order-lifecycle.spec.ts.
 * Safe to re-run: replaces prior E2E-LIFECYCLE fixtures.
 */
class E2eOrderLifecycleSeeder extends Seeder
{
    public const GROUP_NAME = 'E2E-LIFECYCLE';

    public const BOX_QR = 'MB-E2E-01';

    public function run(): void
    {
        MiddoSettings::updateMealAndKitchenDefaults([
            'accept_window_minutes' => 180,
        ]);

        $kitchen = User::query()->where('mobile', '01310123453')->firstOrFail();
        $corporate = User::query()->where('mobile', '01310123452')->firstOrFail();
        $menu = MenuItem::query()->orderBy('id')->firstOrFail();

        // Ensure Gulshan kitchen can accept the E2E group even if other seeders filled slots.
        $openCount = KitchenCapacity::openGroupCount((int) $kitchen->id);
        $kitchen->update([
            'allowed_open_groups' => $openCount + 2,
        ]);

        OrderGroup::query()->where('name', self::GROUP_NAME)->each(function (OrderGroup $group) {
            $orderIds = $group->orders()->pluck('orders.id');
            $group->orders()->detach();
            Order::query()->whereIn('id', $orderIds)->delete();
            $group->delete();
        });

        MiddoBox::query()->where('qr_code_id', self::BOX_QR)->each(function (MiddoBox $box) {
            MiddoBoxLog::query()->where('middo_box_id', $box->id)->delete();
            OrderMiddoBox::query()->where('middo_box_id', $box->id)->delete();
            $box->delete();
        });

        $deliveryAt = Carbon::now('Asia/Dhaka')->copy()->addMinutes(90);

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => $deliveryAt->toDateString(),
            'delivery_time' => $deliveryAt->format('g:i A'),
            'total_amount' => (int) $menu->price,
            'address' => $corporate->address ?: 'House 12, Gulshan',
            'area_id' => $corporate->area_id,
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'balance',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        $group = OrderGroup::create([
            'name' => self::GROUP_NAME,
            'menu_id' => $menu->id,
            'delivery_date' => $deliveryAt->toDateString(),
            'area_id' => $corporate->area_id,
            'kitchen_id' => null,
        ]);
        $group->orders()->attach($order->id);

        MiddoBox::create([
            'qr_code_id' => self::BOX_QR,
            'box_model_type' => 'standard_insulated',
            'held_by_user_id' => $kitchen->id,
            'kitchen_id' => $kitchen->id,
            'asset_status' => 'active',
            'total_uses_count' => 0,
        ]);

        $this->command?->info('E2E lifecycle fixture ready: '.self::GROUP_NAME.' / '.self::BOX_QR);
    }
}
