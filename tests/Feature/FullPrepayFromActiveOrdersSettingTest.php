<?php

namespace Tests\Feature;

use App\Livewire\Admin\SettingsPage;
use App\Models\Area;
use App\Models\City;
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

    public function test_default_requires_full_prepay_from_three_projected_active_orders(): void
    {
        $user = $this->makeCorporate();

        $under = CorporateOrderPrepayment::evaluate($user, 'Corporate User', '01310123452', 2, 1000);
        $this->assertFalse($under['required']);
        $this->assertSame(0.0, $under['ratio']);
        $this->assertSame(3, $under['full_prepay_from']);

        $atLimit = CorporateOrderPrepayment::evaluate($user, 'Corporate User', '01310123452', 3, 1000);
        $this->assertTrue($atLimit['required']);
        $this->assertSame(1.0, $atLimit['ratio']);
        $this->assertSame(1000, $atLimit['amount']);
        $this->assertSame('active_order_limit', $atLimit['reason']);
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

        $user = $this->makeCorporate();
        $three = CorporateOrderPrepayment::evaluate($user, 'Corporate User', '01310123452', 3, 1000);
        $this->assertFalse($three['required']);

        $five = CorporateOrderPrepayment::evaluate($user, 'Corporate User', '01310123452', 5, 800);
        $this->assertTrue($five['required']);
        $this->assertSame(1.0, $five['ratio']);
        $this->assertSame(800, $five['amount']);
        $this->assertSame(5, $five['full_prepay_from']);
    }

    private function makeCorporate(): User
    {
        $role = Role::create(['name' => 'corporate']);
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        return User::create([
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
    }
}
