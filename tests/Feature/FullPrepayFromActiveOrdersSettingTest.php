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

    public function test_default_requires_full_prepay_from_three_meals_not_date_lines(): void
    {
        $user = $this->makeCorporate('01310123452');

        // Two meals total (e.g. one day qty 2, or two days qty 1) → COD OK.
        $under = CorporateOrderPrepayment::evaluate($user, 'Corporate User', '01310123452', 2, 1000);
        $this->assertFalse($under['required']);
        $this->assertSame(0.0, $under['ratio']);
        $this->assertSame(3, $under['full_prepay_from']);
        $this->assertSame(2, $under['projected_active']);

        // Three meals on a single cart day → full prepay.
        $atLimit = CorporateOrderPrepayment::evaluate($user, 'Corporate User', '01310123452', 3, 1000);
        $this->assertTrue($atLimit['required']);
        $this->assertSame(1.0, $atLimit['ratio']);
        $this->assertSame(1000, $atLimit['amount']);
        $this->assertSame('active_order_limit', $atLimit['reason']);
        $this->assertSame(3, $atLimit['projected_active']);
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

        // Existing qty 2 + cart qty 1 = 3 meals → full prepay even for a single new date line.
        $quote = CorporateOrderPrepayment::evaluate($user, 'Corporate User', '01310123453', 1, 420);
        $this->assertTrue($quote['required']);
        $this->assertSame(1.0, $quote['ratio']);
        $this->assertSame(2, $quote['active_orders']);
        $this->assertSame(1, $quote['new_orders']);
        $this->assertSame(3, $quote['projected_active']);
    }

    public function test_checkout_modal_single_day_qty_three_requires_full_prepay(): void
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
        $this->assertTrue((bool) ($component->get('prepayment')['required'] ?? false));
        $this->assertSame(1.0, (float) ($component->get('prepayment')['ratio'] ?? 0));
        $this->assertSame(3, (int) ($component->get('prepayment')['projected_active'] ?? 0));
        $this->assertFalse($component->instance()->codAllowed);
    }

    public function test_admin_can_raise_full_prepay_threshold(): void
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
        $this->assertSame(4, MiddoSettings::codMaxActiveOrders());

        $user = $this->makeCorporate('01310123455');
        $three = CorporateOrderPrepayment::evaluate($user, 'Corporate User', '01310123455', 3, 1000);
        $this->assertFalse($three['required']);

        $five = CorporateOrderPrepayment::evaluate($user, 'Corporate User', '01310123455', 5, 800);
        $this->assertTrue($five['required']);
        $this->assertSame(1.0, $five['ratio']);
        $this->assertSame(800, $five['amount']);
        $this->assertSame(5, $five['full_prepay_from']);
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
