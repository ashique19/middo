<?php

namespace Tests\Feature;

use App\Livewire\Corporate\PackageShow;
use App\Livewire\Shared\SubscriptionShow;
use App\Models\Area;
use App\Models\City;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PackageDayCancelRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderCutoff;
use App\Support\PackageBilling;
use App\Support\PackageDayCancelRequestService;
use App\Support\PackageSubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PackageDayCancelRequestTest extends TestCase
{
    use RefreshDatabase;

    private Role $corporateRole;

    private Role $operationRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->corporateRole = Role::create(['name' => 'corporate']);
        $this->operationRole = Role::create(['name' => 'operation']);
    }

    public function test_corporate_can_request_cancel_and_ops_can_approve_refund(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $corporate = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 50000,
        ]);
        $ops = $this->makeOps();
        $menu = $this->makeMenuItem();
        $package = $this->makeRatePlan(100);
        $workingDays = PackageBilling::availableDatesInMonth('2026-08', [5, 6])->count();

        $subscribed = app(PackageSubscriptionService::class)->subscribe(
            $corporate,
            $package,
            1,
            [5, 6],
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            '2026-08',
            'Corporate User',
            $corporate->mobile,
            'House 12, Road 5',
            $city->id,
            $area->id,
            '12:00 PM',
            'balance'
        );

        $dates = PackageBilling::availableDatesInMonth('2026-08', [5, 6]);
        $scheduled = app(PackageSubscriptionService::class)->assignSchedule(
            $ops,
            $subscribed['subscription'],
            $dates->map(fn ($date) => ['date' => $date, 'menu_item_id' => $menu->id])->values()->all()
        );

        $order = $scheduled['orders']->sortBy('delivery_date')->first();
        $balanceBefore = (int) $corporate->fresh()->balance;

        Livewire::actingAs($corporate)
            ->test(PackageShow::class, ['subscriptionId' => $subscribed['subscription']->id])
            ->call('openRequestModal', $order->id)
            ->set('requestReason', 'Office closed for holiday')
            ->call('submitCancelRequest')
            ->assertSet('successMessage', 'Cancel request sent to Middo operations.')
            ->assertSee('Cancel requested')
            ->assertSee('Office closed for holiday');

        $request = PackageDayCancelRequest::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($request);
        $this->assertTrue($request->isPending());
        $this->assertSame('pending', $order->fresh()->order_status);
        $this->assertSame($balanceBefore, (int) $corporate->fresh()->balance);

        Livewire::actingAs($ops)
            ->test(SubscriptionShow::class, ['subscription' => $subscribed['subscription']->id])
            ->assertSee('Cancel requested')
            ->assertSee('Office closed for holiday')
            ->call('approveCancelRequest', $request->id)
            ->assertSet('errorMessage', null)
            ->assertSee('Approved cancel request');

        $this->assertSame('cancelled', $order->fresh()->order_status);
        $this->assertSame(PackageDayCancelRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertGreaterThan($balanceBefore, (int) $corporate->fresh()->balance);

        Carbon::setTestNow();
    }

    public function test_ops_can_reject_cancel_request_without_refund(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $corporate = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 50000,
            'mobile' => '01310123888',
        ]);
        $ops = $this->makeOps();
        $menu = $this->makeMenuItem();
        $package = $this->makeRatePlan(100);
        $workingDays = PackageBilling::availableDatesInMonth('2026-08', [5, 6])->count();

        $subscribed = app(PackageSubscriptionService::class)->subscribe(
            $corporate,
            $package,
            1,
            [5, 6],
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            '2026-08',
            'Corporate User',
            $corporate->mobile,
            'House 12, Road 5',
            $city->id,
            $area->id,
            '12:00 PM',
            'balance'
        );

        $dates = PackageBilling::availableDatesInMonth('2026-08', [5, 6]);
        $scheduled = app(PackageSubscriptionService::class)->assignSchedule(
            $ops,
            $subscribed['subscription'],
            $dates->map(fn ($date) => ['date' => $date, 'menu_item_id' => $menu->id])->values()->all()
        );
        $order = $scheduled['orders']->first();
        $balanceBefore = (int) $corporate->fresh()->balance;

        $request = app(PackageDayCancelRequestService::class)->request(
            $corporate,
            $order,
            'Please cancel this day'
        );

        Livewire::actingAs($ops)
            ->test(SubscriptionShow::class, ['subscription' => $subscribed['subscription']->id])
            ->call('toggleReviewRequest', $request->id)
            ->set('reviewOpsNote', 'Too late for kitchen prep')
            ->call('rejectCancelRequest', $request->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(PackageDayCancelRequest::STATUS_REJECTED, $request->fresh()->status);
        $this->assertSame('pending', $order->fresh()->order_status);
        $this->assertSame($balanceBefore, (int) $corporate->fresh()->balance);
        $this->assertSame('Too late for kitchen prep', $request->fresh()->ops_note);

        Carbon::setTestNow();
    }

    public function test_corporate_can_withdraw_pending_request(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $corporate = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 50000,
            'mobile' => '01310123887',
        ]);
        $ops = $this->makeOps();
        $menu = $this->makeMenuItem();
        $package = $this->makeRatePlan(100);
        $workingDays = PackageBilling::availableDatesInMonth('2026-08', [5, 6])->count();

        $subscribed = app(PackageSubscriptionService::class)->subscribe(
            $corporate,
            $package,
            1,
            [5, 6],
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            '2026-08',
            'Corporate User',
            $corporate->mobile,
            'House 12, Road 5',
            $city->id,
            $area->id,
            '12:00 PM',
            'balance'
        );

        $dates = PackageBilling::availableDatesInMonth('2026-08', [5, 6]);
        $scheduled = app(PackageSubscriptionService::class)->assignSchedule(
            $ops,
            $subscribed['subscription'],
            $dates->map(fn ($date) => ['date' => $date, 'menu_item_id' => $menu->id])->values()->all()
        );
        $order = $scheduled['orders']->first();

        $request = app(PackageDayCancelRequestService::class)->request(
            $corporate,
            $order,
            'Changed my mind soon'
        );

        Livewire::actingAs($corporate)
            ->test(PackageShow::class, ['subscriptionId' => $subscribed['subscription']->id])
            ->call('withdrawCancelRequest', $request->id)
            ->assertSet('successMessage', 'Cancel request withdrawn.');

        $this->assertSame(PackageDayCancelRequest::STATUS_WITHDRAWN, $request->fresh()->status);

        Carbon::setTestNow();
    }

    private function makeCorporate(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Corporate',
            'last_name' => 'User',
            'company_name' => 'Middo Demo Corp',
            'mobile' => '01310123452',
            'password' => '12345678',
            'role_id' => $this->corporateRole->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 50000,
            'address' => 'House 12, Road 5',
        ], $overrides));
    }

    private function makeOps(): User
    {
        return User::create([
            'first_name' => 'Ops',
            'last_name' => 'User',
            'mobile' => '01310123900',
            'password' => '12345678',
            'role_id' => $this->operationRole->id,
            'status' => 'active',
        ]);
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

    private function makeRatePlan(int $pricePerDay = 79): MealPackage
    {
        $start = now(OrderCutoff::timezone())->startOfMonth();

        return MealPackage::create([
            'name' => '৳'.$pricePerDay.'/day Classic',
            'summary' => 'Monthly rate plan',
            'price_per_day' => $pricePerDay,
            'diet_tag' => 'classic',
            'duration_days' => 30,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->toDateString(),
            'status' => MealPackage::STATUS_PUBLISHED,
            'display_order' => 1,
        ]);
    }
}
