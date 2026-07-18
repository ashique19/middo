<?php

namespace Tests\Feature;

use App\Livewire\Kitchen\ActiveOrders as KitchenActiveOrders;
use App\Livewire\Operation\ActiveOrders as OperationActiveOrders;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrdersExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_active_orders_can_export_excel(): void
    {
        $role = Role::create(['name' => 'operation']);
        $user = User::create([
            'first_name' => 'Ops',
            'last_name' => 'User',
            'mobile' => '01310123460',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $menu = MenuItem::create([
            'name' => 'Tehari',
            'summary' => 'Test',
            'price' => 420,
            'thumbnail' => 'img/menu/menu-1.jpg',
            'is_featured' => true,
            'display_order' => 1,
        ]);

        Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'amount_paid' => 0,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(OperationActiveOrders::class)
            ->call('exportExcel')
            ->assertFileDownloaded();
    }

    public function test_kitchen_active_orders_can_export_excel(): void
    {
        $role = Role::create(['name' => 'kitchen']);
        $user = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'User',
            'mobile' => '01310123461',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($user);

        Livewire::test(KitchenActiveOrders::class)
            ->call('exportExcel')
            ->assertFileDownloaded();
    }
}
