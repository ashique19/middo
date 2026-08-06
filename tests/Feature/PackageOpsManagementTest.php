<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\City;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroupOrder;
use App\Models\PackageSubscription;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderCutoff;
use App\Support\PackageBilling;
use App\Support\PackageOrderPresenter;
use App\Support\PackageSubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PackageOpsManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $corporateRole;

    private Role $adminRole;

    private Role $operationRole;

    private Role $kitchenRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->corporateRole = Role::create(['name' => 'corporate']);
        $this->adminRole = Role::create(['name' => 'admin']);
        $this->operationRole = Role::create(['name' => 'operation']);
        $this->kitchenRole = Role::create(['name' => 'kitchen']);
    }

    private function makeUser(Role $role, array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => ucfirst($role->name),
            'last_name' => 'User',
            'company_name' => $role->name === 'corporate' ? 'Middo Demo Corp' : null,
            'mobile' => '01'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 50000,
            'address' => 'House 12, Road 5',
        ], $overrides));
    }

    private function makeCityArea(): array
    {
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        return [$city, $area];
    }

    private function makeMenuItem(string $name = 'Office Thali', int $price = 150): MenuItem
    {
        return MenuItem::create([
            'name' => $name,
            'summary' => 'Daily lunch',
            'price' => $price,
            'is_featured' => true,
            'display_order' => 1,
        ]);
    }

    private function makePublishedPackage(MenuItem $menuItem, int $pricePerDay = 79, int $days = 10): MealPackage
    {
        $start = now(OrderCutoff::timezone())->startOfMonth();

        return MealPackage::create([
            'name' => '৳'.$pricePerDay.'/day Classic',
            'summary' => 'Month plan',
            'price_per_day' => $pricePerDay,
            'diet_tag' => 'classic',
            'duration_days' => $days,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->toDateString(),
            'status' => MealPackage::STATUS_PUBLISHED,
            'display_order' => 1,
        ]);
    }

    private function workingDays(string $month = '2026-08', array $omitted = [5, 6]): int
    {
        return PackageBilling::availableDatesInMonth($month, $omitted)->count();
    }

    private function subscribeCorporate(
        User $user,
        MealPackage $package,
        City $city,
        Area $area,
        MenuItem $menu,
        ?int $dayCount = null,
        string $month = '2026-08'
    ): array {
        $dayCount ??= $this->workingDays($month);

        return app(PackageSubscriptionService::class)->subscribe(
            $user,
            $package,
            1,
            [5, 6],
            [['menu_item_id' => $menu->id, 'day_count' => $dayCount]],
            $month,
            'Corporate User',
            $user->mobile,
            'House 12, Road 5',
            $city->id,
            $area->id,
            '12:00 PM',
            'balance'
        );
    }

    private function scheduleSubscription(User $ops, PackageSubscription $subscription, MenuItem $menu): array
    {
        $available = PackageBilling::availableDatesInMonth(
            (string) $subscription->target_month,
            $subscription->omitted_weekdays ?? []
        );

        $assignments = $available->map(fn ($date) => [
            'date' => $date,
            'menu_item_id' => $menu->id,
        ])->values()->all();

        return app(PackageSubscriptionService::class)->assignSchedule($ops, $subscription, $assignments);
    }

    public function test_package_order_presenter_and_auto_group_visibility(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123999',
        ]);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123899']);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 79, 8);

        $result = $this->subscribeCorporate($user, $package, $city, $area, $menu);
        $scheduled = $this->scheduleSubscription($ops, $result['subscription'], $menu);
        $order = $scheduled['orders']->first();

        $fields = PackageOrderPresenter::fields($order->fresh('packageSubscription.package'));
        $this->assertTrue($fields['is_package']);
        $this->assertSame($package->name, $fields['package_name']);
        $this->assertNotNull($fields['package_subscription_id']);
        $this->assertTrue(OrderGroupOrder::query()->where('order_id', $order->id)->exists());

        Carbon::setTestNow();
    }

    public function test_staff_skip_day_ungroups_and_refunds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123998',
            'balance' => 50000,
        ]);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123997', 'balance' => 0]);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 100, 8);
        $workingDays = $this->workingDays('2026-08');
        $quote = PackageBilling::quoteFromSelections(
            $package,
            1,
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            [5, 6],
            '2026-08'
        );

        $result = $this->subscribeCorporate($user, $package, $city, $area, $menu);
        $scheduled = $this->scheduleSubscription($ops, $result['subscription'], $menu);
        $order = $scheduled['orders']->first();
        $this->assertTrue(OrderGroupOrder::query()->where('order_id', $order->id)->exists());

        $refund = (int) $order->amount_paid;
        app(PackageSubscriptionService::class)->skipDayAsStaff($ops, $order, 'Ops cancelled — customer request');

        $order->refresh();
        $user->refresh();
        $this->assertSame('cancelled', $order->order_status);
        $this->assertFalse(OrderGroupOrder::query()->where('order_id', $order->id)->exists());
        $this->assertSame(50000 - $quote['total_amount'] + $refund, (int) $user->balance);

        Carbon::setTestNow();
    }

    public function test_cancel_remaining_and_swap_menu(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123996',
            'balance' => 50000,
        ]);
        $admin = $this->makeUser($this->adminRole, ['mobile' => '01310123995']);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123895']);
        $menuA = $this->makeMenuItem('Menu A');
        $menuB = $this->makeMenuItem('Menu B', 180);
        $package = $this->makePublishedPackage($menuA, 79, 8);

        $result = $this->subscribeCorporate($user, $package, $city, $area, $menuA);
        $scheduled = $this->scheduleSubscription($ops, $result['subscription'], $menuA);
        $subscription = $scheduled['subscription'];
        $order = $scheduled['orders']->first();

        $oldGroupId = OrderGroupOrder::query()->where('order_id', $order->id)->value('order_group_id');
        $this->assertNotNull($oldGroupId);
        $oldGroup = \App\Models\OrderGroup::query()->findOrFail($oldGroupId);
        $this->assertSame($menuA->id, (int) $oldGroup->menu_id);

        $swapped = app(PackageSubscriptionService::class)->swapDayMenu($admin, $order, $menuB->id);
        $this->assertSame($menuB->id, (int) $swapped->menu_item_id);
        $this->assertTrue(OrderGroupOrder::query()->where('order_id', $swapped->id)->exists());

        $newGroupId = OrderGroupOrder::query()->where('order_id', $swapped->id)->value('order_group_id');
        $this->assertNotNull($newGroupId);
        $this->assertNotSame((int) $oldGroupId, (int) $newGroupId);
        $newGroup = \App\Models\OrderGroup::query()->findOrFail($newGroupId);
        $this->assertSame($menuB->id, (int) $newGroup->menu_id);
        $this->assertSame($order->delivery_date->toDateString(), $newGroup->delivery_date->toDateString());
        $this->assertSame((int) $order->area_id, (int) $newGroup->area_id);
        $this->assertFalse(OrderGroupOrder::query()->where('order_group_id', $oldGroupId)->where('order_id', $swapped->id)->exists());

        $beforeBalance = (int) $user->fresh()->balance;
        $cancel = app(PackageSubscriptionService::class)->cancelRemaining($admin, $subscription->fresh());
        $this->assertGreaterThan(0, $cancel['cancelled_orders']);
        $this->assertSame(PackageSubscription::STATUS_CANCELLED, $cancel['subscription']->status);
        $this->assertSame($beforeBalance + $cancel['refunded_amount'], (int) $user->fresh()->balance);

        Carbon::setTestNow();
    }

    public function test_admin_and_operation_can_open_new_package_pages(): void
    {
        $admin = $this->makeUser($this->adminRole, ['mobile' => '01310123994']);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123993']);
        $kitchen = $this->makeUser($this->kitchenRole, ['mobile' => '01310123992']);

        $this->actingAs($admin)->get(route('admin.subscriptions.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.packages.demand'))->assertOk();
        $this->actingAs($admin)->get(route('admin.packages.insights'))->assertOk();

        $this->actingAs($ops)->get(route('operation.subscriptions.index'))->assertOk();
        $this->actingAs($ops)->get(route('operation.packages.create'))->assertOk();
        $this->actingAs($ops)->get(route('operation.packages.demand'))->assertOk();

        $this->actingAs($kitchen)->get(route('kitchen.menus.today'))->assertOk();
    }

    public function test_operation_cannot_force_complete_but_admin_can(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123991',
        ]);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123990']);
        $admin = $this->makeUser($this->adminRole, ['mobile' => '01310123989']);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 79, 6);
        $subscription = $this->subscribeCorporate($user, $package, $city, $area, $menu)['subscription'];

        $this->expectException(\RuntimeException::class);
        app(PackageSubscriptionService::class)->forceComplete($ops, $subscription);

        Carbon::setTestNow();
    }

    public function test_admin_force_complete_subscription(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123988',
        ]);
        $admin = $this->makeUser($this->adminRole, ['mobile' => '01310123987']);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 79, 6);
        $subscription = $this->subscribeCorporate($user, $package, $city, $area, $menu)['subscription'];

        $completed = app(PackageSubscriptionService::class)->forceComplete($admin, $subscription);
        $this->assertSame(PackageSubscription::STATUS_COMPLETED, $completed->status);

        Carbon::setTestNow();
    }

    public function test_bulk_skip_date_and_clone_package_route(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123986',
        ]);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123985']);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 79, 8);
        $subscribed = $this->subscribeCorporate($user, $package, $city, $area, $menu);
        $scheduled = $this->scheduleSubscription($ops, $subscribed['subscription'], $menu);
        $subscription = $scheduled['subscription'];
        $firstDate = Order::query()
            ->where('package_subscription_id', $subscription->id)
            ->orderBy('delivery_date')
            ->value('delivery_date');

        $result = app(PackageSubscriptionService::class)->bulkSkipDate($ops, Carbon::parse($firstDate)->toDateString());
        $this->assertGreaterThan(0, $result['skipped']);

        $this->actingAs($ops)
            ->get(route('operation.subscriptions.show', $subscription->id))
            ->assertOk()
            ->assertSee('Subscription #'.$subscription->id);

        Carbon::setTestNow();
    }

    public function test_package_filter_on_active_orders_page(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123984',
        ]);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123983']);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 79, 6);
        $subscribed = $this->subscribeCorporate($user, $package, $city, $area, $menu);
        $this->scheduleSubscription($ops, $subscribed['subscription'], $menu);

        $this->actingAs($ops)
            ->get(route('operation.orders.active'))
            ->assertOk()
            ->assertSee('Package only');

        Carbon::setTestNow();
    }

    public function test_unconfirm_returns_day_to_schedule_and_writes_audit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123982',
            'balance' => 50000,
        ]);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123981']);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 100, 8);

        $result = $this->subscribeCorporate($user, $package, $city, $area, $menu);
        $scheduled = $this->scheduleSubscription($ops, $result['subscription'], $menu);
        $order = $scheduled['orders']->first();
        $balanceBefore = (int) $user->fresh()->balance;

        $assignEvent = \App\Models\PackageSubscriptionEvent::query()
            ->where('package_subscription_id', $result['subscription']->id)
            ->where('type', 'schedule_assigned')
            ->latest('id')
            ->first();
        $this->assertNotNull($assignEvent);
        $this->assertStringContainsString($menu->name, (string) $assignEvent->summary);
        $this->assertStringContainsString($order->delivery_date->format('M d, Y'), (string) $assignEvent->summary);

        app(PackageSubscriptionService::class)->unconfirmDay($ops, $order);

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertSame($balanceBefore, (int) $user->fresh()->balance);

        $subscription = $result['subscription']->fresh();
        $this->assertTrue($subscription->canReceiveScheduleAssignments());
        $this->assertGreaterThan(0, $subscription->remainingBillableDays());
        $this->assertDatabaseHas('package_subscription_events', [
            'package_subscription_id' => $subscription->id,
            'type' => 'day_unconfirmed',
            'created_by' => $ops->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_cancel_and_refund_then_reactivate_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123980',
            'balance' => 50000,
        ]);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123979']);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 100, 8);
        $workingDays = $this->workingDays('2026-08');
        $quote = PackageBilling::quoteFromSelections(
            $package,
            1,
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            [5, 6],
            '2026-08'
        );

        $result = $this->subscribeCorporate($user, $package, $city, $area, $menu);
        $scheduled = $this->scheduleSubscription($ops, $result['subscription'], $menu);
        $order = $scheduled['orders']->first();
        $refund = (int) $order->amount_paid;

        $skip = app(PackageSubscriptionService::class)->skipDayAsStaff($ops, $order, 'Holiday / office closed');
        $this->assertSame($refund, $skip['refunded_amount']);
        $this->assertSame('cancelled', $order->fresh()->order_status);
        $this->assertSame(50000 - $quote['total_amount'] + $refund, (int) $user->fresh()->balance);
        $subscriptionAfterCancel = $result['subscription']->fresh(['selections', 'orders']);
        $this->assertSame(1, $subscriptionAfterCancel->remainingBillableDays());
        $this->assertTrue($subscriptionAfterCancel->canReceiveScheduleAssignments());
        $this->assertSame(
            max(0, (int) $subscriptionAfterCancel->selections->first()->day_count - ($workingDays - 1)),
            (int) ($subscriptionAfterCancel->remainingSelectionCounts()[$menu->id] ?? 0)
        );
        $this->assertDatabaseHas('package_subscription_events', [
            'package_subscription_id' => $result['subscription']->id,
            'type' => 'day_cancelled',
        ]);
        $cancelEvent = \App\Models\PackageSubscriptionEvent::query()
            ->where('package_subscription_id', $result['subscription']->id)
            ->where('type', 'day_cancelled')
            ->latest('id')
            ->first();
        $this->assertNotNull($cancelEvent);
        $this->assertStringContainsString('Holiday / office closed', (string) $cancelEvent->summary);
        $this->assertStringContainsString('Menu untagged', (string) $cancelEvent->summary);
        $this->assertSame('Holiday / office closed', $cancelEvent->meta['reason'] ?? null);

        $this->actingAs($ops)
            ->get(route('operation.subscriptions.show', $result['subscription']->id))
            ->assertOk()
            ->assertSee('Untagged · cancelled')
            ->assertSee('Confirm delivery days')
            ->assertSee('Re-activate')
            ->assertSee('Package audit log')
            ->assertSee('day cancelled', false)
            ->assertSee('Holiday / office closed')
            ->assertSee(\Carbon\Carbon::parse($order->delivery_date)->format('M d, Y'));

        $reactivate = app(PackageSubscriptionService::class)->reactivateDay($ops, $order->fresh());
        $this->assertSame($refund, $reactivate['debited_amount']);
        $this->assertSame('pending', $order->fresh()->order_status);
        $this->assertSame(50000 - $quote['total_amount'], (int) $user->fresh()->balance);
        $this->assertTrue(OrderGroupOrder::query()->where('order_id', $order->id)->exists());
        $this->assertSame(0, $result['subscription']->fresh()->remainingBillableDays());
        $this->assertSame(PackageSubscription::SCHEDULE_SCHEDULED, $result['subscription']->fresh()->schedule_status);
        $this->assertDatabaseHas('package_subscription_events', [
            'package_subscription_id' => $result['subscription']->id,
            'type' => 'day_reactivated',
        ]);
        $reactivateEvent = \App\Models\PackageSubscriptionEvent::query()
            ->where('package_subscription_id', $result['subscription']->id)
            ->where('type', 'day_reactivated')
            ->latest('id')
            ->first();
        $this->assertNotNull($reactivateEvent);
        $this->assertStringContainsString('back to pending', (string) $reactivateEvent->summary);

        $this->actingAs($ops)
            ->get(route('operation.subscriptions.show', $result['subscription']->id))
            ->assertOk()
            ->assertSee('day reactivated', false)
            ->assertSee('back to pending');

        Carbon::setTestNow();
    }

    public function test_ops_subscription_show_ux_labels(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123978',
        ]);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123977']);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 79, 8);
        $subscribed = $this->subscribeCorporate($user, $package, $city, $area, $menu);
        $scheduled = $this->scheduleSubscription($ops, $subscribed['subscription'], $menu);

        $this->actingAs($ops)
            ->get(route('operation.subscriptions.show', $scheduled['subscription']->id))
            ->assertOk()
            ->assertSee('Update delivery details')
            ->assertSee('Cancel and Refund')
            ->assertSee('Package audit log')
            ->assertSee(route('operation.corporates.show', $user), false)
            ->assertSee('Wallet ৳')
            ->assertDontSee('Open active orders')
            ->assertDontSee('Cancel remaining')
            ->assertDontSee('Swap menu for a pending day')
            ->assertDontSee('Confirm selected days');

        Carbon::setTestNow();
    }

    public function test_cancel_unscheduled_day_refunds_and_can_reactivate(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123976',
            'balance' => 50000,
        ]);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123975']);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 100, 8);
        $workingDays = $this->workingDays('2026-08');
        $quote = PackageBilling::quoteFromSelections(
            $package,
            1,
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            [5, 6],
            '2026-08'
        );

        $result = $this->subscribeCorporate($user, $package, $city, $area, $menu);
        $subscription = $result['subscription'];
        $available = PackageBilling::availableDatesInMonth('2026-08', [5, 6]);
        $date = $available->first();

        $this->actingAs($ops)
            ->get(route('operation.subscriptions.show', $subscription->id))
            ->assertOk()
            ->assertSee('Confirm delivery days')
            ->assertSee('Cancel and Refund');

        $cancelled = app(PackageSubscriptionService::class)->cancelUnscheduledDay(
            $ops,
            $subscription,
            $date,
            $menu->id,
            'Customer closed that day'
        );

        $this->assertSame('cancelled', $cancelled['order']->order_status);
        $this->assertGreaterThan(0, $cancelled['refunded_amount']);
        $this->assertSame(50000 - $quote['total_amount'] + $cancelled['refunded_amount'], (int) $user->fresh()->balance);
        $this->assertSame($workingDays - 1, (int) $subscription->fresh()->billable_days);
        $this->assertSame($workingDays - 1, (int) $subscription->fresh()->selections->first()->day_count);

        $reactivate = app(PackageSubscriptionService::class)->reactivateDay($ops, $cancelled['order']->fresh());
        $this->assertSame('pending', $cancelled['order']->fresh()->order_status);
        $this->assertSame($cancelled['refunded_amount'], $reactivate['debited_amount']);
        $this->assertSame(50000 - $quote['total_amount'], (int) $user->fresh()->balance);
        $this->assertSame($workingDays, (int) $subscription->fresh()->billable_days);
        $this->assertTrue(OrderGroupOrder::query()->where('order_id', $cancelled['order']->id)->exists());

        Carbon::setTestNow();
    }

    public function test_livewire_cancel_pending_then_reactivate_via_modal(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123974',
            'balance' => 50000,
        ]);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123973']);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 100, 8);

        $result = $this->subscribeCorporate($user, $package, $city, $area, $menu);
        $scheduled = $this->scheduleSubscription($ops, $result['subscription'], $menu);
        $order = $scheduled['orders']->first();
        $balanceAfterSubscribe = (int) $user->fresh()->balance;

        Livewire::actingAs($ops)
            ->test(\App\Livewire\Shared\SubscriptionShow::class, ['subscription' => $result['subscription']->id])
            ->call('openCancelModal', $order->id)
            ->set('cancelReason', 'Ops cancel for reactivate test')
            ->call('confirmCancelAndRefund')
            ->assertSet('errorMessage', null)
            ->assertSee('Untagged · cancelled');

        $this->assertSame('cancelled', $order->fresh()->order_status);
        $this->assertGreaterThan($balanceAfterSubscribe, (int) $user->fresh()->balance);

        Livewire::actingAs($ops)
            ->test(\App\Livewire\Shared\SubscriptionShow::class, ['subscription' => $result['subscription']->id])
            ->call('openReactivateModal', $order->id)
            ->call('confirmReactivate')
            ->assertSet('errorMessage', null)
            ->assertSee('Re-activated order #'.$order->id);

        $this->assertSame('pending', $order->fresh()->order_status);
        $this->assertSame($balanceAfterSubscribe, (int) $user->fresh()->balance);
        $this->assertTrue(OrderGroupOrder::query()->where('order_id', $order->id)->exists());
        $this->assertDatabaseHas('package_subscription_events', [
            'package_subscription_id' => $result['subscription']->id,
            'type' => 'day_reactivated',
        ]);

        Carbon::setTestNow();
    }
}
