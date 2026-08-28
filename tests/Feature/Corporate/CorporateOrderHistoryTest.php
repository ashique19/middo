<?php

namespace Tests\Feature\Corporate;

use App\Livewire\Corporate\OrderHistory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CorporateOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $corporate;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'corporate']);

        $this->corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'History',
            'company_name' => 'History Corp',
            'mobile' => '01991000010',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'balance' => 5000,
        ]);

        $this->menu = MenuItem::create([
            'name' => 'History Thali',
            'price' => 200,
        ]);
    }

    public function test_order_again_is_hidden_for_first_time_customer(): void
    {
        Livewire::actingAs($this->corporate)
            ->test(OrderHistory::class)
            ->assertSet('hasEverOrdered', false)
            ->assertDontSee('Order Again')
            ->assertSee('Place Your First Order', false);

        $this->actingAs($this->corporate)
            ->get(route('corporates.orders.history'))
            ->assertOk()
            ->assertDontSee('Order Again');
    }

    public function test_order_again_shows_after_customer_has_past_orders(): void
    {
        Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->subDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
        ]);

        Livewire::actingAs($this->corporate)
            ->test(OrderHistory::class)
            ->assertSet('hasEverOrdered', true)
            ->assertSee('Order Again', false);

        $this->actingAs($this->corporate)
            ->get(route('corporates.orders.history'))
            ->assertOk()
            ->assertSee('Order Again', false);
    }

    public function test_order_again_shows_when_only_future_orders_exist(): void
    {
        Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        Livewire::actingAs($this->corporate)
            ->test(OrderHistory::class)
            ->assertSet('hasEverOrdered', true)
            ->assertSee('Order Again', false)
            ->assertSee('No lunch history recorded yet.', false);
    }

    public function test_order_again_is_hidden_when_only_cancelled_orders_exist(): void
    {
        Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->subDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => 'cancelled',
            'payment_status' => 'pending',
        ]);

        Livewire::actingAs($this->corporate)
            ->test(OrderHistory::class)
            ->assertSet('hasEverOrdered', false)
            ->assertDontSee('Order Again');
    }
}
