<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Kitchen\ActiveOrders;
use App\Livewire\Kitchen\DispatchOrderModal;
use App\Livewire\Kitchen\MiddoOrderGroups;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderGroupEvent;
use App\Models\Role;
use App\Models\User;
use App\Support\KitchenCapacity;
use App\Support\MiddoSettings;
use App\Support\OrderTransition;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\KitchenBoxFactory;
use Tests\TestCase;

class KitchenLifecycleK1Test extends TestCase
{
    use RefreshDatabase;

    protected User $kitchen;

    protected User $customer;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(
            now('Asia/Dhaka')->toDateString().' 11:00 AM',
            'Asia/Dhaka'
        ));

        MiddoSettings::updateMealAndKitchenDefaults([
            'accept_window_minutes' => 120,
        ]);

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $corporateRole = Role::create(['name' => 'corporate']);

        $this->kitchen = User::create([
            'first_name' => 'Gulshan',
            'last_name' => 'Kitchen',
            'mobile' => '01730000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
            'kitchen_tier' => 'gold',
            'allowed_open_groups' => 2,
        ]);

        $this->customer = User::create([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'mobile' => '01730000002',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);

        $this->menu = MenuItem::create([
            'name' => 'Lunch Box A',
            'summary' => 'Daily lunch',
            'price' => 250,
            'kitchen_commission' => 50,
        ]);

        KitchenBoxFactory::seedSendable($this->kitchen, 5);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function createPoolGroup(string $name = 'GRP-K1', string $deliveryTime = '12:00 PM'): OrderGroup
    {
        $order = Order::create([
            'user_id' => $this->customer->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => $deliveryTime,
            'total_amount' => 250,
            'address' => 'Test',
            'order_status' => 'pending',
            'payment_status' => 'paid',
        ]);

        $group = OrderGroup::create([
            'name' => $name,
            'menu_id' => $this->menu->id,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'kitchen_id' => null,
        ]);
        $group->orders()->attach($order->id);

        return $group->fresh(['orders']);
    }

    public function test_accept_rejected_outside_window(): void
    {
        $group = $this->createPoolGroup('GRP-EARLY', '12:00 PM');

        Carbon::setTestNow(Carbon::parse(
            now('Asia/Dhaka')->toDateString().' 8:00 AM',
            'Asia/Dhaka'
        ));

        Livewire::actingAs($this->kitchen)
            ->test(MiddoOrderGroups::class)
            ->call('acceptOrder', $group->id)
            ->assertSet('errorMessage', fn ($msg) => is_string($msg) && str_contains($msg, 'Accept window opens'));

        $this->assertNull($group->fresh()->kitchen_id);
    }

    public function test_accept_allowed_inside_window(): void
    {
        $group = $this->createPoolGroup('GRP-IN-WINDOW');

        Livewire::actingAs($this->kitchen)
            ->test(MiddoOrderGroups::class)
            ->call('acceptOrder', $group->id)
            ->assertSet('errorMessage', null);

        $this->assertSame($this->kitchen->id, $group->fresh()->kitchen_id);
        $this->assertDatabaseHas('order_group_events', [
            'order_group_id' => $group->id,
            'type' => OrderGroupEvent::TYPE_ACCEPT,
            'kitchen_id' => $this->kitchen->id,
        ]);
    }

    public function test_cannot_dispatch_from_processing_must_mark_ready_first(): void
    {
        $group = $this->createPoolGroup('GRP-READY');
        Livewire::actingAs($this->kitchen)
            ->test(MiddoOrderGroups::class)
            ->call('acceptOrder', $group->id);

        $order = $group->orders()->first();
        $box = MiddoBox::create([
            'qr_code_id' => 'MB-K1-01',
            'box_model_type' => 'standard_insulated',
            'held_by_user_id' => $this->kitchen->id,
            'kitchen_id' => $this->kitchen->id,
            'asset_status' => 'active',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($this->kitchen)
            ->test(DispatchOrderModal::class)
            ->call('openModal', $order->id)
            ->assertSet('errorMessage', 'Mark this order ready first.');

        Livewire::actingAs($this->kitchen)
            ->test(ActiveOrders::class)
            ->call('markReady', $order->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(OrderTransition::READY, $order->fresh()->order_status);

        // Without a rider claim, kitchen still cannot pack/dispatch.
        Livewire::actingAs($this->kitchen)
            ->test(DispatchOrderModal::class)
            ->call('openModal', $order->id)
            ->assertSet('errorMessage', 'Wait for a rider to accept this order before dispatching.');
    }

    public function test_release_returns_group_to_pool_and_frees_capacity(): void
    {
        $this->kitchen->update(['allowed_open_groups' => 1]);
        $group = $this->createPoolGroup('GRP-RELEASE');

        Livewire::actingAs($this->kitchen)
            ->test(MiddoOrderGroups::class)
            ->call('acceptOrder', $group->id);

        $this->assertFalse(KitchenCapacity::canAccept($this->kitchen->fresh()));

        Livewire::actingAs($this->kitchen)
            ->test(ActiveOrders::class)
            ->call('releaseGroup', $group->id)
            ->assertSet('errorMessage', null);

        $group->refresh();
        $this->assertNull($group->kitchen_id);
        $this->assertSame('pending', $group->orders()->first()->order_status);
        $this->assertTrue(KitchenCapacity::canAccept($this->kitchen->fresh()));
        $this->assertDatabaseHas('order_group_events', [
            'order_group_id' => $group->id,
            'type' => OrderGroupEvent::TYPE_RELEASE,
        ]);
    }

    public function test_cannot_release_after_ready(): void
    {
        $group = $this->createPoolGroup('GRP-NO-REL');
        Livewire::actingAs($this->kitchen)
            ->test(MiddoOrderGroups::class)
            ->call('acceptOrder', $group->id);

        $order = $group->orders()->first();
        OrderTransition::apply($order->fresh(), OrderTransition::READY);

        Livewire::actingAs($this->kitchen)
            ->test(ActiveOrders::class)
            ->call('releaseGroup', $group->id)
            ->assertSet('errorMessage', fn ($msg) => is_string($msg) && str_contains($msg, 'Cannot release'));

        $this->assertSame($this->kitchen->id, $group->fresh()->kitchen_id);
    }

    public function test_decline_hides_group_from_kitchen_and_keeps_pool_open(): void
    {
        $group = $this->createPoolGroup('GRP-DECLINE');

        Livewire::actingAs($this->kitchen)
            ->test(MiddoOrderGroups::class)
            ->set('declineGroupId', $group->id)
            ->set('declineReason', 'Too far from kitchen')
            ->call('confirmDecline')
            ->assertSet('errorMessage', null);

        $this->assertNull($group->fresh()->kitchen_id);
        $this->assertDatabaseHas('order_group_events', [
            'order_group_id' => $group->id,
            'type' => OrderGroupEvent::TYPE_DECLINE,
            'reason' => 'Too far from kitchen',
        ]);

        Livewire::actingAs($this->kitchen)
            ->test(MiddoOrderGroups::class)
            ->assertDontSee('GRP-DECLINE');
    }

    public function test_shortage_releases_group_with_event(): void
    {
        $group = $this->createPoolGroup('GRP-SHORT');
        Livewire::actingAs($this->kitchen)
            ->test(MiddoOrderGroups::class)
            ->call('acceptOrder', $group->id);

        Livewire::actingAs($this->kitchen)
            ->test(ActiveOrders::class)
            ->set('shortageGroupId', $group->id)
            ->set('shortageReason', 'Out of chicken')
            ->call('confirmShortage')
            ->assertSet('errorMessage', null);

        $this->assertNull($group->fresh()->kitchen_id);
        $this->assertDatabaseHas('order_group_events', [
            'order_group_id' => $group->id,
            'type' => OrderGroupEvent::TYPE_SHORTAGE,
            'reason' => 'Out of chicken',
        ]);
        $this->assertTrue(KitchenCapacity::canAccept($this->kitchen->fresh()));
    }
}
