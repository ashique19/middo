<?php

namespace Tests\Feature\Delivery;

use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Kitchen\DispatchOrderModal;
use App\Livewire\Shared\StaffAlertsPage;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\StaffAlert;
use App\Models\User;
use App\Support\OrderTransition;
use App\Support\RiderAccountLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryAreaAlertsR1Test extends TestCase
{
    use RefreshDatabase;

    protected Role $deliveryRole;

    protected Role $kitchenRole;

    protected Role $corporateRole;

    protected City $city;

    protected Area $gulshan;

    protected Area $mirpur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deliveryRole = Role::create(['name' => 'delivery']);
        $this->kitchenRole = Role::create(['name' => 'kitchen']);
        $this->corporateRole = Role::create(['name' => 'corporate']);
        $this->city = City::create(['name' => 'Dhaka']);
        $this->gulshan = Area::create(['name' => 'Gulshan', 'city_id' => $this->city->id]);
        $this->mirpur = Area::create(['name' => 'Mirpur', 'city_id' => $this->city->id]);
    }

    protected function makeRider(string $mobile, Area $area): User
    {
        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => $area->name,
            'mobile' => $mobile,
            'password' => 'password',
            'role_id' => $this->deliveryRole->id,
            'status' => 'active',
            'city_id' => $this->city->id,
            'area_id' => $area->id,
        ]);
        $rider->areas()->sync([$area->id]);

        return $rider;
    }

    protected function makeDispatchedOrder(Area $area, User $kitchen, User $corporate, MenuItem $menu): Order
    {
        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'Office',
            'area_id' => $area->id,
            'order_status' => OrderTransition::READY,
            'payment_status' => 'pending',
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-'.$area->name.'-'.uniqid(),
            'menu_id' => $menu->id,
            'delivery_date' => $order->delivery_date,
            'kitchen_id' => $kitchen->id,
            'area_id' => $area->id,
        ]);
        $group->orders()->attach($order->id);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-'.uniqid(),
            'box_model_type' => 'standard_insulated',
            'held_by_user_id' => $kitchen->id,
            'kitchen_id' => $kitchen->id,
            'asset_status' => 'active',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($kitchen)
            ->test(DispatchOrderModal::class)
            ->call('openModal', $order->id)
            ->call('toggleBox', $box->id)
            ->call('dispatchOrder')
            ->assertSet('showModal', false)
            ->assertSet('errorMessage', null);

        return $order->fresh();
    }

    public function test_rider_only_sees_dispatches_in_service_areas(): void
    {
        $kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'One',
            'mobile' => '01770000001',
            'password' => 'password',
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
        ]);
        $corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'One',
            'mobile' => '01770000002',
            'password' => 'password',
            'role_id' => $this->corporateRole->id,
            'status' => 'active',
            'area_id' => $this->gulshan->id,
        ]);
        $menu = MenuItem::create(['name' => 'Thali', 'price' => 200, 'delivery_commission' => 40]);

        $gulshanRider = $this->makeRider('01770000003', $this->gulshan);
        $mirpurRider = $this->makeRider('01770000004', $this->mirpur);

        $order = $this->makeDispatchedOrder($this->gulshan, $kitchen, $corporate, $menu);

        Livewire::actingAs($gulshanRider)
            ->test(KitchenDispatches::class)
            ->assertSee('#'.$order->id, false);

        Livewire::actingAs($mirpurRider)
            ->test(KitchenDispatches::class)
            ->assertDontSee('#'.$order->id, false);

        Livewire::actingAs($mirpurRider)
            ->test(KitchenDispatches::class)
            ->call('acceptOrder', $order->id)
            ->assertSet('errorMessage', fn ($m) => is_string($m) && str_contains($m, 'service areas'));
    }

    public function test_dispatch_alerts_riders_in_area(): void
    {
        $kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'Two',
            'mobile' => '01770000005',
            'password' => 'password',
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
        ]);
        $corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'Two',
            'mobile' => '01770000006',
            'password' => 'password',
            'role_id' => $this->corporateRole->id,
            'status' => 'active',
            'area_id' => $this->gulshan->id,
        ]);
        $menu = MenuItem::create(['name' => 'Biryani', 'price' => 250, 'delivery_commission' => 35]);
        $gulshanRider = $this->makeRider('01770000007', $this->gulshan);
        $mirpurRider = $this->makeRider('01770000008', $this->mirpur);

        $order = $this->makeDispatchedOrder($this->gulshan, $kitchen, $corporate, $menu);

        $this->assertTrue(StaffAlert::query()
            ->where('user_id', $gulshanRider->id)
            ->where('type', StaffAlert::TYPE_LUNCH_DISPATCH)
            ->where('meta->order_id', $order->id)
            ->exists());
        $this->assertFalse(StaffAlert::query()
            ->where('user_id', $mirpurRider->id)
            ->where('type', StaffAlert::TYPE_LUNCH_DISPATCH)
            ->exists());

        Livewire::actingAs($gulshanRider)
            ->test(StaffAlertsPage::class)
            ->assertOk()
            ->assertSee('New lunch run', false);
    }

    public function test_accept_credits_rider_wallet_once(): void
    {
        $kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'Three',
            'mobile' => '01770000009',
            'password' => 'password',
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
        ]);
        $corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'Three',
            'mobile' => '01770000010',
            'password' => 'password',
            'role_id' => $this->corporateRole->id,
            'status' => 'active',
            'area_id' => $this->gulshan->id,
        ]);
        $menu = MenuItem::create(['name' => 'Khichuri', 'price' => 180, 'delivery_commission' => 45]);
        $rider = $this->makeRider('01770000011', $this->gulshan);
        $order = $this->makeDispatchedOrder($this->gulshan, $kitchen, $corporate, $menu);

        Livewire::actingAs($rider)
            ->test(KitchenDispatches::class)
            ->call('acceptOrder', $order->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(45, RiderAccountLedger::balance($rider->id));

        $order->update(['order_status' => 'delivered_and_paid', 'payment_status' => 'paid', 'amount_paid' => 180]);
        $this->assertSame(45, RiderAccountLedger::balance($rider->id));
    }
}
