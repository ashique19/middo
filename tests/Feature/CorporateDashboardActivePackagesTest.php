<?php

namespace Tests\Feature;

use App\Livewire\Corporate\Dashboard;
use App\Models\Area;
use App\Models\City;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\PackageSubscription;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderCutoff;
use App\Support\PackageBilling;
use App\Support\PackageSubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CorporateDashboardActivePackagesTest extends TestCase
{
    use RefreshDatabase;

    private Role $corporateRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->corporateRole = Role::create(['name' => 'corporate']);
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

    public function test_dashboard_shows_active_packages_tile_and_section(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
        ]);
        $menu = MenuItem::create([
            'name' => 'Office Thali',
            'summary' => 'Daily lunch',
            'price' => 150,
            'is_featured' => true,
            'display_order' => 1,
        ]);
        $start = now(OrderCutoff::timezone())->startOfMonth();
        $package = MealPackage::create([
            'name' => '৳79/day Classic',
            'summary' => 'Monthly rate plan',
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
            'Corporate User',
            $user->mobile,
            'House 12, Road 5',
            $city->id,
            $area->id,
            '12:00 PM',
            'balance'
        );

        $subscription = $result['subscription'];
        $this->assertSame(PackageSubscription::STATUS_ACTIVE, $subscription->status);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSet('metrics.active_packages', 1)
            ->assertSet('activePackages.0.id', $subscription->id)
            ->assertSee('Active Packages')
            ->assertSee($package->name)
            ->assertSee('Awaiting schedule')
            ->assertSee('August 2026');

        Carbon::setTestNow();
    }

    public function test_dashboard_empty_state_when_no_active_packages(): void
    {
        $user = $this->makeCorporate();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSet('metrics.active_packages', 0)
            ->assertDontSee('No active packages yet')
            ->assertDontSee('Prepaid monthly plans currently running for your office.');
    }
}
