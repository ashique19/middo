<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Admin\KitchenOnboarding;
use App\Livewire\Admin\SettingsPage;
use App\Livewire\Kitchen\MiddoOrderGroups;
use App\Livewire\Shared\StaffProfileShow;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use App\Support\KitchenActivation;
use App\Support\KitchenCapacity;
use App\Support\KitchenTier;
use App\Support\MiddoSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\KitchenBoxFactory;
use Tests\TestCase;

class KitchenTierCapacityTest extends TestCase
{
    use RefreshDatabase;

    protected Role $kitchenRole;

    protected Role $adminRole;

    protected User $admin;

    protected MenuItem $menu;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(
            now('Asia/Dhaka')->toDateString().' 11:00 AM',
            'Asia/Dhaka'
        ));

        MiddoSettings::updateMealAndKitchenDefaults([
            'accept_window_minutes' => 120,
        ]);

        $this->kitchenRole = Role::create(['name' => 'kitchen']);
        $this->adminRole = Role::create(['name' => 'admin']);
        Role::create(['name' => 'corporate']);

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01310123451',
            'password' => 'password',
            'role_id' => $this->adminRole->id,
            'status' => 'active',
        ]);

        $corporateRole = Role::where('name', 'corporate')->first();
        $this->customer = User::create([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'mobile' => '01700000099',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);

        $this->menu = MenuItem::create([
            'name' => 'Lunch Box A',
            'summary' => 'Daily lunch',
            'price' => 250,
            'kitchen_commission' => 50,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeKitchen(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'Kitchen',
            'mobile' => '017'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'password' => 'password',
            'role_id' => $this->kitchenRole->id,
            'status' => 'pending',
        ], $overrides));
    }

    protected function createOpenGroup(string $name = 'GRP-OPEN'): OrderGroup
    {
        $order = Order::create([
            'user_id' => $this->customer->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 2,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 500,
            'address' => 'Test Address',
            'order_status' => 'pending',
            'payment_status' => 'paid',
        ]);

        $group = OrderGroup::create([
            'name' => $name,
            'menu_id' => $this->menu->id,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'kitchen_id' => null,
        ]);

        $group->orders()->attach($order->id);

        return $group->fresh();
    }

    public function test_admin_can_save_tier_defaults_in_settings(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(SettingsPage::class)
            ->set('tier_silver', 2)
            ->set('tier_gold', 4)
            ->set('tier_platinum', 6)
            ->set('auto_group_quantity', 12)
            ->set('accept_window_minutes', 90)
            ->set('accept_window_warn_minutes', 20)
            ->set('accept_window_starts_at', '09:30')
            ->set('order_cutoff_time', '16:00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Meal grouping')
            ->assertSee('Corporate allowed order per day')
            ->assertSee('Accept window')
            ->assertSee('Kitchen tier defaults')
            ->assertSee('Daily Order Cutoff time')
            ->assertSee('Rider commissions')
            ->assertSee('Finance — VAT')
            ->assertSee('EPS gateway fees');

        $this->assertSame(2, MiddoSettings::defaultAllowedOpenGroupsForTier(KitchenTier::SILVER));
        $this->assertSame(4, MiddoSettings::defaultAllowedOpenGroupsForTier(KitchenTier::GOLD));
        $this->assertSame(6, MiddoSettings::defaultAllowedOpenGroupsForTier(KitchenTier::PLATINUM));
        $this->assertSame(12, MiddoSettings::autoGroupQuantity());
        $this->assertSame(90, MiddoSettings::acceptWindowMinutes());
        $this->assertSame(20, MiddoSettings::acceptWindowWarnMinutes());
        $this->assertSame('09:30', MiddoSettings::acceptWindowStartsAt());
        $this->assertSame('16:00', MiddoSettings::orderCutoffTime());
        $this->assertSame(16, \App\Support\OrderCutoff::hour());
        $this->assertSame(0, \App\Support\OrderCutoff::minute());
    }

    public function test_accept_window_uses_configured_start_time(): void
    {
        MiddoSettings::updateMealAndKitchenDefaults([
            'accept_window_minutes' => 120,
            'accept_window_starts_at' => '09:00',
        ]);

        $group = $this->createOpenGroup('GRP-START');

        $openAt = \App\Support\KitchenAcceptWindow::windowOpenAt($group->fresh(['orders']));
        $this->assertSame('09:00', $openAt->format('H:i'));
    }

    public function test_evening_accept_window_opens_night_before_for_configured_minutes(): void
    {
        // Lunch tomorrow — evening start must open tonight, not fall back to a morning window.
        $deliveryDate = now('Asia/Dhaka')->addDay()->toDateString();

        MiddoSettings::updateMealAndKitchenDefaults([
            'accept_window_minutes' => 120,
            'accept_window_starts_at' => '22:30',
        ]);

        $order = Order::create([
            'user_id' => $this->customer->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 2,
            'delivery_date' => $deliveryDate,
            'delivery_time' => '12:00 PM',
            'total_amount' => 500,
            'address' => 'Test Address',
            'order_status' => 'pending',
            'payment_status' => 'paid',
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-NIGHT',
            'menu_id' => $this->menu->id,
            'delivery_date' => $deliveryDate,
            'kitchen_id' => null,
        ]);
        $group->orders()->attach($order->id);
        $group = $group->fresh(['orders']);

        [$openAt, $closeAt] = \App\Support\KitchenAcceptWindow::windowBounds($group);

        $this->assertSame(
            now('Asia/Dhaka')->toDateString().' 22:30',
            $openAt->format('Y-m-d H:i')
        );
        $this->assertSame(
            now('Asia/Dhaka')->addDay()->toDateString().' 00:30',
            $closeAt->format('Y-m-d H:i')
        );

        $duringWindow = Carbon::parse(
            now('Asia/Dhaka')->toDateString().' 22:42',
            'Asia/Dhaka'
        );
        $this->assertTrue(\App\Support\KitchenAcceptWindow::isOpen($group, $duringWindow));

        $beforeWindow = Carbon::parse(
            now('Asia/Dhaka')->toDateString().' 22:00',
            'Asia/Dhaka'
        );
        $this->assertFalse(\App\Support\KitchenAcceptWindow::isOpen($group, $beforeWindow));
    }

    public function test_accept_window_uses_live_settings_not_order_created_at(): void
    {
        $deliveryDate = now('Asia/Dhaka')->addDay()->toDateString();

        // Group created while morning window was configured.
        MiddoSettings::updateMealAndKitchenDefaults([
            'accept_window_minutes' => 120,
            'accept_window_starts_at' => '09:00',
        ]);

        $order = Order::create([
            'user_id' => $this->customer->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 2,
            'delivery_date' => $deliveryDate,
            'delivery_time' => '12:00 PM',
            'total_amount' => 500,
            'address' => 'Test Address',
            'order_status' => 'pending',
            'payment_status' => 'paid',
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-LIVE',
            'menu_id' => $this->menu->id,
            'delivery_date' => $deliveryDate,
            'kitchen_id' => null,
        ]);
        $group->orders()->attach($order->id);
        $group = $group->fresh(['orders']);

        // Admin later switches to an evening window for tonight.
        MiddoSettings::updateMealAndKitchenDefaults([
            'accept_window_minutes' => 120,
            'accept_window_starts_at' => '22:30',
        ]);

        $duringWindow = Carbon::parse(
            now('Asia/Dhaka')->toDateString().' 22:42',
            'Asia/Dhaka'
        );
        $this->assertTrue(\App\Support\KitchenAcceptWindow::isOpen($group, $duringWindow));
        $this->assertSame('22:30', \App\Support\KitchenAcceptWindow::windowOpenAt($group)->format('H:i'));
    }

    public function test_activation_copies_tier_default_allowed_open_groups(): void
    {
        MiddoSettings::updateMealAndKitchenDefaults([
            'tier_defaults' => [
                KitchenTier::GOLD => 5,
            ],
        ]);

        $kitchen = $this->makeKitchen([
            'kitchen_tier' => KitchenTier::GOLD,
            'allowed_open_groups' => null,
        ]);

        KitchenActivation::activate($kitchen);

        $kitchen->refresh();
        $this->assertSame('active', $kitchen->status);
        $this->assertSame(KitchenTier::GOLD, $kitchen->kitchen_tier);
        $this->assertSame(5, $kitchen->allowed_open_groups);
    }

    public function test_activation_defaults_missing_tier_to_silver(): void
    {
        MiddoSettings::updateMealAndKitchenDefaults([
            'tier_defaults' => [
                KitchenTier::SILVER => 3,
            ],
        ]);

        $kitchen = $this->makeKitchen([
            'kitchen_tier' => null,
            'allowed_open_groups' => null,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(KitchenOnboarding::class)
            ->call('activate', $kitchen->id);

        $kitchen->refresh();
        $this->assertSame('active', $kitchen->status);
        $this->assertSame(KitchenTier::SILVER, $kitchen->kitchen_tier);
        $this->assertSame(3, $kitchen->allowed_open_groups);
    }

    public function test_activation_preserves_admin_override_allowed_open_groups(): void
    {
        MiddoSettings::updateMealAndKitchenDefaults([
            'tier_defaults' => [
                KitchenTier::SILVER => 1,
            ],
        ]);

        $kitchen = $this->makeKitchen([
            'kitchen_tier' => KitchenTier::SILVER,
            'allowed_open_groups' => 9,
        ]);

        KitchenActivation::activate($kitchen);

        $this->assertSame(9, $kitchen->fresh()->allowed_open_groups);
    }

    public function test_changing_tier_defaults_does_not_overwrite_existing_kitchen(): void
    {
        $kitchen = $this->makeKitchen([
            'status' => 'active',
            'kitchen_tier' => KitchenTier::SILVER,
            'allowed_open_groups' => 1,
        ]);

        MiddoSettings::updateMealAndKitchenDefaults([
            'tier_defaults' => [
                KitchenTier::SILVER => 8,
            ],
        ]);

        $this->assertSame(1, $kitchen->fresh()->allowed_open_groups);
        $this->assertSame(8, MiddoSettings::defaultAllowedOpenGroupsForTier(KitchenTier::SILVER));
    }

    public function test_admin_can_override_kitchen_allowed_open_groups_on_profile(): void
    {
        $kitchen = $this->makeKitchen([
            'status' => 'active',
            'kitchen_tier' => KitchenTier::SILVER,
            'allowed_open_groups' => 1,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(StaffProfileShow::class, ['kitchen' => $kitchen])
            ->set('edit_kitchen_tier', KitchenTier::PLATINUM)
            ->set('edit_allowed_open_groups', 7)
            ->call('saveKitchenCapacity')
            ->assertHasNoErrors();

        $kitchen->refresh();
        $this->assertSame(KitchenTier::PLATINUM, $kitchen->kitchen_tier);
        $this->assertSame(7, $kitchen->allowed_open_groups);
    }

    public function test_kitchen_cannot_accept_beyond_allowed_open_groups(): void
    {
        $kitchen = $this->makeKitchen([
            'status' => 'active',
            'kitchen_tier' => KitchenTier::SILVER,
            'allowed_open_groups' => 1,
        ]);

        $held = $this->createOpenGroup('GRP-HELD');
        $held->update(['kitchen_id' => $kitchen->id]);
        $held->orders()->update(['order_status' => 'processing']);

        $this->assertSame(1, KitchenCapacity::openGroupCount($kitchen->id));
        $this->assertFalse(KitchenCapacity::canAccept($kitchen));

        $next = $this->createOpenGroup('GRP-NEXT');

        Livewire::actingAs($kitchen)
            ->test(MiddoOrderGroups::class)
            ->call('acceptOrder', $next->id)
            ->assertSet('errorMessage', fn ($msg) => is_string($msg) && str_contains($msg, 'at capacity'));

        $this->assertNull($next->fresh()->kitchen_id);
    }

    public function test_kitchen_can_accept_when_under_capacity(): void
    {
        $kitchen = $this->makeKitchen([
            'status' => 'active',
            'kitchen_tier' => KitchenTier::GOLD,
            'allowed_open_groups' => 2,
        ]);
        KitchenBoxFactory::seedSendable($kitchen, 2);

        $group = $this->createOpenGroup('GRP-ACCEPT');

        Livewire::actingAs($kitchen)
            ->test(MiddoOrderGroups::class)
            ->call('acceptOrder', $group->id)
            ->assertSet('errorMessage', null);

        $this->assertSame($kitchen->id, $group->fresh()->kitchen_id);
        $this->assertSame(1, KitchenCapacity::openGroupCount($kitchen->id));
        $this->assertSame(1, KitchenCapacity::remainingSlots($kitchen->fresh()));
    }

    public function test_dispatched_group_frees_capacity_slot(): void
    {
        $kitchen = $this->makeKitchen([
            'status' => 'active',
            'kitchen_tier' => KitchenTier::SILVER,
            'allowed_open_groups' => 1,
        ]);

        $group = $this->createOpenGroup('GRP-DISPATCH');
        $group->update(['kitchen_id' => $kitchen->id]);
        $group->orders()->update([
            'order_status' => 'packed',
            'dispatched_at' => now(),
        ]);

        $this->assertSame(0, KitchenCapacity::openGroupCount($kitchen->id));
        $this->assertTrue(KitchenCapacity::canAccept($kitchen->fresh()));
    }
}
