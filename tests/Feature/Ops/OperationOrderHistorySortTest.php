<?php

namespace Tests\Feature\Ops;

use App\Livewire\Operation\OrderHistory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperationOrderHistorySortTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_history_lists_newest_delivery_dates_and_ids_first(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 12:00:00', 'Asia/Dhaka'));

        $role = Role::firstOrCreate(['name' => 'operation']);
        $ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'User',
            'mobile' => '01310999001',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ]);

        $corporateRole = Role::firstOrCreate(['name' => 'corporate']);
        $customer = User::create([
            'first_name' => 'Corp',
            'last_name' => 'Buyer',
            'company_name' => 'Acme',
            'mobile' => '01310999002',
            'password' => '12345678',
            'role_id' => $corporateRole->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 5000,
            'address' => 'House 1',
        ]);

        $menu = MenuItem::create([
            'name' => 'Lunch Box',
            'price' => 350,
            'status' => 'active',
        ]);

        $olderDayOlderId = Order::create([
            'user_id' => $customer->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => '2026-07-18',
            'delivery_time' => '12:00 PM',
            'total_amount' => 350,
            'amount_paid' => 350,
            'address' => 'A',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'created_by' => $customer->id,
            'updated_by' => $customer->id,
        ]);

        $newerDayOlderId = Order::create([
            'user_id' => $customer->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => '2026-07-19',
            'delivery_time' => '12:00 PM',
            'total_amount' => 350,
            'amount_paid' => 350,
            'address' => 'B',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'created_by' => $customer->id,
            'updated_by' => $customer->id,
        ]);

        $newerDayNewerId = Order::create([
            'user_id' => $customer->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => '2026-07-19',
            'delivery_time' => '12:00 PM',
            'total_amount' => 350,
            'amount_paid' => 350,
            'address' => 'C',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'created_by' => $customer->id,
            'updated_by' => $customer->id,
        ]);

        $this->actingAs($ops);

        Livewire::test(OrderHistory::class)
            ->set('viewMode', 'list')
            ->assertViewHas('flatOrders', function (array $flatOrders) use ($olderDayOlderId, $newerDayOlderId, $newerDayNewerId) {
                $ids = array_column($flatOrders, 'id');

                return $ids === [
                    $newerDayNewerId->id,
                    $newerDayOlderId->id,
                    $olderDayOlderId->id,
                ];
            })
            ->assertViewHas('dateSections', function (array $dateSections) {
                return array_column($dateSections, 'date') === ['2026-07-19', '2026-07-18'];
            });

        Carbon::setTestNow();
    }
}
