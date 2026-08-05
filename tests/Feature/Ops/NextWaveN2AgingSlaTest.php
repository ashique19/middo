<?php

namespace Tests\Feature\Ops;

use App\Livewire\Operation\CoverageBoard;
use App\Livewire\Operation\RidersBoard;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use App\Support\OpsAreaCoverage;
use App\Support\OpsRiderBoard;
use App\Support\RiderAcceptSla;
use App\Support\RiderShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NextWaveN2AgingSlaTest extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected User $kitchen;

    protected User $rider;

    protected User $corporate;

    protected MenuItem $menu;

    protected City $city;

    protected Area $area;

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

        $this->ops = User::create([
            'first_name' => 'Ops', 'last_name' => 'N2', 'mobile' => '01992000001',
            'password' => 'password', 'role_id' => $opsRole->id, 'status' => 'active',
        ]);
        $this->kitchen = User::create([
            'first_name' => 'Kitchen', 'last_name' => 'N2', 'mobile' => '01992000002',
            'password' => 'password', 'role_id' => $kitchenRole->id, 'status' => 'active',
            'area_id' => $this->area->id, 'city_id' => $this->city->id,
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider', 'last_name' => 'N2', 'mobile' => '01992000003',
            'password' => 'password', 'role_id' => $deliveryRole->id, 'status' => 'active',
            'area_id' => $this->area->id, 'city_id' => $this->city->id,
            'rider_shift_status' => RiderShift::ON,
        ]);
        $this->rider->areas()->sync([$this->area->id]);
        $this->corporate = User::create([
            'first_name' => 'Corp', 'last_name' => 'N2', 'mobile' => '01992000004',
            'password' => 'password', 'role_id' => $corporateRole->id, 'status' => 'active',
        ]);
        $this->menu = MenuItem::create([
            'name' => 'N2 Thali', 'price' => 200,
            'kitchen_commission' => 50, 'delivery_commission' => 40,
        ]);
    }

    protected function makeUnclaimedPacked(Carbon $deliveryAt, Carbon $dispatchedAt): Order
    {
        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => $deliveryAt->toDateString(),
            'delivery_time' => $deliveryAt->format('g:i A'),
            'total_amount' => 200,
            'address' => 'HQ',
            'area_id' => $this->area->id,
            'order_status' => 'packed',
            'payment_status' => 'pending',
            'dispatched_at' => $dispatchedAt,
            'delivery_rider_id' => null,
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-N2-'.$order->id,
            'menu_id' => $this->menu->id,
            'delivery_date' => $order->delivery_date,
            'kitchen_id' => $this->kitchen->id,
            'area_id' => $this->area->id,
        ]);
        $group->orders()->attach($order->id);

        return $order->fresh();
    }

    public function test_rider_accept_sla_marks_overdue_and_aging(): void
    {
        // App TZ is UTC; keep dispatched_at relative to now() so absolute instants match.
        Carbon::setTestNow(Carbon::parse('2026-08-05 07:00:00', 'UTC')); // 13:00 Asia/Dhaka
        $now = now('Asia/Dhaka');

        $overdue = $this->makeUnclaimedPacked(
            Carbon::parse('2026-08-05 12:00 PM', 'Asia/Dhaka'),
            now()->subHours(3)
        );
        $sla = RiderAcceptSla::statusPayload($overdue->fresh(), $now);
        $this->assertSame('overdue', $sla['state']);
        $this->assertTrue($sla['overdue']);
        $this->assertTrue($sla['aging']);
        $this->assertSame(0, $sla['priority']);

        $aging = $this->makeUnclaimedPacked(
            Carbon::parse('2026-08-05 06:00 PM', 'Asia/Dhaka'),
            now()->subMinutes(90)
        );
        $agingSla = RiderAcceptSla::statusPayload($aging->fresh(), $now);
        $this->assertSame('aging', $agingSla['state'], json_encode($agingSla));
        $this->assertGreaterThanOrEqual(30, $agingSla['minutes_waiting']);
        $this->assertSame(2, $agingSla['priority']);
    }

    public function test_awaiting_accept_sorts_overdue_first_and_counts_aging(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 07:00:00', 'UTC'));
        $now = now('Asia/Dhaka');

        $ok = $this->makeUnclaimedPacked(
            Carbon::parse('2026-08-05 06:00 PM', 'Asia/Dhaka'),
            now()->subMinutes(5)
        );
        $overdue = $this->makeUnclaimedPacked(
            Carbon::parse('2026-08-05 12:00 PM', 'Asia/Dhaka'),
            now()->subHours(3)
        );

        $awaiting = OpsRiderBoard::awaitingAccept($now);
        $this->assertCount(2, $awaiting);
        $this->assertSame($overdue->id, $awaiting[0]['id']);
        $this->assertSame($ok->id, $awaiting[1]['id']);

        $counts = OpsRiderBoard::counts();
        $this->assertSame(2, $counts['awaiting']);
        $this->assertGreaterThanOrEqual(1, $counts['awaiting_aging']);
        $this->assertSame(1, $counts['awaiting_overdue']);

        Livewire::actingAs($this->ops)
            ->test(RidersBoard::class)
            ->set('tab', 'awaiting')
            ->assertSee('Aging / overdue')
            ->assertSee('#'.$overdue->id)
            ->assertSee('Past delivery');
    }

    public function test_coverage_board_surfaces_aging_unclaimed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 07:00:00', 'UTC'));
        $now = now('Asia/Dhaka');

        $this->makeUnclaimedPacked(
            Carbon::parse('2026-08-05 12:00 PM', 'Asia/Dhaka'),
            now()->subHours(3)
        );

        $rows = OpsAreaCoverage::rows('2026-08-05', $now);
        $banani = collect($rows)->firstWhere('area_id', $this->area->id);
        $this->assertNotNull($banani);
        $this->assertSame(1, $banani['unclaimed_packed']);
        $this->assertSame(1, $banani['aging_unclaimed']);
        $this->assertTrue($banani['gap']);
        $this->assertStringContainsString('aging', (string) $banani['gap_reason']);
        $this->assertSame(1, $banani['riders_on_shift']);

        Livewire::actingAs($this->ops)
            ->test(CoverageBoard::class)
            ->set('deliveryDate', '2026-08-05')
            ->assertSee('Aging unclaimed')
            ->assertSee('Banani')
            ->assertSee('packed unclaimed aging');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
