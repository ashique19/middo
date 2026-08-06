<?php

namespace Tests\Feature\Operation;

use App\Livewire\Corporate\ComplaintSupportModal;
use App\Livewire\Operation\ComplaintShow;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ComplaintMarkCompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_ops_can_mark_complaint_complete_and_corporate_can_reply_until_then(): void
    {
        $opsRole = Role::create(['name' => 'operation']);
        $corpRole = Role::create(['name' => 'corporate']);
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        $ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'User',
            'mobile' => '01310123451',
            'password' => '12345678',
            'role_id' => $opsRole->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'city_id' => $city->id,
            'area_id' => $area->id,
        ]);

        $corporate = User::create([
            'first_name' => 'Corporate',
            'last_name' => 'User',
            'company_name' => 'Acme',
            'mobile' => '01310123452',
            'password' => '12345678',
            'role_id' => $corpRole->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 5000,
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

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'amount_paid' => 0,
            'address' => 'House 12, Road 5',
            'receiver_name' => 'Corporate User',
            'receiver_mobile' => $corporate->mobile,
            'area_id' => $area->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        $root = OrderComplaint::create([
            'order_id' => $order->id,
            'parent_id' => null,
            'is_reply' => false,
            'status' => OrderComplaint::STATUS_OPEN,
            'category' => 'delivery',
            'message' => 'Rider was late and the box arrived cold.',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        Livewire::actingAs($corporate)
            ->test(ComplaintSupportModal::class)
            ->call('openModal', $order->id)
            ->assertSet('hasExistingComplaint', true)
            ->assertSet('complaintResolved', false)
            ->set('message', 'Still waiting — please advise.')
            ->call('reply')
            ->assertSet('successMessage', 'Reply posted.')
            ->assertSee('Still waiting — please advise.');

        $this->assertSame(2, OrderComplaint::query()->where('order_id', $order->id)->count());

        Livewire::actingAs($ops)
            ->test(ComplaintShow::class, ['complaint' => $root])
            ->assertSee('Mark complete')
            ->call('markComplete')
            ->assertSet('statusMessage', 'Complaint marked complete.')
            ->assertDontSee('Mark complete')
            ->assertSee('This complaint is complete');

        $root->refresh();
        $this->assertTrue($root->isResolved());

        Livewire::actingAs($corporate)
            ->test(ComplaintSupportModal::class)
            ->call('openModal', $order->id)
            ->assertSet('complaintResolved', true)
            ->set('message', 'Should not post after complete.')
            ->call('reply')
            ->assertSet('errorMessage', 'This complaint is complete. You can no longer reply.');

        $this->assertSame(2, OrderComplaint::query()->where('order_id', $order->id)->count());
    }
}
