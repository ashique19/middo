<?php

namespace Tests\Feature\Ops;

use App\Livewire\Shared\OrderShow;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderGroupOrder;
use App\Models\OrderLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderShowTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name]);
    }

    private function user(string $roleName, array $overrides = []): User
    {
        $role = $this->role($roleName);

        return User::create(array_merge([
            'first_name' => ucfirst($roleName),
            'last_name' => 'User',
            'mobile' => '01310'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ], $overrides));
    }

    public function test_operation_and_admin_can_open_order_detail_with_tracking(): void
    {
        $ops = $this->user('operation', ['mobile' => '01310888001']);
        $admin = $this->user('admin', ['mobile' => '01310888002']);
        $corporate = $this->user('corporate', [
            'mobile' => '01310888003',
            'company_name' => 'Acme Foods',
            'first_name' => 'Corp',
            'last_name' => 'Buyer',
            'balance' => 9000,
        ]);
        $kitchen = $this->user('kitchen', [
            'mobile' => '01310888004',
            'first_name' => 'Chef',
            'last_name' => 'One',
        ]);
        $rider = $this->user('delivery', [
            'mobile' => '01310888005',
            'first_name' => 'Rider',
            'last_name' => 'Ali',
        ]);

        $menu = MenuItem::create([
            'name' => 'Chicken Bowl',
            'price' => 350,
            'status' => 'active',
        ]);

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 2,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 700,
            'amount_paid' => 0,
            'address' => 'Banani Road 11',
            'receiver_name' => 'Front Desk',
            'receiver_mobile' => '01710000000',
            'order_status' => 'packed',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'delivery_rider_id' => $rider->id,
            'created_by' => $corporate->id,
            'updated_by' => $ops->id,
        ]);

        $group = OrderGroup::create([
            'name' => 'Banani Lunch',
            'delivery_date' => $order->delivery_date,
            'menu_id' => $menu->id,
            'kitchen_id' => $kitchen->id,
            'created_by' => $ops->id,
        ]);

        OrderGroupOrder::create([
            'order_group_id' => $group->id,
            'order_id' => $order->id,
        ]);

        OrderLog::create([
            'order_id' => $order->id,
            'event' => 'order_status_changed',
            'metadata' => [
                'changes' => [
                    'order_status' => ['from' => 'pending', 'to' => 'packed'],
                ],
            ],
            'performed_by' => $ops->id,
        ]);

        $this->actingAs($ops)
            ->get(route('operation.orders.show', $order))
            ->assertOk()
            ->assertSee('Order #'.$order->id)
            ->assertSee('Acme Foods')
            ->assertSee('Chef One')
            ->assertSee('Rider Ali')
            ->assertSee('Banani Road 11')
            ->assertSee('Tracking log')
            ->assertSee('Status Updated')
            ->assertSee('Profile →')
            ->assertSee(route('operation.kitchens.show', $kitchen), false)
            ->assertSee(route('operation.deliveries.show', $rider), false);

        $this->actingAs($ops)
            ->get(route('operation.kitchens.show', $kitchen))
            ->assertOk()
            ->assertSee('Chef One')
            ->assertSee('kitchen profile')
            ->assertSee('Profile details');

        $this->actingAs($ops)
            ->get(route('operation.deliveries.show', $rider))
            ->assertOk()
            ->assertSee('Rider Ali')
            ->assertSee('delivery profile');

        Livewire::actingAs($ops)
            ->test(OrderShow::class, ['order' => $order])
            ->assertSee('Cash on Delivery')
            ->assertSee('Banani Lunch');

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Acme Foods')
            ->assertSee('Rider Ali')
            ->assertSee(route('admin.kitchens.show', $kitchen), false)
            ->assertSee(route('admin.deliveries.show', $rider), false);
    }

    public function test_corporate_cannot_open_staff_order_show(): void
    {
        $corporate = $this->user('corporate', ['mobile' => '01310888006', 'company_name' => 'No Access']);
        $menu = MenuItem::create(['name' => 'Rice', 'price' => 200, 'status' => 'active']);
        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'A',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        $this->actingAs($corporate)
            ->get(route('operation.orders.show', $order))
            ->assertRedirect();
    }
}
