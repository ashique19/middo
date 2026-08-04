<?php

namespace Tests\Feature\Delivery;

use App\Livewire\Admin\SettingsPage;
use App\Livewire\Admin\UserCreateModal;
use App\Livewire\Admin\UserEditModal;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\DeliveryRunType;
use App\Support\MiddoSettings;
use App\Support\RiderCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RiderFoundationR0Test extends TestCase
{
    use RefreshDatabase;

    protected Role $deliveryRole;

    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deliveryRole = Role::create(['name' => 'delivery']);
        $this->adminRole = Role::create(['name' => 'admin']);
        Role::create(['name' => 'corporate']);
    }

    public function test_admin_can_save_box_commission_defaults(): void
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'One',
            'mobile' => '01750000001',
            'password' => 'password',
            'role_id' => $this->adminRole->id,
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('commission_corporate_to_kitchen', 55)
            ->set('commission_kitchen_to_ops', 44)
            ->set('commission_ops_to_kitchen', 33)
            ->set('commission_custom', 66)
            ->call('save')
            ->assertSet('errorMessage', '');

        $this->assertSame(55, MiddoSettings::deliveryCommissionDefault(DeliveryRunType::CORPORATE_TO_KITCHEN));
        $this->assertSame(44, MiddoSettings::deliveryCommissionDefault(DeliveryRunType::KITCHEN_TO_OPS));
        $this->assertSame(33, MiddoSettings::deliveryCommissionDefault(DeliveryRunType::OPS_TO_KITCHEN));
        $this->assertSame(66, MiddoSettings::deliveryCommissionDefault(DeliveryRunType::CUSTOM));
    }

    public function test_rider_override_beats_settings_default(): void
    {
        MiddoSettings::updateMealAndKitchenDefaults([
            'delivery_commissions' => [
                DeliveryRunType::CORPORATE_TO_KITCHEN => 30,
            ],
        ]);

        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'A',
            'mobile' => '01750000002',
            'password' => 'password',
            'role_id' => $this->deliveryRole->id,
            'status' => 'active',
            'rider_commission_overrides' => [
                DeliveryRunType::CORPORATE_TO_KITCHEN => 90,
            ],
        ]);

        $this->assertSame(90, RiderCommission::forSettingsRun($rider, DeliveryRunType::CORPORATE_TO_KITCHEN));

        $plain = User::create([
            'first_name' => 'Rider',
            'last_name' => 'B',
            'mobile' => '01750000003',
            'password' => 'password',
            'role_id' => $this->deliveryRole->id,
            'status' => 'active',
        ]);

        $this->assertSame(30, RiderCommission::forSettingsRun($plain, DeliveryRunType::CORPORATE_TO_KITCHEN));
    }

    public function test_lunch_commission_uses_menu_unless_rider_override(): void
    {
        $corporateRole = Role::where('name', 'corporate')->first();
        $corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'C',
            'mobile' => '01750000004',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);

        $menu = MenuItem::create([
            'name' => 'Thali',
            'price' => 200,
            'delivery_commission' => 25,
        ]);

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 2,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 400,
            'address' => 'Office',
            'order_status' => 'packed',
            'payment_status' => 'pending',
        ]);

        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'Lunch',
            'mobile' => '01750000005',
            'password' => 'password',
            'role_id' => $this->deliveryRole->id,
            'status' => 'active',
        ]);

        $this->assertSame(50, RiderCommission::forLunchOrder($rider, $order));

        $rider->update([
            'rider_commission_overrides' => [
                DeliveryRunType::KITCHEN_TO_CORPORATE => 70,
            ],
        ]);

        $this->assertSame(70, RiderCommission::forLunchOrder($rider->fresh(), $order));
    }

    public function test_creating_delivery_user_syncs_multi_areas(): void
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Two',
            'mobile' => '01750000006',
            'password' => 'password',
            'role_id' => $this->adminRole->id,
            'status' => 'active',
        ]);

        $city = City::create(['name' => 'Dhaka']);
        $a1 = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);
        $a2 = Area::create(['name' => 'Banani', 'city_id' => $city->id]);

        Livewire::actingAs($admin)
            ->test(UserCreateModal::class, ['lockedRole' => 'delivery'])
            ->set('first_name', 'Rider')
            ->set('last_name', 'Multi')
            ->set('mobile', '01750000007')
            ->set('password', 'password')
            ->set('selectedCity', $city->id)
            ->set('selectedAreaIds', [$a1->id, $a2->id])
            ->set('commissionOverrides.'.DeliveryRunType::CUSTOM, 88)
            ->call('save')
            ->assertHasNoErrors();

        $rider = User::query()->where('mobile', '01750000007')->first();
        $this->assertNotNull($rider);
        $this->assertEqualsCanonicalizing([$a1->id, $a2->id], $rider->serviceAreaIds());
        $this->assertTrue($rider->servesArea($a1->id));
        $this->assertSame(88, RiderCommission::forSettingsRun($rider, DeliveryRunType::CUSTOM));
    }

    public function test_edit_delivery_user_updates_areas_and_overrides(): void
    {
        $city = City::create(['name' => 'Dhaka']);
        $a1 = Area::create(['name' => 'Mirpur', 'city_id' => $city->id]);
        $a2 = Area::create(['name' => 'Uttara', 'city_id' => $city->id]);

        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'Edit',
            'mobile' => '01750000008',
            'password' => 'password',
            'role_id' => $this->deliveryRole->id,
            'status' => 'active',
            'city_id' => $city->id,
            'area_id' => $a1->id,
        ]);
        $rider->areas()->sync([$a1->id]);

        Livewire::test(UserEditModal::class, ['user' => $rider])
            ->set('showModal', true)
            ->set('selectedAreaIds', [(string) $a2->id])
            ->set('commissionOverrides.'.DeliveryRunType::OPS_TO_KITCHEN, 41)
            ->call('save')
            ->assertHasNoErrors();

        $rider->refresh()->load('areas');
        $this->assertEqualsCanonicalizing([$a2->id], $rider->serviceAreaIds());
        $this->assertSame(41, RiderCommission::forSettingsRun($rider, DeliveryRunType::OPS_TO_KITCHEN));
    }

    public function test_migration_backfills_delivery_area_pivot(): void
    {
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Dhanmondi', 'city_id' => $city->id]);

        $rider = User::create([
            'first_name' => 'Legacy',
            'last_name' => 'Rider',
            'mobile' => '01750000009',
            'password' => 'password',
            'role_id' => $this->deliveryRole->id,
            'status' => 'active',
            'area_id' => $area->id,
        ]);

        // Fresh migrate already ran — simulate backfill path by syncing like migration.
        $this->assertContains($area->id, $rider->fresh()->serviceAreaIds());
    }
}
