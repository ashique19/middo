<?php

namespace Tests\Feature;

use App\Livewire\Corporate\DeleteOrderModal;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CorporateDeleteOrderModalCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepaid_delete_modal_shows_refund_amount_menu_and_confirm(): void
    {
        [$user, $menu] = $this->seedCorporate();
        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'amount_paid' => 420,
            'prepaid_amount' => 420,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'balance',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(DeleteOrderModal::class)
            ->dispatch('open-delete-order-modal', orderId: $order->id)
            ->assertSet('showModal', true)
            ->assertSet('isPrepaid', true)
            ->assertSet('refundableAmount', 420)
            ->assertSee('Your prepaid balance ৳420 would be added to your Middo wallet.')
            ->assertSee('You can use this balance for future orders.')
            ->assertSee('Tehari')
            ->assertSee('৳420')
            ->assertSee('Are you sure?')
            ->assertSee('Delete Order')
            ->assertDontSee('Any prepaid amount will be credited back to your Middo Balance');
    }

    public function test_unpaid_cod_delete_modal_explains_no_wallet_credit(): void
    {
        [$user, $menu] = $this->seedCorporate();
        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'amount_paid' => 0,
            'prepaid_amount' => 0,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(DeleteOrderModal::class)
            ->dispatch('open-delete-order-modal', orderId: $order->id)
            ->assertSet('showModal', true)
            ->assertSet('isPrepaid', false)
            ->assertSee('This unpaid COD order will be cancelled with no wallet credit.')
            ->assertSee('Tehari')
            ->assertSee('Are you sure?')
            ->assertDontSee('would be added to your Middo wallet');
    }

    /**
     * @return array{0: User, 1: MenuItem}
     */
    private function seedCorporate(): array
    {
        $role = Role::create(['name' => 'corporate']);
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);
        $user = User::create([
            'first_name' => 'Corporate',
            'last_name' => 'User',
            'company_name' => 'Acme',
            'mobile' => '01310123452',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 1000,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'address' => 'House 12, Road 5',
        ]);
        $menu = MenuItem::create([
            'name' => 'Tehari',
            'summary' => 'Test',
            'price' => 420,
            'thumbnail' => 'img/menu/menu-1.jpg',
            'is_featured' => true,
            'display_order' => 1,
        ]);

        return [$user, $menu];
    }
}
