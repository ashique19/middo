<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Kitchen\PrepShoppingList;
use App\Models\MealItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Role;
use App\Models\User;
use App\Support\KitchenIngredientRollup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenIngredientRollupTest extends TestCase
{
    use RefreshDatabase;

    protected User $kitchen;

    protected User $otherKitchen;

    protected User $corporate;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $corporateRole = Role::create(['name' => 'corporate']);

        $this->kitchen = User::create([
            'first_name' => 'Gulshan',
            'last_name' => 'Kitchen',
            'mobile' => '01750000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);

        $this->otherKitchen = User::create([
            'first_name' => 'Other',
            'last_name' => 'Kitchen',
            'mobile' => '01750000002',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);

        $this->corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'mobile' => '01750000003',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);
    }

    public function test_rollup_scales_and_merges_ingredients_across_menus(): void
    {
        $today = now('Asia/Dhaka')->toDateString();

        $menuA = $this->menuWithIngredients('Thali A', [
            ['name' => 'Onion', 'quantity' => 0.05, 'unit' => 'kg'],
            ['name' => 'Rice', 'quantity' => 0.15, 'unit' => 'kg'],
        ]);
        $menuB = $this->menuWithIngredients('Thali B', [
            ['name' => 'onion', 'quantity' => 0.08, 'unit' => 'kg'],
            ['name' => 'Onion', 'quantity' => 2, 'unit' => 'pcs'],
        ]);

        $this->assignGroup($this->kitchen->id, $menuA, $today, 10, 'GRP-A');
        $this->assignGroup($this->kitchen->id, $menuB, $today, 5, 'GRP-B');
        $this->assignGroup($this->otherKitchen->id, $menuA, $today, 99, 'GRP-OTHER');

        $open = OrderGroup::create([
            'name' => 'GRP-OPEN',
            'menu_id' => $menuA->id,
            'delivery_date' => $today,
            'kitchen_id' => null,
        ]);
        $openOrder = $this->makeOrder($menuA, $today, 7, 'processing');
        $open->orders()->attach($openOrder->id);

        $rollup = KitchenIngredientRollup::forKitchen($this->kitchen->id, $today);

        $this->assertSame(2, $rollup['group_count']);
        $this->assertSame(15, $rollup['plate_count']);

        $byKey = collect($rollup['ingredients'])->keyBy('key');

        $this->assertTrue($byKey->has(KitchenIngredientRollup::mergeKey('Onion', 'kg')));
        $this->assertSame(0.9, $byKey[KitchenIngredientRollup::mergeKey('Onion', 'kg')]['quantity']);
        $this->assertSame(1.5, $byKey[KitchenIngredientRollup::mergeKey('Rice', 'kg')]['quantity']);
        $this->assertSame(10.0, $byKey[KitchenIngredientRollup::mergeKey('Onion', 'pcs')]['quantity']);
    }

    public function test_rollup_excludes_cancelled_orders_and_warns_missing_recipe(): void
    {
        $today = now('Asia/Dhaka')->toDateString();
        $menu = MenuItem::create(['name' => 'Bare Menu', 'price' => 200]);
        $meal = MealItem::create(['name' => 'No Recipe Dish', 'summary' => 'x']);
        $menu->mealItems()->attach($meal->id, ['sort_order' => 1]);

        $group = OrderGroup::create([
            'name' => 'GRP-BARE',
            'menu_id' => $menu->id,
            'delivery_date' => $today,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $live = $this->makeOrder($menu, $today, 3, 'processing');
        $cancelled = $this->makeOrder($menu, $today, 8, 'cancelled');
        $group->orders()->attach([$live->id, $cancelled->id]);

        $rollup = KitchenIngredientRollup::forKitchen($this->kitchen->id, $today);

        $this->assertSame(3, $rollup['plate_count']);
        $this->assertSame([], $rollup['ingredients']);
        $this->assertNotEmpty($rollup['warnings']);
        $this->assertStringContainsString('No active recipe', $rollup['warnings'][0]);
    }

    public function test_prep_shopping_list_page_renders_for_kitchen(): void
    {
        $today = now('Asia/Dhaka')->toDateString();
        $menu = $this->menuWithIngredients('Lunch', [
            ['name' => 'Chicken', 'quantity' => 0.2, 'unit' => 'kg'],
        ]);
        $this->assignGroup($this->kitchen->id, $menu, $today, 4, 'GRP-UI');

        Livewire::actingAs($this->kitchen)
            ->test(PrepShoppingList::class)
            ->assertStatus(200)
            ->assertSee('Prep shopping list')
            ->assertSee('Chicken')
            ->assertSee('Lunch');
    }

    /**
     * @param  list<array{name: string, quantity: float, unit: string}>  $ingredients
     */
    protected function menuWithIngredients(string $name, array $ingredients): MenuItem
    {
        $menu = MenuItem::create(['name' => $name, 'price' => 250, 'kitchen_commission' => 40]);
        $meal = MealItem::create(['name' => $name.' dish', 'summary' => 'Test']);
        $menu->mealItems()->attach($meal->id, ['sort_order' => 1]);

        $recipe = Recipe::create([
            'meal_item_id' => $meal->id,
            'title' => $name.' recipe',
            'instructions' => 'Cook.',
            'is_active' => true,
        ]);

        foreach ($ingredients as $i => $line) {
            RecipeIngredient::create([
                'recipe_id' => $recipe->id,
                'name' => $line['name'],
                'quantity' => $line['quantity'],
                'unit' => $line['unit'],
                'cost' => 1,
                'sort_order' => $i + 1,
            ]);
        }

        return $menu->fresh('mealItems.activeRecipe.ingredients');
    }

    protected function assignGroup(int $kitchenId, MenuItem $menu, string $date, int $qty, string $name): OrderGroup
    {
        $group = OrderGroup::create([
            'name' => $name,
            'menu_id' => $menu->id,
            'delivery_date' => $date,
            'kitchen_id' => $kitchenId,
        ]);
        $order = $this->makeOrder($menu, $date, $qty, 'processing');
        $group->orders()->attach($order->id);

        return $group;
    }

    protected function makeOrder(MenuItem $menu, string $date, int $qty, string $status): Order
    {
        return Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => $qty,
            'delivery_date' => $date,
            'delivery_time' => '12:00 PM',
            'total_amount' => 200 * $qty,
            'address' => 'Office',
            'order_status' => $status,
            'payment_status' => 'pending',
            'created_by' => $this->corporate->id,
        ]);
    }
}
