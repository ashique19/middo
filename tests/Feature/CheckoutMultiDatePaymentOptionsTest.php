<?php

namespace Tests\Feature;

use App\Livewire\Public\OrderCheckoutModal;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderPaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutMultiDatePaymentOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_dates_still_offer_cod_balance_and_online(): void
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
            'price' => 420,
            'thumbnail' => 'img/menu/menu-1.jpg',
            'is_featured' => true,
            'display_order' => 1,
        ]);

        $component = Livewire::actingAs($user)
            ->test(OrderCheckoutModal::class)
            ->call('loadOrderCheckout', $menu->id);

        $dates = $component->get('availableDates');
        $this->assertGreaterThanOrEqual(2, count($dates));

        // Keep exactly two dates selected.
        foreach ($dates as $index => $date) {
            $selected = ($component->get('quantities')[$date] ?? 0) > 0;
            $shouldKeep = $index < 2;
            if ($selected !== $shouldKeep) {
                $component->call('toggleDateSelection', $date);
            }
        }

        $active = array_filter($component->get('quantities'), fn ($qty) => $qty > 0);
        $this->assertCount(2, $active);
        $this->assertFalse((bool) ($component->get('prepayment')['required'] ?? false));
        $this->assertTrue($component->instance()->codAllowed);
        $this->assertTrue($component->instance()->balancePaymentAvailable);

        $html = $component->html();
        $this->assertStringContainsString('data-testid="checkout-payment-methods"', $html);
        $this->assertStringContainsString('role="radiogroup"', $html);
        $this->assertStringContainsString('value="cash_on_delivery"', $html);
        $this->assertStringContainsString('value="balance"', $html);
        $this->assertStringContainsString('value="gateway"', $html);
        $this->assertStringContainsString('Cash on Delivery', $html);
        $this->assertStringContainsString('Middo Balance', $html);
        $this->assertStringContainsString('Online payment', $html);
        $this->assertStringContainsString('Available for up to 3 meals', $html);
        $this->assertStringNotContainsString('Unavailable — add money', $html);
        // Must not regress to a payment <select>, and payment must not sit in a clipped scroller.
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*(paymentMethod|payment_method)/i', $html);
        $this->assertStringNotContainsString('md:max-h-[58%]', $html);

        $this->assertSame(
            [
                OrderPaymentMethod::CASH_ON_DELIVERY,
                OrderPaymentMethod::BALANCE,
                OrderPaymentMethod::GATEWAY,
            ],
            OrderPaymentMethod::checkoutOptions(false, 2, 50000, 840)
        );
    }

    public function test_three_dates_still_allow_cod_under_meal_ceiling(): void
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

        $component = Livewire::actingAs($user)
            ->test(OrderCheckoutModal::class)
            ->call('loadOrderCheckout', $menu->id);

        $dates = $component->get('availableDates');
        $this->assertGreaterThanOrEqual(3, count($dates));

        foreach ($dates as $index => $date) {
            $selected = ($component->get('quantities')[$date] ?? 0) > 0;
            $shouldKeep = $index < 3;
            if ($selected !== $shouldKeep) {
                $component->call('toggleDateSelection', $date);
            }
        }

        $active = array_filter($component->get('quantities'), fn ($qty) => $qty > 0);
        $this->assertCount(3, $active);
        $this->assertSame(3, (int) array_sum($active));
        $this->assertFalse((bool) ($component->get('prepayment')['required'] ?? false));
        $this->assertTrue($component->instance()->codAllowed);

        $html = $component->html();
        $this->assertStringContainsString('value="cash_on_delivery"', $html);
        $this->assertStringContainsString('Available for up to 3 meals', $html);
    }

    public function test_four_dates_drop_cod_and_keep_prepaid_methods(): void
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

        $component = Livewire::actingAs($user)
            ->test(OrderCheckoutModal::class)
            ->call('loadOrderCheckout', $menu->id);

        $dates = $component->get('availableDates');
        $this->assertGreaterThanOrEqual(4, count($dates));

        foreach ($dates as $index => $date) {
            $selected = ($component->get('quantities')[$date] ?? 0) > 0;
            $shouldKeep = $index < 4;
            if ($selected !== $shouldKeep) {
                $component->call('toggleDateSelection', $date);
            }
        }

        $active = array_filter($component->get('quantities'), fn ($qty) => $qty > 0);
        $this->assertCount(4, $active);
        $this->assertSame(4, (int) array_sum($active));
        $this->assertTrue((bool) ($component->get('prepayment')['required'] ?? false));
        $this->assertSame(1.0, (float) ($component->get('prepayment')['ratio'] ?? 0));
        $this->assertFalse($component->instance()->codAllowed);

        $html = $component->html();
        $this->assertStringNotContainsString('value="cash_on_delivery"', $html);
        $this->assertStringContainsString('value="balance"', $html);
        $this->assertStringContainsString('value="gateway"', $html);
        $this->assertStringContainsString('Cash on Delivery is limited to 3 meals', $html);
        $this->assertStringContainsString('Full prepayment required above 3 meals', $html);
        $this->assertStringContainsString('Choose Middo Balance or online payment', $html);
    }
}
