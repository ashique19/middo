<?php

namespace Tests\Feature;

use App\Livewire\Operation\AssignKitchenModal;
use App\Models\Area;
use App\Models\City;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderLog;
use App\Models\PackageSubscription;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderAudit;
use App\Support\OrderCutoff;
use App\Support\OrderGroupManager;
use App\Support\PackageBilling;
use App\Support\PackageSubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private Role $corporateRole;

    private Role $operationRole;

    private Role $kitchenRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->corporateRole = Role::create(['name' => 'corporate']);
        $this->operationRole = Role::create(['name' => 'operation']);
        $this->kitchenRole = Role::create(['name' => 'kitchen']);
    }

    private function makeCorporate(): User
    {
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        return User::create([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'mobile' => '01310123452',
            'password' => '12345678',
            'role_id' => $this->corporateRole->id,
            'status' => 'active',
            'balance' => 50000,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'address' => 'House 1',
        ]);
    }

    public function test_menu_and_package_orders_share_order_logs_with_source_snapshot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        $user = $this->makeCorporate();
        $menu = MenuItem::create([
            'name' => 'Thali',
            'summary' => 'Lunch',
            'price' => 150,
            'is_featured' => true,
            'display_order' => 1,
        ]);

        $menuOrder = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 2,
            'delivery_date' => now(OrderCutoff::timezone())->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 300,
            'amount_paid' => 300,
            'address' => 'House 1',
            'receiver_name' => 'Corp',
            'receiver_mobile' => $user->mobile,
            'area_id' => $user->area_id,
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'balance',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $created = OrderLog::query()->where('order_id', $menuOrder->id)->where('event', 'created')->first();
        $this->assertNotNull($created);
        $this->assertSame('menu', $created->metadata['snapshot']['source'] ?? null);
        $this->assertNull($created->metadata['snapshot']['package_subscription_id'] ?? null);

        $start = now(OrderCutoff::timezone())->startOfMonth();
        $package = MealPackage::create([
            'name' => 'Classic',
            'summary' => 'Plan',
            'price_per_day' => 79,
            'diet_tag' => 'classic',
            'duration_days' => 30,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->toDateString(),
            'status' => MealPackage::STATUS_PUBLISHED,
            'display_order' => 1,
        ]);
        $workingDays = PackageBilling::availableDatesInMonth('2026-08', [5, 6])->count();
        $result = app(PackageSubscriptionService::class)->subscribe(
            $user,
            $package,
            1,
            [5, 6],
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            '2026-08',
            'Corp',
            $user->mobile,
            'House 1',
            (int) $user->city_id,
            (int) $user->area_id,
            '12:00 PM',
            'balance'
        );

        $ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'User',
            'mobile' => '01310123900',
            'password' => '12345678',
            'role_id' => $this->operationRole->id,
            'status' => 'active',
        ]);

        $scheduled = app(PackageSubscriptionService::class)->assignSchedule(
            $ops,
            $result['subscription'],
            PackageBilling::availableDatesInMonth('2026-08', [5, 6])
                ->map(fn ($date) => ['date' => $date, 'menu_item_id' => $menu->id])
                ->values()
                ->all()
        );

        $packageOrder = $scheduled['orders']->first();
        $this->assertInstanceOf(Order::class, $packageOrder);

        $pkgCreated = OrderLog::query()
            ->where('order_id', $packageOrder->id)
            ->where('event', 'created')
            ->first();
        $this->assertNotNull($pkgCreated);
        $this->assertSame('package', $pkgCreated->metadata['snapshot']['source'] ?? null);
        $this->assertSame($result['subscription']->id, $pkgCreated->metadata['snapshot']['package_subscription_id'] ?? null);

        $this->assertStringContainsString('Package order placed', OrderAudit::description($pkgCreated->toArray()));

        Carbon::setTestNow();
    }

    public function test_package_skip_and_kitchen_forward_are_audited(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        $user = $this->makeCorporate();
        $menu = MenuItem::create([
            'name' => 'Thali',
            'summary' => 'Lunch',
            'price' => 150,
            'is_featured' => true,
            'display_order' => 1,
        ]);
        $start = now(OrderCutoff::timezone())->startOfMonth();
        $package = MealPackage::create([
            'name' => 'Classic',
            'summary' => 'Plan',
            'price_per_day' => 79,
            'diet_tag' => 'classic',
            'duration_days' => 30,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->toDateString(),
            'status' => MealPackage::STATUS_PUBLISHED,
            'display_order' => 1,
        ]);
        $workingDays = PackageBilling::availableDatesInMonth('2026-08', [5, 6])->count();
        $result = app(PackageSubscriptionService::class)->subscribe(
            $user,
            $package,
            1,
            [5, 6],
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            '2026-08',
            'Corp',
            $user->mobile,
            'House 1',
            (int) $user->city_id,
            (int) $user->area_id,
            '12:00 PM',
            'balance'
        );

        $ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'User',
            'mobile' => '01310123901',
            'password' => '12345678',
            'role_id' => $this->operationRole->id,
            'status' => 'active',
        ]);
        $kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'One',
            'mobile' => '01310123902',
            'password' => '12345678',
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
        ]);

        $scheduled = app(PackageSubscriptionService::class)->assignSchedule(
            $ops,
            $result['subscription'],
            PackageBilling::availableDatesInMonth('2026-08', [5, 6])
                ->map(fn ($date) => ['date' => $date, 'menu_item_id' => $menu->id])
                ->values()
                ->all()
        );

        $order = $scheduled['orders']->first();
        app(PackageSubscriptionService::class)->skipDay($user, $order);

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $order->id,
            'event' => 'skipped',
        ]);

        $orderB = $scheduled['orders']->skip(1)->first();
        $orderC = $scheduled['orders']->skip(2)->first();
        $this->assertNotNull($orderB);
        $this->assertNotNull($orderC);

        // Ensure same delivery date for grouping.
        $orderC->update([
            'delivery_date' => $orderB->delivery_date->toDateString(),
            'updated_by' => $ops->id,
        ]);

        $this->actingAs($ops);
        $group = app(OrderGroupManager::class)->createManualGroupFromOrders($orderB->fresh(), $orderC->fresh(), $ops->id);

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $orderB->id,
            'event' => 'grouped',
        ]);

        Livewire::actingAs($ops)
            ->test(AssignKitchenModal::class)
            ->call('openModal', $group->id)
            ->set('selectedKitchenId', $kitchen->id)
            ->call('save');

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $orderB->id,
            'event' => 'forwarded_to_kitchen',
        ]);
        $this->assertDatabaseHas('order_logs', [
            'order_id' => $orderB->id,
            'event' => 'kitchen_accepted',
        ]);

        Carbon::setTestNow();
    }
}
