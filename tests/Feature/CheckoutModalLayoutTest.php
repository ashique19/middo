<?php

namespace Tests\Feature;

use App\Livewire\Public\OrderCheckoutModal;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutModalLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_and_payment_are_outside_line_item_scroll_region(): void
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

        $html = Livewire::actingAs($user)
            ->test(OrderCheckoutModal::class)
            ->call('loadOrderCheckout', $menu->id)
            ->assertSee('Coupon code')
            ->assertSee('Payment method')
            ->html();

        $this->assertMatchesRegularExpression(
            '/max-h-\[96px\] overflow-y-auto[\s\S]*?<\/div>\s*<div class="space-y-3 pt-1">[\s\S]*?Delivery Logistics/s',
            $html,
            'Line-item scroll region should only wrap date rows, not delivery/coupon/payment.'
        );

        // Coupon / payment live in the pinned footer after the scrollable body closes.
        $couponPos = strpos($html, 'Coupon code');
        $paymentPos = strpos($html, 'Payment method');
        $scrollMarkerPos = strpos($html, 'max-h-[96px] overflow-y-auto');
        $this->assertNotFalse($couponPos);
        $this->assertNotFalse($paymentPos);
        $this->assertNotFalse($scrollMarkerPos);
        $this->assertGreaterThan($scrollMarkerPos, $couponPos);
        $this->assertGreaterThan($scrollMarkerPos, $paymentPos);

        $scrollChunk = substr($html, $scrollMarkerPos, 1200);
        $this->assertStringNotContainsString('Coupon code', $scrollChunk);
        $this->assertStringNotContainsString('Payment method', $scrollChunk);
    }
}
