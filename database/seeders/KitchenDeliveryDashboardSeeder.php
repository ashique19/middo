<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderMiddoBox;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic kitchen + delivery dashboard fixtures for local/demo.
 *
 * Login as:
 * - kitchen@middo.com (Gulshan) — Active Orders, boxes, Middo groups, menu/recipes
 * - delivery@middo.com (Rahim) — dispatches, pending runs, delivered / payment
 */
class KitchenDeliveryDashboardSeeder extends Seeder
{
    private int $groupSeq = 1;

    private int $boxSeq = 1;

    public function run(): void
    {
        $gulshan = User::where('email', 'kitchen@middo.com')->first()
            ?? User::where('mobile', '01310123453')->first();
        $banani = User::where('email', 'kitchen2@middo.com')->first()
            ?? User::where('mobile', '01310123456')->first();
        $rahim = User::where('email', 'delivery@middo.com')->first()
            ?? User::where('mobile', '01310123454')->first();
        $karim = User::where('email', 'delivery2@middo.com')->first()
            ?? User::where('mobile', '01310123460')->first();
        $customer = User::where('email', 'corporate@middo.com')->first()
            ?? User::where('mobile', '01310123452')->first();
        $ops = User::where('email', 'operations@middo.com')->first()
            ?? User::where('mobile', '01310123455')->first();

        $menus = MenuItem::orderBy('display_order')->get();

        if (! $gulshan || ! $rahim || ! $customer || $menus->isEmpty()) {
            $this->command?->warn('KitchenDeliveryDashboardSeeder skipped: missing kitchen, rider, corporate, or menus.');

            return;
        }

        $menuA = $menus[0];
        $menuB = $menus[1] ?? $menus[0];
        $menuC = $menus[2] ?? $menus[0];

        $today = now('Asia/Dhaka')->toDateString();
        $tomorrow = now('Asia/Dhaka')->copy()->addDay()->toDateString();
        $plusTwo = now('Asia/Dhaka')->copy()->addDays(2)->toDateString();
        $weekAgo = now('Asia/Dhaka')->copy()->subDays(7)->toDateString();
        $twoWeeksAgo = now('Asia/Dhaka')->copy()->subDays(14)->toDateString();
        $monthAgo = now('Asia/Dhaka')->copy()->subDays(25)->toDateString();

        DB::transaction(function () use (
            $gulshan, $banani, $rahim, $karim, $customer, $ops,
            $menuA, $menuB, $menuC,
            $today, $tomorrow, $plusTwo, $weekAgo, $twoWeeksAgo, $monthAgo
        ) {
            // Free inventory at Gulshan (dispatch-ready)
            for ($i = 0; $i < 12; $i++) {
                $this->makeBoxAtKitchen($gulshan->id);
            }

            // Incoming boxes for Gulshan (rider → kitchen)
            for ($i = 0; $i < 4; $i++) {
                $box = $this->makeBox([
                    'kitchen_id' => $gulshan->id,
                    'held_by_user_id' => $rahim->id,
                    'asset_status' => 'active',
                ]);
                MiddoBoxLog::create([
                    'middo_box_id' => $box->id,
                    'custody_status' => 'in_transit',
                    'log_action' => 'dispatched_to_kitchen',
                ]);
            }

            if ($banani && $karim) {
                for ($i = 0; $i < 2; $i++) {
                    $this->makeBoxAtKitchen($banani->id);
                }
                for ($i = 0; $i < 2; $i++) {
                    $box = $this->makeBox([
                        'kitchen_id' => $banani->id,
                        'held_by_user_id' => $karim->id,
                        'asset_status' => 'active',
                    ]);
                    MiddoBoxLog::create([
                        'middo_box_id' => $box->id,
                        'custody_status' => 'in_transit',
                        'log_action' => 'dispatched_to_kitchen',
                    ]);
                }
            }

            // Unassigned Middo order groups (accept pool)
            foreach ([
                [$today, $menuA, 2],
                [$today, $menuB, 1],
                [$tomorrow, $menuA, 2],
                [$tomorrow, $menuC, 3],
                [$plusTwo, $menuB, 1],
                [$plusTwo, $menuA, 2],
            ] as [$date, $menu, $qty]) {
                $this->createGroupedOrder(
                    customer: $customer,
                    menu: $menu,
                    deliveryDate: $date,
                    quantity: $qty,
                    kitchenId: null,
                    status: 'pending',
                    createdBy: $ops?->id,
                );
            }

            // Gulshan active assigned (not yet dispatched)
            foreach ([
                [$today, $menuA, 2],
                [$today, $menuB, 1],
                [$tomorrow, $menuA, 2],
                [$tomorrow, $menuC, 2],
            ] as [$date, $menu, $qty]) {
                $this->createGroupedOrder(
                    customer: $customer,
                    menu: $menu,
                    deliveryDate: $date,
                    quantity: $qty,
                    kitchenId: $gulshan->id,
                    status: 'pending',
                    createdBy: $ops?->id,
                    updatedBy: $gulshan->id,
                );
            }

            if ($banani) {
                $this->createGroupedOrder(
                    customer: $customer,
                    menu: $menuB,
                    deliveryDate: $today,
                    quantity: 2,
                    kitchenId: $banani->id,
                    status: 'pending',
                    createdBy: $ops?->id,
                    updatedBy: $banani->id,
                );
            }

            // Month history for Gulshan tiles
            foreach ([
                [$weekAgo, $menuA, 'delivered'],
                [$twoWeeksAgo, $menuB, 'delivered'],
                [$monthAgo, $menuC, 'delivered_and_paid'],
            ] as [$date, $menu, $status]) {
                $this->createGroupedOrder(
                    customer: $customer,
                    menu: $menu,
                    deliveryDate: $date,
                    quantity: 2,
                    kitchenId: $gulshan->id,
                    status: $status,
                    paymentStatus: $status === 'delivered_and_paid' ? 'paid' : 'pending',
                    createdBy: $ops?->id,
                    updatedBy: $gulshan->id,
                );
            }

            // Kitchen-dispatched, awaiting rider accept (boxes still at kitchen)
            for ($i = 0; $i < 3; $i++) {
                $order = $this->createGroupedOrder(
                    customer: $customer,
                    menu: $menuA,
                    deliveryDate: $today,
                    quantity: 2,
                    kitchenId: $gulshan->id,
                    status: 'processing',
                    paymentStatus: 'pending',
                    createdBy: $ops?->id,
                    updatedBy: $gulshan->id,
                    extra: ['dispatched_at' => now()->subMinutes(30 - ($i * 5))],
                );

                $boxes = collect(range(1, 2))->map(fn () => $this->makeBoxAtKitchen($gulshan->id));
                foreach ($boxes as $box) {
                    OrderMiddoBox::create([
                        'order_id' => $order->id,
                        'middo_box_id' => $box->id,
                    ]);
                    $box->update(['last_scanned_at' => now()]);
                }
            }

            // On the way to delivery (Rahim)
            for ($i = 0; $i < 2; $i++) {
                $order = $this->createGroupedOrder(
                    customer: $customer,
                    menu: $menuB,
                    deliveryDate: $today,
                    quantity: 2,
                    kitchenId: $gulshan->id,
                    status: 'on_the_way_to_delivery',
                    paymentStatus: 'pending',
                    createdBy: $ops?->id,
                    updatedBy: $rahim->id,
                    extra: [
                        'dispatched_at' => now()->subHours(2),
                        'delivery_rider_id' => $rahim->id,
                    ],
                );

                foreach (range(1, 2) as $_) {
                    $box = $this->makeBox([
                        'held_by_user_id' => $rahim->id,
                        'kitchen_id' => null,
                        'asset_status' => 'active',
                        'total_uses_count' => 1,
                    ]);
                    OrderMiddoBox::create([
                        'order_id' => $order->id,
                        'middo_box_id' => $box->id,
                    ]);
                    MiddoBoxLog::create([
                        'order_id' => $order->id,
                        'middo_box_id' => $box->id,
                        'custody_status' => 'in_transit',
                        'log_action' => 'picked_by_delivery_from_kitchen',
                    ]);
                }
            }

            // Delivered unpaid (payment modal ready)
            for ($i = 0; $i < 2; $i++) {
                $order = $this->createGroupedOrder(
                    customer: $customer,
                    menu: $menuA,
                    deliveryDate: $weekAgo,
                    quantity: 2,
                    kitchenId: $gulshan->id,
                    status: 'delivered',
                    paymentStatus: 'pending',
                    createdBy: $ops?->id,
                    updatedBy: $rahim->id,
                    extra: [
                        'dispatched_at' => now()->subDays(7)->addHours(1),
                        'delivery_rider_id' => $rahim->id,
                    ],
                );

                foreach (range(1, 2) as $_) {
                    $box = $this->makeBox([
                        'held_by_user_id' => $customer->id,
                        'kitchen_id' => null,
                        'asset_status' => 'active',
                        'total_uses_count' => 1,
                    ]);
                    OrderMiddoBox::create([
                        'order_id' => $order->id,
                        'middo_box_id' => $box->id,
                    ]);
                    MiddoBoxLog::create([
                        'order_id' => $order->id,
                        'middo_box_id' => $box->id,
                        'custody_status' => 'with_customer',
                        'log_action' => 'delivered_to_corporate',
                    ]);
                }
            }

            // Delivered + paid
            $paidOrder = $this->createGroupedOrder(
                customer: $customer,
                menu: $menuC,
                deliveryDate: $twoWeeksAgo,
                quantity: 2,
                kitchenId: $gulshan->id,
                status: 'delivered_and_paid',
                paymentStatus: 'paid',
                createdBy: $ops?->id,
                updatedBy: $rahim->id,
                extra: [
                    'dispatched_at' => now()->subDays(14)->addHours(1),
                    'delivery_rider_id' => $rahim->id,
                ],
            );

            foreach (range(1, 2) as $_) {
                $box = $this->makeBox([
                    'held_by_user_id' => $customer->id,
                    'kitchen_id' => null,
                    'asset_status' => 'active',
                    'total_uses_count' => 1,
                ]);
                OrderMiddoBox::create([
                    'order_id' => $paidOrder->id,
                    'middo_box_id' => $box->id,
                ]);
                MiddoBoxLog::create([
                    'order_id' => $paidOrder->id,
                    'middo_box_id' => $box->id,
                    'custody_status' => 'with_customer',
                    'log_action' => 'delivered_to_corporate',
                ]);
            }
        });

        $this->command?->info('Seeded kitchen/delivery dashboard fixtures (Gulshan + Rahim pipelines).');
    }

    private function makeBoxAtKitchen(int $kitchenId): MiddoBox
    {
        return $this->makeBox([
            'kitchen_id' => $kitchenId,
            'held_by_user_id' => $kitchenId,
            'asset_status' => 'active',
        ]);
    }

    private function makeBox(array $overrides = []): MiddoBox
    {
        $qr = 'MB-KD'.str_pad((string) $this->boxSeq++, 5, '0', STR_PAD_LEFT);

        $box = MiddoBox::create(array_merge([
            'qr_code_id' => $qr,
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'total_uses_count' => 0,
        ], $overrides));

        MiddoBoxLog::create([
            'middo_box_id' => $box->id,
            'custody_status' => $box->isAtKitchen()
                ? 'assigned_at_kitchen'
                : ($box->kitchen_id ? 'in_transit' : 'warehouse'),
            'log_action' => $box->isAtKitchen()
                ? 'received_at_kitchen'
                : ($box->kitchen_id ? 'dispatched_to_kitchen' : 'registered_at_warehouse'),
        ]);

        return $box;
    }

    private function createGroupedOrder(
        User $customer,
        MenuItem $menu,
        string $deliveryDate,
        int $quantity,
        ?int $kitchenId,
        string $status,
        ?string $paymentStatus = null,
        ?int $createdBy = null,
        ?int $updatedBy = null,
        array $extra = [],
    ): Order {
        $total = (int) $menu->price * $quantity;
        $resolvedPayment = $paymentStatus ?? ($status === 'pending' ? 'paid' : 'pending');
        $paid = $resolvedPayment === 'paid' ? $total : 0;

        $order = Order::create(array_merge([
            'user_id' => $customer->id,
            'menu_item_id' => $menu->id,
            'quantity' => $quantity,
            'delivery_date' => $deliveryDate,
            'delivery_time' => '12:00 PM',
            'total_amount' => $total,
            'amount_paid' => $paid,
            'prepaid_amount' => $paid,
            'cash_collected' => 0,
            'address' => 'Corp HQ, Gulshan Avenue, Dhaka',
            'receiver_name' => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')),
            'receiver_mobile' => $customer->mobile,
            'order_status' => $status,
            'payment_status' => $resolvedPayment,
            'created_by' => $createdBy,
            'updated_by' => $updatedBy,
        ], $extra));

        $group = OrderGroup::create([
            'name' => 'GRP-SEED-'.str_pad((string) $this->groupSeq++, 3, '0', STR_PAD_LEFT),
            'menu_id' => $menu->id,
            'delivery_date' => $deliveryDate,
            'kitchen_id' => $kitchenId,
            'created_by' => $createdBy,
            'updated_by' => $updatedBy ?? $kitchenId,
        ]);

        $group->orders()->attach($order->id);

        return $order->fresh(['orderGroup', 'menuItem']);
    }
}
