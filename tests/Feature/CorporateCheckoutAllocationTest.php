<?php

namespace Tests\Feature;

use App\Livewire\Public\OrderCheckoutModal;
use App\Models\Area;
use App\Models\City;
use App\Models\Coupon;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderConfirmationOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CorporateCheckoutAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_checkout_allocates_full_prepay_on_post_discount_nets(): void
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
            'balance' => 50000,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'address' => 'House 12, Road 5',
        ]);
        $menu = MenuItem::create([
            'name' => 'Tehari',
            'summary' => 'Test',
            'price' => 100,
            'thumbnail' => 'img/menu/menu-1.jpg',
            'is_featured' => true,
            'display_order' => 1,
        ]);
        Coupon::create([
            'code' => 'ODD1',
            'name' => 'Odd taka',
            'type' => Coupon::TYPE_FIXED,
            'value' => 1,
            'min_subtotal' => 0,
            'per_user_limit' => 1,
            'applies_to' => Coupon::APPLIES_ORDERS,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $component = Livewire::test(OrderCheckoutModal::class)
            ->call('loadOrderCheckout', $menu->id);

        $dates = $component->get('availableDates');
        $this->assertGreaterThanOrEqual(2, count($dates));
        $keep = array_slice($dates, 0, 2);

        foreach ($dates as $d) {
            $selected = ($component->get('quantities')[$d] ?? 0) > 0;
            $shouldKeep = in_array($d, $keep, true);
            if ($selected !== $shouldKeep) {
                $component->call('toggleDateSelection', $d);
            }
        }

        // Receiver mismatch forces 100% prepayment of the payable (post-discount) cart.
        $component
            ->set('customerName', 'Other Worker')
            ->set('mobile', '01710000000')
            ->set('addressLine1', 'House 12, Road 5')
            ->set('city_id', $city->id)
            ->set('area_id', $area->id)
            ->set('couponCode', 'ODD1')
            ->call('applyCoupon')
            ->assertSet('couponDiscount', 1)
            ->set('paymentMethod', 'balance')
            ->call('initiateOrderConfirmation')
            ->assertSet('isConfirmingOtp', true);

        OrderConfirmationOtp::generate('01710000000');

        $component
            ->set('otpInput', '1234')
            ->call('finalizeOrder');

        $orders = Order::query()->where('user_id', $user->id)->orderBy('delivery_date')->get();
        $this->assertCount(2, $orders);

        $totalPaid = 0;
        $totalDiscount = 0;
        foreach ($orders as $order) {
            $net = max(0, (int) $order->total_amount - (int) $order->discount_amount);
            $paid = (int) $order->amount_paid;
            $this->assertLessThanOrEqual($net, $paid, 'prepaid must not exceed post-discount line net');
            $this->assertSame($net, $paid, 'full prepay must cover each line net exactly');
            $this->assertSame('paid', $order->payment_status);
            $totalPaid += $paid;
            $totalDiscount += (int) $order->discount_amount;
        }

        $this->assertSame(1, $totalDiscount);
        $this->assertSame(199, $totalPaid);
        $this->assertSame(50000 - 199, (int) $user->fresh()->balance);
    }
}
