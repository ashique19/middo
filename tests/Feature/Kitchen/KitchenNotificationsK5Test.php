<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Operation\AssignKitchenModal;
use App\Livewire\Shared\StaffAlertsPage;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\StaffAlert;
use App\Models\User;
use App\Support\AcceptWindowSla;
use App\Support\KitchenAcceptWindow;
use App\Support\MiddoSettings;
use App\Support\OrderGroupKitchenAssignment;
use App\Support\StaffAlerts;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenNotificationsK5Test extends TestCase
{
    use RefreshDatabase;

    protected User $kitchen;

    protected User $otherKitchen;

    protected User $admin;

    protected User $corporate;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $adminRole = Role::create(['name' => 'admin']);
        $corporateRole = Role::create(['name' => 'corporate']);
        Role::create(['name' => 'operation']);

        $this->kitchen = User::create([
            'first_name' => 'Gulshan',
            'last_name' => 'Kitchen',
            'mobile' => '01760000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
            'kitchen_tier' => 'gold',
            'allowed_open_groups' => 3,
        ]);

        $this->otherKitchen = User::create([
            'first_name' => 'Other',
            'last_name' => 'Kitchen',
            'mobile' => '01760000002',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
            'kitchen_tier' => 'silver',
            'allowed_open_groups' => 2,
        ]);

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01760000003',
            'password' => 'password',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        $this->corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'mobile' => '01760000004',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);

        $this->menu = MenuItem::create([
            'name' => 'Lunch Box',
            'price' => 250,
            'kitchen_commission' => 40,
        ]);

        MiddoSettings::updateMealAndKitchenDefaults([
            'accept_window_minutes' => 120,
            'accept_window_warn_minutes' => 15,
        ]);
    }

    public function test_ops_assign_creates_kitchen_alert(): void
    {
        $group = $this->openGroup(now('Asia/Dhaka')->addHours(1));

        Livewire::actingAs($this->admin)
            ->test(AssignKitchenModal::class)
            ->call('openModal', $group->id)
            ->set('selectedKitchenId', $this->kitchen->id)
            ->call('save');

        $this->assertDatabaseHas('staff_alerts', [
            'user_id' => $this->kitchen->id,
            'type' => StaffAlert::TYPE_GROUP_ASSIGNED,
            'order_group_id' => $group->id,
        ]);
    }

    public function test_shortage_notifies_ops_and_can_be_marked_read(): void
    {
        $group = $this->openGroup(now('Asia/Dhaka')->addHours(1));
        $group->update(['kitchen_id' => $this->kitchen->id]);
        $group->orders()->update(['order_status' => 'processing']);

        OrderGroupKitchenAssignment::reportShortage($group->fresh(), $this->kitchen, 'Out of chicken');

        $this->assertDatabaseHas('staff_alerts', [
            'user_id' => $this->admin->id,
            'type' => StaffAlert::TYPE_NEEDS_REASSIGNMENT,
            'order_group_id' => $group->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(StaffAlertsPage::class)
            ->assertSee('Shortage')
            ->call('markAllRead')
            ->assertSet('statusMessage', 'Marked 1 alert(s) as read.');

        $this->assertSame(0, StaffAlerts::unreadCount($this->admin->id));
    }

    public function test_accept_window_closing_soon_payload_and_sla_warn_once(): void
    {
        $deliveryStart = now('Asia/Dhaka')->addMinutes(10);
        $group = $this->openGroup($deliveryStart);

        $payload = KitchenAcceptWindow::statusPayload($group);
        $this->assertTrue($payload['is_open']);
        $this->assertTrue($payload['closing_soon']);
        $this->assertLessThanOrEqual(15, $payload['minutes_remaining']);

        $first = AcceptWindowSla::warnEligible();
        $this->assertSame(1, $first['groups_checked']);
        $this->assertGreaterThanOrEqual(1, $first['alerts_created']);

        $this->assertDatabaseHas('staff_alerts', [
            'user_id' => $this->kitchen->id,
            'type' => StaffAlert::TYPE_ACCEPT_WINDOW_CLOSING,
            'order_group_id' => $group->id,
        ]);

        $second = AcceptWindowSla::warnEligible();
        $this->assertSame(0, $second['alerts_created']);
    }

    public function test_sla_skips_kitchens_that_declined_today(): void
    {
        $deliveryStart = now('Asia/Dhaka')->addMinutes(10);
        $group = $this->openGroup($deliveryStart);

        OrderGroupKitchenAssignment::decline($group, $this->kitchen, 'Too busy');

        AcceptWindowSla::warnEligible();

        $this->assertDatabaseMissing('staff_alerts', [
            'user_id' => $this->kitchen->id,
            'type' => StaffAlert::TYPE_ACCEPT_WINDOW_CLOSING,
            'order_group_id' => $group->id,
        ]);

        $this->assertDatabaseHas('staff_alerts', [
            'user_id' => $this->otherKitchen->id,
            'type' => StaffAlert::TYPE_ACCEPT_WINDOW_CLOSING,
            'order_group_id' => $group->id,
        ]);
    }

    public function test_warn_command_runs(): void
    {
        $this->artisan('kitchen:warn-accept-windows')
            ->assertSuccessful();
    }

    protected function openGroup(Carbon $deliveryStart): OrderGroup
    {
        $group = OrderGroup::create([
            'name' => 'GRP-ALERT-'.uniqid(),
            'menu_id' => $this->menu->id,
            'delivery_date' => $deliveryStart->toDateString(),
            'kitchen_id' => null,
        ]);

        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 2,
            'delivery_date' => $deliveryStart->toDateString(),
            'delivery_time' => $deliveryStart->format('g:i A'),
            'total_amount' => 500,
            'address' => 'Office',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'created_by' => $this->corporate->id,
        ]);

        $group->orders()->attach($order->id);

        return $group->fresh('orders');
    }
}
