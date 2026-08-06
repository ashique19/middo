<?php

namespace Tests\Feature;

use App\Livewire\Admin\SettingsPage;
use App\Livewire\Public\OrderCheckoutModal;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\CorporateOrderPrepayment;
use App\Support\MiddoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FullPrepayFromActiveOrdersSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_third_meal_allows_cod_fourth_requires_full_prepay(): void
    {
        $user = $this->makeCorporate('01310123452');

        $atThree = CorporateOrderPrepayment::evaluate($user, 'Corporate User', '01310123452', 3, 1000);
        $this->assertFalse($atThree['required']);
        $this->assertSame(0.0, $atThree['ratio']);
        $this->assertSame(3, $atThree['full_prepay_from']);
        $this->assertSame(3, $atThree['projected_active']);

        $atFour = CorporateOrderPrepayment::evaluate($user, 'Corporate User', '01310123452', 4, 1000);
        $this->assertTrue($atFour['required']);
        $this->assertSame(1.0, $atFour['ratio']);
        $this->assertSame(1000, $atFour['amount']);
        $this->assertSame('active_order_limit', $atFour['reason']);
        $this->assertSame(4, $atFour['projected_active']);
    }

    public function test_existing_active_meal_quantity_counts_toward_threshold(): void
    {
        $user = $this->makeCorporate('01310123453');
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
            'quantity' => 2,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 840,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Existing qty 2 + cart qty 1 = 3 → still COD.
        $atThree = CorporateOrderPrepayment::evaluate($user, 'Corporate User', '01310123453', 1, 420);
        $this->assertFalse($atThree['required']);
        $this->assertSame(3, $atThree['projected_active']);

        // Existing qty 2 + cart qty 2 = 4 → full prepay.
        $atFour = CorporateOrderPrepayment::evaluate($user, 'Corporate User', '01310123453', 2, 840);
        $this->assertTrue($atFour['required']);
        $this->assertSame(1.0, $atFour['ratio']);
        $this->assertSame(4, $atFour['projected_active']);
    }

    public function test_checkout_modal_single_day_qty_three_keeps_cod_qty_four_requires_prepay(): void
    {
        $user = $this->makeCorporate('01310123454');
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
        $this->assertNotEmpty($dates);
        $keep = $dates[0];

        foreach ($dates as $date) {
            $selected = ($component->get('quantities')[$date] ?? 0) > 0;
            $shouldKeep = $date === $keep;
            if ($selected !== $shouldKeep) {
                $component->call('toggleDateSelection', $date);
            }
        }

        while ((int) ($component->get('quantities')[$keep] ?? 0) < 3) {
            $component->call('changeDateQuantity', $keep, 1);
        }

        $this->assertSame(3, (int) ($component->get('quantities')[$keep] ?? 0));
        $this->assertFalse((bool) ($component->get('prepayment')['required'] ?? false));
        $this->assertTrue($component->instance()->codAllowed);

        $component->call('changeDateQuantity', $keep, 1);
        $this->assertSame(4, (int) ($component->get('quantities')[$keep] ?? 0));
        $this->assertTrue((bool) ($component->get('prepayment')['required'] ?? false));
        $this->assertSame(1.0, (float) ($component->get('prepayment')['ratio'] ?? 0));
        $this->assertSame(4, (int) ($component->get('prepayment')['projected_active'] ?? 0));
        $this->assertFalse($component->instance()->codAllowed);
    }

    public function test_admin_can_raise_cod_meal_ceiling(): void
    {
        $adminRole = Role::create(['name' => 'admin']);
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01310123451',
            'password' => '12345678',
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('full_prepay_from_active_orders', 5)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(5, MiddoSettings::fullPrepayFromActiveOrders());
        $this->assertSame(5, MiddoSettings::codMaxActiveOrders());

        $user = $this->makeCorporate('01310123455');
        $five = CorporateOrderPrepayment::evaluate($user, 'Corporate User', '01310123455', 5, 1000);
        $this->assertFalse($five['required']);

        $six = CorporateOrderPrepayment::evaluate($user, 'Corporate User', '01310123455', 6, 800);
        $this->assertTrue($six['required']);
        $this->assertSame(1.0, $six['ratio']);
        $this->assertSame(800, $six['amount']);
        $this->assertSame(5, $six['full_prepay_from']);
    }

    private function makeCorporate(string $mobile): User
    {
        $role = Role::firstOrCreate(['name' => 'corporate']);
        $city = City::firstOrCreate(['name' => 'Dhaka']);
        $area = Area::query()->firstOrCreate(
            ['name' => 'Gulshan', 'city_id' => $city->id]
        );

        return User::create([
            'first_name' => 'Corporate',
            'last_name' => 'User',
            'company_name' => 'Acme',
            'mobile' => $mobile,
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 5000,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'address' => 'House 12, Road 5',
        ]);
    }
}
