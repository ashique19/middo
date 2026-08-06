<?php

namespace Tests\Feature\Ops;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderGroupOrder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderGroupShowTest extends TestCase
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

    public function test_operation_can_open_order_group_detail_and_see_linked_name_on_order(): void
    {
        $ops = $this->user('operation', ['mobile' => '01310999001']);
        $corporate = $this->user('corporate', [
            'mobile' => '01310999002',
            'company_name' => 'Link Corp',
        ]);
        $kitchen = $this->user('kitchen', [
            'mobile' => '01310999003',
            'first_name' => 'Kitchen',
            'last_name' => 'Alpha',
        ]);

        $menu = MenuItem::create([
            'name' => 'Dal Bhat',
            'price' => 220,
            'status' => 'active',
        ]);

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '1:00 PM',
            'total_amount' => 220,
            'amount_paid' => 220,
            'address' => 'Gulshan 1',
            'receiver_name' => 'Desk',
            'receiver_mobile' => '01710000011',
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'created_by' => $ops->id,
            'updated_by' => $ops->id,
        ]);

        $group = OrderGroup::create([
            'name' => 'Gulshan Dal Group',
            'delivery_date' => $order->delivery_date,
            'menu_id' => $menu->id,
            'kitchen_id' => $kitchen->id,
            'created_by' => $ops->id,
        ]);

        OrderGroupOrder::create([
            'order_group_id' => $group->id,
            'order_id' => $order->id,
        ]);

        $groupUrl = route('operation.order-groups.show', $group);

        $this->actingAs($ops)
            ->get($groupUrl)
            ->assertOk()
            ->assertSee('Gulshan Dal Group')
            ->assertSee('Dal Bhat')
            ->assertSee('Kitchen Alpha')
            ->assertSee('#'.$order->id);

        $this->actingAs($ops)
            ->get(route('operation.orders.show', $order))
            ->assertOk()
            ->assertSee('Gulshan Dal Group')
            ->assertSee($groupUrl, false);
    }

    public function test_kitchen_cannot_open_ops_order_group_detail(): void
    {
        $kitchen = $this->user('kitchen', ['mobile' => '01310999004']);
        $ops = $this->user('operation', ['mobile' => '01310999005']);

        $menu = MenuItem::create([
            'name' => 'Veg Box',
            'price' => 180,
            'status' => 'active',
        ]);

        $group = OrderGroup::create([
            'name' => 'Private Group',
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'menu_id' => $menu->id,
            'kitchen_id' => $kitchen->id,
            'created_by' => $ops->id,
        ]);

        $this->actingAs($kitchen)
            ->get(route('operation.order-groups.show', $group))
            ->assertStatus(302);
    }
}
