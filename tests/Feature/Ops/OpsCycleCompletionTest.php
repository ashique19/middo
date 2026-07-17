<?php

namespace Tests\Feature\Ops;

use App\Livewire\Delivery\CashHandovers as DeliveryCashHandovers;
use App\Livewire\Delivery\PendingBoxRuns;
use App\Livewire\Kitchen\CashHandovers as KitchenCashHandovers;
use App\Livewire\Kitchen\IncomingBoxes;
use App\Models\Area;
use App\Models\CashHandover;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderMiddoBox;
use App\Models\Role;
use App\Models\User;
use App\Support\MealOrderGrouper;
use App\Support\MiddoCashLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpsCycleCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_group_uses_same_area(): void
    {
        $corporateRole = Role::create(['name' => 'corporate']);
        $city = City::create(['name' => 'Dhaka']);
        $mirpur = Area::create(['name' => 'Mirpur', 'city_id' => $city->id]);
        $gulshan = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        $userA = User::create([
            'first_name' => 'A',
            'last_name' => 'Corp',
            'mobile' => '01710000001',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
            'area_id' => $mirpur->id,
            'city_id' => $city->id,
        ]);
        $userB = User::create([
            'first_name' => 'B',
            'last_name' => 'Corp',
            'mobile' => '01710000002',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
            'area_id' => $gulshan->id,
            'city_id' => $city->id,
        ]);

        $menu = MenuItem::create([
            'name' => 'Thali',
            'summary' => 'Lunch',
            'price' => 300,
            'kitchen_commission' => 40,
        ]);

        $today = now('Asia/Dhaka')->toDateString();

        $orderA = Order::create([
            'user_id' => $userA->id,
            'menu_item_id' => $menu->id,
            'quantity' => 2,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 600,
            'address' => 'Mirpur',
            'area_id' => $mirpur->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'created_by' => $userA->id,
        ]);
        $orderB = Order::create([
            'user_id' => $userB->id,
            'menu_item_id' => $menu->id,
            'quantity' => 2,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 600,
            'address' => 'Gulshan',
            'area_id' => $gulshan->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'created_by' => $userB->id,
        ]);

        $grouper = app(MealOrderGrouper::class);
        $groupA = $grouper->assignOrder($orderA->fresh('user'));
        $groupB = $grouper->assignOrder($orderB->fresh('user'));

        $this->assertNotSame($groupA->id, $groupB->id);
        $this->assertSame($mirpur->id, $groupA->area_id);
        $this->assertSame($gulshan->id, $groupB->area_id);
    }

    public function test_rider_hands_box_to_kitchen_and_kitchen_confirms(): void
    {
        [$kitchen, $rider, $customer, $order, $box] = $this->seedDeliveredBoxFlow();

        Livewire::actingAs($rider)
            ->test(PendingBoxRuns::class)
            ->call('handToKitchen', $box->id)
            ->assertSet('errorMessage', null);

        $box->refresh();
        $this->assertSame($kitchen->id, (int) $box->kitchen_id);
        $this->assertSame($rider->id, (int) $box->held_by_user_id);
        $this->assertTrue(
            MiddoBoxLog::query()
                ->where('middo_box_id', $box->id)
                ->where('log_action', 'returned_to_kitchen')
                ->exists()
        );

        Livewire::actingAs($kitchen)
            ->test(IncomingBoxes::class)
            ->call('receiveBox', $box->id)
            ->assertSet('errorMessage', null);

        $box->refresh();
        $this->assertTrue($box->isAtKitchen($kitchen->id));
    }

    public function test_cash_handover_credits_middo_ledger(): void
    {
        $kitchenRole = Role::create(['name' => 'kitchen']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        $corporateRole = Role::create(['name' => 'corporate']);

        $kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'One',
            'mobile' => '01820000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);
        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'Cash',
            'mobile' => '01820000002',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
            'balance' => 500,
        ]);
        $customer = User::create([
            'first_name' => 'Corp',
            'last_name' => 'Pay',
            'mobile' => '01820000003',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);
        $menu = MenuItem::create([
            'name' => 'Box',
            'summary' => 'Lunch',
            'price' => 250,
            'kitchen_commission' => 40,
        ]);

        $order = Order::create([
            'user_id' => $customer->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 250,
            'address' => 'Office',
            'order_status' => 'delivered_and_paid',
            'payment_status' => 'paid',
            'delivery_rider_id' => $rider->id,
        ]);

        Livewire::actingAs($rider)
            ->test(DeliveryCashHandovers::class)
            ->call('toggleOrder', $order->id)
            ->call('createHandover')
            ->assertSet('errorMessage', null);

        $handover = CashHandover::query()->first();
        $this->assertNotNull($handover);
        $this->assertSame('pending', $handover->status);
        $this->assertSame(250, $handover->amount);

        Livewire::actingAs($kitchen)
            ->test(KitchenCashHandovers::class)
            ->call('accept', $handover->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(250, MiddoCashLedger::balance());
        $this->assertSame(250, (int) $rider->fresh()->balance);
        $this->assertSame('accepted', $handover->fresh()->status);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: Order, 4: MiddoBox}
     */
    protected function seedDeliveredBoxFlow(): array
    {
        $kitchenRole = Role::create(['name' => 'kitchen']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        $corporateRole = Role::create(['name' => 'corporate']);

        $kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'Return',
            'mobile' => '01830000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
            'address' => 'Kitchen Road',
        ]);
        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'Return',
            'mobile' => '01830000002',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
        ]);
        $customer = User::create([
            'first_name' => 'Corp',
            'last_name' => 'Return',
            'mobile' => '01830000003',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);
        $menu = MenuItem::create([
            'name' => 'Return Meal',
            'summary' => 'Lunch',
            'price' => 200,
            'kitchen_commission' => 30,
        ]);

        $order = Order::create([
            'user_id' => $customer->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'Office',
            'order_status' => 'delivered',
            'payment_status' => 'pending',
            'delivery_rider_id' => $rider->id,
            'dispatched_at' => now(),
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-RETURN-1',
            'menu_id' => $menu->id,
            'delivery_date' => $order->delivery_date,
            'kitchen_id' => $kitchen->id,
        ]);
        $group->orders()->attach($order->id);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-RETURN1',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'held_by_user_id' => $rider->id,
            'kitchen_id' => null,
            'total_uses_count' => 1,
        ]);
        OrderMiddoBox::create([
            'order_id' => $order->id,
            'middo_box_id' => $box->id,
        ]);
        MiddoBoxLog::create([
            'order_id' => $order->id,
            'middo_box_id' => $box->id,
            'custody_status' => 'collected_by_rider',
            'log_action' => 'picked_from_corporate_by_delivery',
        ]);

        return [$kitchen, $rider, $customer, $order, $box];
    }
}
