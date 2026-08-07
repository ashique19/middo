<?php

namespace Tests\Feature;

use App\Livewire\Admin\SettingsPage;
use App\Livewire\Shared\CorporateShow;
use App\Models\Area;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use App\Support\CorporateOrderLimit;
use App\Support\MiddoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CorporateDailyOrderLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_default_and_corporate_override_apply(): void
    {
        $adminRole = Role::create(['name' => 'admin']);
        $opsRole = Role::create(['name' => 'operation']);
        $corpRole = Role::create(['name' => 'corporate']);

        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01310123451',
            'password' => '12345678',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        $ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'User',
            'mobile' => '01310123901',
            'password' => '12345678',
            'role_id' => $opsRole->id,
            'status' => 'active',
        ]);

        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        $corporate = User::create([
            'first_name' => 'Corp',
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
            'address' => 'House 1',
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('max_order_qty_allowed', 8)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Corporate allowed order per day');

        $this->assertSame(8, MiddoSettings::maxOrderQtyAllowed());
        $this->assertSame(8, CorporateOrderLimit::maxAllowed());
        $this->assertSame(8, CorporateOrderLimit::maxAllowed($corporate->id));

        Livewire::actingAs($ops)
            ->test(CorporateShow::class, ['corporate' => $corporate])
            ->set('maxOrderQtyAllowed', '12')
            ->call('saveOrderLimit')
            ->assertSet('errorMessage', '')
            ->assertSee('Order limit set to 12');

        $this->assertSame(12, (int) $corporate->fresh()->max_order_qty_allowed);
        $this->assertSame(12, CorporateOrderLimit::maxAllowed($corporate->id));
        $this->assertSame(8, CorporateOrderLimit::defaultMaxAllowed());

        Livewire::actingAs($ops)
            ->test(CorporateShow::class, ['corporate' => $corporate->fresh()])
            ->set('maxOrderQtyAllowed', '')
            ->call('saveOrderLimit')
            ->assertSet('errorMessage', '');

        $this->assertNull($corporate->fresh()->max_order_qty_allowed);
        $this->assertSame(8, CorporateOrderLimit::maxAllowed($corporate->id));
    }
}
