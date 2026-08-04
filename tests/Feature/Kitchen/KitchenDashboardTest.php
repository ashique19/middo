<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Kitchen\MiddoOrderGroups;
use App\Models\MealItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Recipe;
use App\Models\Role;
use App\Models\User;
use App\Support\MiddoSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Role $kitchenRole;

    protected User $kitchen;

    protected User $customer;

    protected MenuItem $menu;

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
        Role::create(['name' => 'corporate']);

        $this->kitchen = User::create([
            'first_name' => 'Gulshan',
            'last_name' => 'Kitchen',
            'mobile' => '01700000001',
            'password' => 'password',
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
            'kitchen_tier' => 'silver',
            'allowed_open_groups' => 3,
        ]);

        $corporateRole = Role::where('name', 'corporate')->first();

        $this->customer = User::create([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'mobile' => '01700000002',
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

    protected function createOrderGroup(?int $kitchenId, string $deliveryDate, string $name = 'GRP-TEST-001'): OrderGroup
    {
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
            'name' => $name,
            'menu_id' => $this->menu->id,
            'delivery_date' => $deliveryDate,
            'kitchen_id' => $kitchenId,
        ]);

        $group->orders()->attach($order->id);

        return $group->fresh(['orders', 'menuItem']);
    }

    public function test_kitchen_dashboard_shows_tile_counts(): void
    {
        $today = now('Asia/Dhaka')->toDateString();

        $this->createOrderGroup(null, $today, 'GRP-UNASSIGNED');
        $this->createOrderGroup($this->kitchen->id, $today, 'GRP-ACTIVE');

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.dashboard'))
            ->assertOk()
            ->assertSee('My Order this month')
            ->assertSee('Last 3 months')
            ->assertSee('My active orders')
            ->assertSee('Middo order groups')
            ->assertSee('(1)', false);
    }

    public function test_kitchen_can_accept_unassigned_order_group(): void
    {
        $today = now('Asia/Dhaka')->toDateString();
        $group = $this->createOrderGroup(null, $today, 'GRP-ACCEPT-ME');

        Livewire::actingAs($this->kitchen)
            ->test(MiddoOrderGroups::class)
            ->call('acceptOrder', $group->id)
            ->assertSet('statusMessage', "Accepted {$group->name}. It is now assigned to your kitchen.");

        $this->assertDatabaseHas('order_groups', [
            'id' => $group->id,
            'kitchen_id' => $this->kitchen->id,
            'updated_by' => $this->kitchen->id,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $group->orders()->first()->id,
            'order_status' => 'processing',
        ]);
    }

    public function test_kitchen_cannot_accept_already_assigned_group(): void
    {
        $today = now('Asia/Dhaka')->toDateString();
        $otherKitchen = User::create([
            'first_name' => 'Banani',
            'last_name' => 'Kitchen',
            'mobile' => '01700000003',
            'password' => 'password',
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
        ]);

        $group = $this->createOrderGroup($otherKitchen->id, $today, 'GRP-TAKEN');

        Livewire::actingAs($this->kitchen)
            ->test(MiddoOrderGroups::class)
            ->call('acceptOrder', $group->id)
            ->assertSet('errorMessage', 'This order group was already accepted by another kitchen.');

        $this->assertDatabaseHas('order_groups', [
            'id' => $group->id,
            'kitchen_id' => $otherKitchen->id,
        ]);
    }

    public function test_active_orders_show_menu_link_and_menu_details_with_recipe(): void
    {
        $today = now('Asia/Dhaka')->toDateString();
        $this->createOrderGroup($this->kitchen->id, $today, 'GRP-MENU');

        $meal = MealItem::create([
            'name' => 'Chicken Curry',
            'summary' => 'Spicy curry',
        ]);
        $this->menu->mealItems()->attach($meal->id, ['sort_order' => 1]);

        Recipe::create([
            'meal_item_id' => $meal->id,
            'title' => 'Classic Curry',
            'instructions' => 'Cook slowly.',
            'is_active' => true,
        ]);

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.orders.active'))
            ->assertOk()
            ->assertSee('GRP-MENU')
            ->assertSee('Lunch Box A');

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.menus.show', $this->menu))
            ->assertOk()
            ->assertSee('Chicken Curry')
            ->assertSee('View recipe');

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.menus.meal-items.recipe', [$this->menu, $meal]))
            ->assertOk()
            ->assertSee('Classic Curry')
            ->assertSee('Cook slowly.');
    }

    public function test_kitchen_cannot_view_menu_without_assignment(): void
    {
        $this->actingAs($this->kitchen)
            ->get(route('kitchen.menus.show', $this->menu))
            ->assertNotFound();
    }

    public function test_guest_cannot_access_kitchen_dashboard(): void
    {
        $this->get(route('kitchen.dashboard'))
            ->assertRedirect();
    }
}
