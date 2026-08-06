<?php

namespace Tests\Feature;

use App\Livewire\Corporate\Dashboard;
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

class CorporateOrderComplaintBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_marks_orders_with_raised_complaints(): void
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

        $withComplaint = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'amount_paid' => 0,
            'address' => 'House 12, Road 5',
            'receiver_name' => 'Corporate User',
            'receiver_mobile' => $user->mobile,
            'area_id' => $area->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $withoutComplaint = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDays(2)->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'amount_paid' => 0,
            'address' => 'House 12, Road 5',
            'receiver_name' => 'Corporate User',
            'receiver_mobile' => $user->mobile,
            'area_id' => $area->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        OrderComplaint::create([
            'order_id' => $withComplaint->id,
            'parent_id' => null,
            'is_reply' => false,
            'category' => 'delivery',
            'message' => 'Rider was late and the box arrived cold.',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $component = Livewire::actingAs($user)
            ->test(Dashboard::class);

        $upcoming = collect($component->get('upcomingEvents'));
        $flagged = $upcoming->firstWhere('id', $withComplaint->id);
        $clean = $upcoming->firstWhere('id', $withoutComplaint->id);

        $this->assertNotNull($flagged);
        $this->assertTrue((bool) $flagged['has_complaint']);
        $this->assertNotNull($clean);
        $this->assertFalse((bool) $clean['has_complaint']);

        $component
            ->assertSee('Complaint')
            ->assertSee('Complaint raised — view')
            ->assertSee('Complaint / Support');
    }
}
