<?php

namespace Tests\Feature\Delivery;

use App\Livewire\Delivery\CustomRuns as DeliveryCustomRuns;
use App\Livewire\Operation\CustomRuns as OpsCustomRuns;
use App\Models\Area;
use App\Models\City;
use App\Models\CustomRun;
use App\Models\MiddoOperatingCost;
use App\Models\Role;
use App\Models\StaffAlert;
use App\Models\User;
use App\Support\DeliveryRunType;
use App\Support\MiddoSettings;
use App\Support\RiderAccountLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomRunsR2bTest extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected User $rider;

    protected User $otherRider;

    protected Area $gulshan;

    protected Area $mirpur;

    protected function setUp(): void
    {
        parent::setUp();

        $opsRole = Role::create(['name' => 'operation']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        $city = City::create(['name' => 'Dhaka']);
        $this->gulshan = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);
        $this->mirpur = Area::create(['name' => 'Mirpur', 'city_id' => $city->id]);

        $this->ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'R2b',
            'mobile' => '01970000001',
            'password' => 'password',
            'role_id' => $opsRole->id,
            'status' => 'active',
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'Gulshan',
            'mobile' => '01970000002',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
            'area_id' => $this->gulshan->id,
        ]);
        $this->rider->areas()->sync([$this->gulshan->id]);

        $this->otherRider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'Mirpur',
            'mobile' => '01970000003',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
            'area_id' => $this->mirpur->id,
        ]);
        $this->otherRider->areas()->sync([$this->mirpur->id]);

        MiddoSettings::updateMealAndKitchenDefaults([
            'delivery_commissions' => [
                DeliveryRunType::CUSTOM => 55,
            ],
        ]);
    }

    public function test_ops_creates_area_run_and_rider_start_credits_wallet(): void
    {
        Livewire::actingAs($this->ops)
            ->test(OpsCustomRuns::class)
            ->set('fromLabel', 'Warehouse')
            ->set('toLabel', 'Gulshan Kitchen')
            ->set('areaId', $this->gulshan->id)
            ->set('commissionAmount', 55)
            ->call('createRun')
            ->assertSet('errorMessage', '');

        $run = CustomRun::query()->firstOrFail();
        $this->assertSame(CustomRun::STATUS_PENDING, $run->status);
        $this->assertTrue(StaffAlert::query()
            ->where('user_id', $this->rider->id)
            ->where('type', StaffAlert::TYPE_CUSTOM_RUN)
            ->where('meta->custom_run_id', $run->id)
            ->exists());
        $this->assertFalse(StaffAlert::query()
            ->where('user_id', $this->otherRider->id)
            ->where('type', StaffAlert::TYPE_CUSTOM_RUN)
            ->exists());

        Livewire::actingAs($this->otherRider)
            ->test(DeliveryCustomRuns::class)
            ->assertDontSee('Warehouse → Gulshan Kitchen', false);

        Livewire::actingAs($this->rider)
            ->test(DeliveryCustomRuns::class)
            ->assertSee('Warehouse → Gulshan Kitchen', false)
            ->call('startRun', $run->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(55, RiderAccountLedger::balance($this->rider->id));
        $this->assertSame(1, MiddoOperatingCost::query()
            ->where('run_type', DeliveryRunType::CUSTOM)
            ->where('reference_type', CustomRun::class)
            ->where('reference_id', $run->id)
            ->count());
        $this->assertSame(CustomRun::STATUS_STARTED, $run->fresh()->status);
        $this->assertSame($this->rider->id, $run->fresh()->rider_user_id);

        Livewire::actingAs($this->rider)
            ->test(DeliveryCustomRuns::class)
            ->call('completeRun', $run->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(CustomRun::STATUS_COMPLETED, $run->fresh()->status);
        $this->assertSame(55, RiderAccountLedger::balance($this->rider->id));
    }

    public function test_assigned_rider_only_and_idempotent_start(): void
    {
        Livewire::actingAs($this->ops)
            ->test(OpsCustomRuns::class)
            ->set('fromLabel', 'A')
            ->set('toLabel', 'B')
            ->set('areaId', $this->gulshan->id)
            ->set('riderUserId', $this->rider->id)
            ->set('commissionAmount', 40)
            ->call('createRun')
            ->assertSet('errorMessage', '');

        $run = CustomRun::query()->firstOrFail();

        Livewire::actingAs($this->otherRider)
            ->test(DeliveryCustomRuns::class)
            ->call('startRun', $run->id)
            ->assertSet('errorMessage', fn ($m) => is_string($m) && str_contains($m, 'another rider'));

        Livewire::actingAs($this->rider)
            ->test(DeliveryCustomRuns::class)
            ->call('startRun', $run->id)
            ->assertSet('errorMessage', null)
            ->call('startRun', $run->id)
            ->assertSet('errorMessage', fn ($m) => is_string($m) && str_contains($m, 'no longer available'));

        $this->assertSame(40, RiderAccountLedger::balance($this->rider->id));
        $this->assertSame(1, MiddoOperatingCost::query()->where('reference_id', $run->id)->count());
    }

    public function test_ops_can_cancel_pending_run(): void
    {
        Livewire::actingAs($this->ops)
            ->test(OpsCustomRuns::class)
            ->set('fromLabel', 'X')
            ->set('toLabel', 'Y')
            ->set('commissionAmount', 10)
            ->call('createRun');

        $run = CustomRun::query()->firstOrFail();

        Livewire::actingAs($this->ops)
            ->test(OpsCustomRuns::class)
            ->call('cancelRun', $run->id)
            ->assertSet('errorMessage', '');

        $this->assertSame(CustomRun::STATUS_CANCELLED, $run->fresh()->status);
    }
}
