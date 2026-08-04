<?php

namespace Tests\Feature\Ops;

use App\Livewire\Operation\CoverageBoard;
use App\Livewire\Shared\AreasAdmin;
use App\Livewire\Shared\CorporateShow;
use App\Livewire\Shared\StaffProfileShow;
use App\Models\Area;
use App\Models\City;
use App\Models\KitchenHour;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\OpsAreaCoverage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpsCoveragePolicyO8Test extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected User $kitchen;

    protected User $rider;

    protected User $corporate;

    protected City $city;

    protected Area $area;

    protected Area $area2;

    protected function setUp(): void
    {
        parent::setUp();

        $opsRole = Role::create(['name' => 'operation']);
        Role::create(['name' => 'admin']);
        $kitchenRole = Role::create(['name' => 'kitchen']);
        $corporateRole = Role::create(['name' => 'corporate']);
        $deliveryRole = Role::create(['name' => 'delivery']);

        $this->city = City::create(['name' => 'Dhaka']);
        $this->area = Area::create(['name' => 'Banani', 'city_id' => $this->city->id]);
        $this->area2 = Area::create(['name' => 'Gulshan', 'city_id' => $this->city->id]);

        $this->ops = User::create([
            'first_name' => 'Ops', 'last_name' => 'O8', 'mobile' => '01912000001',
            'password' => 'password', 'role_id' => $opsRole->id, 'status' => 'active',
        ]);
        $this->kitchen = User::create([
            'first_name' => 'Kitchen', 'last_name' => 'O8', 'mobile' => '01912000002',
            'password' => 'password', 'role_id' => $kitchenRole->id, 'status' => 'active',
            'kitchen_tier' => 'silver', 'allowed_open_groups' => 2,
            'area_id' => $this->area->id, 'city_id' => $this->city->id,
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider', 'last_name' => 'O8', 'mobile' => '01912000003',
            'password' => 'password', 'role_id' => $deliveryRole->id, 'status' => 'active',
            'area_id' => $this->area->id, 'city_id' => $this->city->id,
        ]);
        $this->rider->areas()->sync([$this->area->id]);

        $this->corporate = User::create([
            'first_name' => 'Corp', 'last_name' => 'O8', 'mobile' => '01912000004',
            'password' => 'password', 'role_id' => $corporateRole->id, 'status' => 'active',
            'balance' => 500,
            'company_name' => 'O8 Foods',
        ]);
    }

    public function test_areas_admin_creates_city_and_area(): void
    {
        Livewire::actingAs($this->ops)
            ->test(AreasAdmin::class)
            ->set('newCityName', 'Chittagong')
            ->call('createCity')
            ->assertSee('Chittagong')
            ->set('newAreaCityId', City::query()->where('name', 'Chittagong')->value('id'))
            ->set('newAreaName', 'Agrabad')
            ->call('createArea')
            ->assertSee('Agrabad');

        $this->assertDatabaseHas('areas', ['name' => 'Agrabad']);
    }

    public function test_coverage_board_flags_gap_when_orders_lack_riders(): void
    {
        $kitchenRoleId = Role::query()->where('name', 'kitchen')->value('id');
        User::create([
            'first_name' => 'Kitchen', 'last_name' => 'Gulshan', 'mobile' => '01912000005',
            'password' => 'password', 'role_id' => $kitchenRoleId, 'status' => 'active',
            'kitchen_tier' => 'silver', 'area_id' => $this->area2->id, 'city_id' => $this->city->id,
        ]);

        $menu = MenuItem::create([
            'name' => 'O8 Thali', 'price' => 200,
            'kitchen_commission' => 50, 'delivery_commission' => 40,
        ]);

        Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 3,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 600,
            'address' => 'HQ',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'area_id' => $this->area2->id,
        ]);

        $rows = OpsAreaCoverage::rows(now('Asia/Dhaka')->toDateString());
        $gulshan = collect($rows)->firstWhere('area_id', $this->area2->id);

        $this->assertNotNull($gulshan);
        $this->assertSame(3, $gulshan['quantity']);
        $this->assertSame(1, $gulshan['kitchens']);
        $this->assertSame(0, $gulshan['riders']);
        $this->assertTrue($gulshan['gap']);
        $this->assertStringContainsString('rider', strtolower((string) $gulshan['gap_reason']));

        Livewire::actingAs($this->ops)
            ->test(CoverageBoard::class)
            ->assertSee('Gulshan')
            ->assertSee('Coverage gaps');
    }

    public function test_ops_can_edit_kitchen_capacity_and_hours(): void
    {
        Livewire::actingAs($this->ops)
            ->test(StaffProfileShow::class, ['kitchen' => $this->kitchen])
            ->set('edit_kitchen_tier', 'gold')
            ->set('edit_allowed_open_groups', 5)
            ->call('saveKitchenCapacity')
            ->assertSee('Kitchen tier and allowed open groups updated');

        $this->assertSame('gold', $this->kitchen->fresh()->kitchen_tier);
        $this->assertSame(5, (int) $this->kitchen->fresh()->allowed_open_groups);

        Livewire::actingAs($this->ops)
            ->test(StaffProfileShow::class, ['kitchen' => $this->kitchen])
            ->set('hours.1.is_closed', false)
            ->set('hours.1.opens_at', '09:00')
            ->set('hours.1.closes_at', '21:00')
            ->call('saveKitchenHours')
            ->assertSet('hoursStatusMessage', 'Weekly hours saved.');

        $monday = KitchenHour::query()
            ->where('user_id', $this->kitchen->id)
            ->where('day_of_week', 1)
            ->first();
        $this->assertNotNull($monday);
        $this->assertFalse($monday->is_closed);
        $this->assertStringStartsWith('09:00', (string) $monday->opens_at);
    }

    public function test_ops_can_attach_rider_areas(): void
    {
        Livewire::actingAs($this->ops)
            ->test(StaffProfileShow::class, ['delivery' => $this->rider])
            ->set('selectedAreaIds', [(string) $this->area->id, (string) $this->area2->id])
            ->call('saveRiderAreas')
            ->assertSet('areasStatusMessage', 'Service areas updated.');

        $this->rider->refresh()->load('areas');
        $this->assertEqualsCanonicalizing(
            [$this->area->id, $this->area2->id],
            $this->rider->areas->pluck('id')->all()
        );
        $this->assertTrue($this->rider->servesArea($this->area2->id));
    }

    public function test_ops_can_adjust_corporate_wallet(): void
    {
        Livewire::actingAs($this->ops)
            ->test(CorporateShow::class, ['corporate' => $this->corporate])
            ->set('adjustDirection', 'credit')
            ->set('adjustAmount', '150')
            ->set('adjustReason', 'Goodwill for late delivery')
            ->call('postWalletAdjustment')
            ->assertSee('Credit ৳150 posted');

        $this->assertSame(650, (int) $this->corporate->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->corporate->id,
            'type' => WalletTransaction::TYPE_ADJUSTMENT,
            'amount' => 150,
        ]);

        Livewire::actingAs($this->ops)
            ->test(CorporateShow::class, ['corporate' => $this->corporate->fresh()])
            ->set('adjustDirection', 'debit')
            ->set('adjustAmount', '50')
            ->set('adjustReason', 'Correction')
            ->call('postWalletAdjustment')
            ->assertSee('Debit ৳50 posted');

        $this->assertSame(600, (int) $this->corporate->fresh()->balance);
    }
}
