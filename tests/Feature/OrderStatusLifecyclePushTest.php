<?php

namespace Tests\Feature;

use App\Jobs\SendOrderStatusPush;
use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Kitchen\DispatchOrderModal;
use App\Livewire\Kitchen\MiddoOrderGroups;
use App\Models\DeviceToken;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class OrderStatusLifecyclePushTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_lifecycle_dispatches_push_for_each_status_change(): void
    {
        Queue::fake();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        $corporateRole = Role::create(['name' => 'corporate']);

        $kitchen = User::create([
            'first_name' => 'Gulshan',
            'last_name' => 'Kitchen',
            'mobile' => '01720000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);
        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'One',
            'mobile' => '01720000002',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
        ]);
        $customer = User::create([
            'first_name' => 'Buyer',
            'last_name' => 'Worker',
            'company_name' => 'Acme',
            'mobile' => '01720000003',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
            'balance' => 0,
        ]);

        DeviceToken::create([
            'user_id' => $customer->id,
            'token' => 'fcm-lifecycle-token-abcdefghijklmnopqrstuvwxyz',
            'platform' => 'android',
        ]);

        $menu = MenuItem::create([
            'name' => 'Beef Tehari',
            'summary' => 'Test',
            'price' => 400,
            'kitchen_commission' => 40,
        ]);

        $today = now('Asia/Dhaka')->toDateString();
        $order = Order::create([
            'user_id' => $customer->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 400,
            'amount_paid' => 400,
            'prepaid_amount' => 400,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'created_by' => $customer->id,
            'updated_by' => $customer->id,
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-PUSH-1',
            'menu_id' => $menu->id,
            'delivery_date' => $today,
            'kitchen_id' => null,
        ]);
        $group->orders()->attach($order->id);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-PUSH01',
            'box_model_type' => 'standard_insulated',
            'held_by_user_id' => $kitchen->id,
            'kitchen_id' => $kitchen->id,
            'asset_status' => 'active',
            'total_uses_count' => 0,
        ]);

        // 1) Kitchen accept → processing
        Livewire::actingAs($kitchen)
            ->test(MiddoOrderGroups::class)
            ->call('acceptOrder', $group->id);

        $this->assertSame('processing', $order->fresh()->order_status);
        Queue::assertPushed(SendOrderStatusPush::class, fn ($job) => $job->status === 'processing');

        // 2) Kitchen pack → packed
        Livewire::actingAs($kitchen)
            ->test(DispatchOrderModal::class)
            ->call('openModal', $order->id)
            ->call('toggleBox', $box->id)
            ->call('dispatchOrder');

        $this->assertSame('packed', $order->fresh()->order_status);
        Queue::assertPushed(SendOrderStatusPush::class, fn ($job) => $job->status === 'packed');

        // 3) Rider accept → on_the_way_to_delivery
        Livewire::actingAs($rider)
            ->test(KitchenDispatches::class)
            ->call('acceptOrder', $order->id);

        $this->assertSame('on_the_way_to_delivery', $order->fresh()->order_status);
        Queue::assertPushed(SendOrderStatusPush::class, fn ($job) => $job->status === 'on_the_way_to_delivery');

        // 4) Prepaid deliver → delivered_and_paid (skip plain delivered)
        Livewire::actingAs($rider)
            ->test(KitchenDispatches::class)
            ->call('deliverToConsumer', $order->id);

        $order->refresh();
        $this->assertSame('delivered_and_paid', $order->order_status);
        $this->assertSame('paid', $order->payment_status);
        Queue::assertPushed(SendOrderStatusPush::class, fn ($job) => $job->status === 'delivered_and_paid');
    }

    public function test_cod_delivery_pushes_delivered_then_payment_settlement(): void
    {
        Queue::fake();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        $corporateRole = Role::create(['name' => 'corporate']);

        $kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'Cod',
            'mobile' => '01720000011',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);
        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'Cod',
            'mobile' => '01720000012',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
        ]);
        $customer = User::create([
            'first_name' => 'Buyer',
            'last_name' => 'Cod',
            'mobile' => '01720000013',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
            'balance' => 0,
        ]);

        DeviceToken::create([
            'user_id' => $customer->id,
            'token' => 'fcm-cod-token-abcdefghijklmnopqrstuvwxyz',
            'platform' => 'android',
        ]);

        $menu = MenuItem::create([
            'name' => 'Chicken Curry',
            'summary' => 'Test',
            'price' => 350,
            'kitchen_commission' => 35,
        ]);

        $today = now('Asia/Dhaka')->toDateString();
        $order = Order::create([
            'user_id' => $customer->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 350,
            'amount_paid' => 0,
            'prepaid_amount' => 0,
            'address' => 'Banani',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'created_by' => $customer->id,
            'updated_by' => $customer->id,
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-COD-1',
            'menu_id' => $menu->id,
            'delivery_date' => $today,
            'kitchen_id' => null,
        ]);
        $group->orders()->attach($order->id);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-COD001',
            'box_model_type' => 'standard_insulated',
            'held_by_user_id' => $kitchen->id,
            'kitchen_id' => $kitchen->id,
            'asset_status' => 'active',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($kitchen)
            ->test(MiddoOrderGroups::class)
            ->call('acceptOrder', $group->id);

        Livewire::actingAs($kitchen)
            ->test(DispatchOrderModal::class)
            ->call('openModal', $order->id)
            ->call('toggleBox', $box->id)
            ->call('dispatchOrder');

        Livewire::actingAs($rider)
            ->test(KitchenDispatches::class)
            ->call('acceptOrder', $order->id);

        Livewire::actingAs($rider)
            ->test(KitchenDispatches::class)
            ->call('deliverToConsumer', $order->id);

        $this->assertSame('delivered', $order->fresh()->order_status);
        Queue::assertPushed(SendOrderStatusPush::class, fn ($job) => $job->status === 'delivered');

        $order->update([
            'order_status' => 'delivered_and_paid',
            'payment_status' => 'paid',
            'amount_paid' => 350,
        ]);

        Queue::assertPushed(SendOrderStatusPush::class, fn ($job) => $job->status === 'delivered_and_paid');
    }

    public function test_light_transition_guard_rejects_invalid_status_jump(): void
    {
        $corporateRole = Role::create(['name' => 'corporate']);
        $customer = User::create([
            'first_name' => 'Buyer',
            'last_name' => 'Worker',
            'company_name' => 'Acme',
            'mobile' => '01720000023',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);
        $menu = MenuItem::create([
            'name' => 'Beef Tehari',
            'summary' => 'Test',
            'price' => 400,
        ]);
        $order = Order::create([
            'user_id' => $customer->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 400,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'created_by' => $customer->id,
            'updated_by' => $customer->id,
        ]);

        $this->expectException(\RuntimeException::class);
        OrderTransition::apply($order, OrderTransition::ON_THE_WAY_TO_DELIVERY);
    }
}
