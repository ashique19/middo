<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\StaffAlert;
use App\Models\User;
use App\Support\KitchenPermissions;
use App\Support\MiddoSettings;
use App\Support\OrderTransition;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\KitchenBoxFactory;
use Tests\TestCase;

class KitchenMobileApiTest extends TestCase
{
    use RefreshDatabase;

    private Role $kitchenRole;

    private Role $corporateRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kitchenRole = Role::create(['name' => 'kitchen']);
        $this->corporateRole = Role::create(['name' => 'corporate']);
        KitchenPermissions::syncKitchenRole($this->kitchenRole);
    }

    private function makeKitchen(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Gulshan',
            'last_name' => 'Kitchen',
            'mobile' => '01310123453',
            'password' => '12345678',
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 0,
            'address' => 'Road 45, Gulshan Ave',
        ], $overrides));
    }

    private function makeCorporate(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'mobile' => '01310123452',
            'password' => '12345678',
            'role_id' => $this->corporateRole->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 5000,
            'address' => 'House 12',
        ], $overrides));
    }

    private function makeMenu(): MenuItem
    {
        return MenuItem::create([
            'name' => 'Office Thali',
            'summary' => 'Daily lunch',
            'price' => 250,
            'is_featured' => true,
            'display_order' => 1,
        ]);
    }

    public function test_kitchen_can_login_and_receive_token(): void
    {
        $this->makeKitchen();

        $response = $this->postJson('/api/kitchen/login', [
            'mobile' => '01310123453',
            'password' => '12345678',
            'device_name' => 'kitchen-pixel',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.mobile', '01310123453')
            ->assertJsonPath('user.role', 'kitchen')
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'first_name', 'name']]);

        $this->assertSame('Bearer', $response->json('token_type'));
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_non_kitchen_cannot_login_to_kitchen_api(): void
    {
        $this->makeCorporate();

        $this->postJson('/api/kitchen/login', [
            'mobile' => '01310123452',
            'password' => '12345678',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Login as Kitchen to continue.');
    }

    public function test_kitchen_me_requires_auth(): void
    {
        $this->getJson('/api/kitchen/me')->assertUnauthorized();
    }

    public function test_kitchen_me_and_dashboard(): void
    {
        $kitchen = $this->makeKitchen();

        Sanctum::actingAs($kitchen);

        $this->getJson('/api/kitchen/me')
            ->assertOk()
            ->assertJsonPath('user.role', 'kitchen')
            ->assertJsonPath('user.mobile', '01310123453');

        $this->getJson('/api/kitchen/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'tiles',
                'insufficient_box_stock',
                'ops_incoming_notices',
                'capacity' => ['open_groups', 'remaining_slots', 'sendable_boxes'],
            ]);

        $keys = collect($this->getJson('/api/kitchen/dashboard')->json('tiles'))->pluck('key')->all();
        $this->assertContains('alerts', $keys);
        $this->assertContains('active_orders', $keys);
        $this->assertContains('claimable_groups', $keys);
    }

    public function test_kitchen_can_register_device_token(): void
    {
        $kitchen = $this->makeKitchen();
        Sanctum::actingAs($kitchen);

        $token = str_repeat('a', 40);

        $this->postJson('/api/kitchen/device-tokens', [
            'token' => $token,
            'platform' => 'android',
            'device_name' => 'kitchen-1',
        ])->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $kitchen->id,
            'token' => $token,
            'platform' => 'android',
        ]);

        $this->deleteJson('/api/kitchen/device-tokens', ['token' => $token])
            ->assertOk();

        $this->assertDatabaseMissing('device_tokens', [
            'token' => $token,
        ]);
    }

    public function test_kitchen_alerts_list_and_mark_read(): void
    {
        $kitchen = $this->makeKitchen();
        Sanctum::actingAs($kitchen);

        $alert = StaffAlert::create([
            'user_id' => $kitchen->id,
            'type' => StaffAlert::TYPE_GROUP_ASSIGNED,
            'title' => 'New group available',
            'body' => 'Claim Gulshan lunch run',
        ]);

        $this->getJson('/api/kitchen/alerts')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('alerts.0.id', $alert->id)
            ->assertJsonPath('alerts.0.is_unread', true);

        $this->patchJson('/api/kitchen/alerts/'.$alert->id.'/read')
            ->assertOk();

        $this->assertNotNull($alert->fresh()->read_at);
    }

    public function test_kitchen_order_groups_and_accept(): void
    {
        Carbon::setTestNow(Carbon::parse(
            now('Asia/Dhaka')->toDateString().' 11:00 AM',
            'Asia/Dhaka'
        ));
        MiddoSettings::updateMealAndKitchenDefaults([
            'accept_window_minutes' => 120,
        ]);

        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);
        $kitchen = $this->makeKitchen([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'kitchen_tier' => 'gold',
            'allowed_open_groups' => 5,
        ]);
        $corporate = $this->makeCorporate(['city_id' => $city->id, 'area_id' => $area->id]);
        $menu = $this->makeMenu();
        $today = now('Asia/Dhaka')->toDateString();

        KitchenBoxFactory::seedSendable($kitchen, 5);

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 2,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 500,
            'address' => 'SECRET STREET — must not leak',
            'area_id' => $area->id,
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        $group = OrderGroup::create([
            'name' => 'Gulshan · '.$today.' · Office Thali',
            'menu_id' => $menu->id,
            'area_id' => $area->id,
            'delivery_date' => $today,
            'kitchen_id' => null,
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);
        $group->orders()->attach($order->id);

        Sanctum::actingAs($kitchen);

        $list = $this->getJson('/api/kitchen/order-groups')->assertOk();
        $list->assertJsonPath('groups.0.id', $group->id);
        $payload = json_encode($list->json());
        $this->assertStringNotContainsString('SECRET STREET', $payload);
        $this->assertStringNotContainsString($corporate->name, $payload);

        $this->postJson('/api/kitchen/order-groups/'.$group->id.'/accept')
            ->assertOk()
            ->assertJsonPath('group.kitchen_id', $kitchen->id);

        $this->assertSame($kitchen->id, (int) $group->fresh()->kitchen_id);

        Carbon::setTestNow();
    }

    public function test_kitchen_active_orders_and_mark_ready(): void
    {
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Banani', 'city_id' => $city->id]);
        $kitchen = $this->makeKitchen(['city_id' => $city->id, 'area_id' => $area->id]);
        $corporate = $this->makeCorporate(['city_id' => $city->id, 'area_id' => $area->id, 'mobile' => '01310999111']);
        $menu = $this->makeMenu();
        $today = now('Asia/Dhaka')->toDateString();

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => $today,
            'delivery_time' => '12:30 PM',
            'total_amount' => 250,
            'address' => 'Private Address',
            'area_id' => $area->id,
            'order_status' => OrderTransition::PROCESSING,
            'payment_status' => 'paid',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        $group = OrderGroup::create([
            'name' => 'Banani · '.$today.' · Office Thali',
            'menu_id' => $menu->id,
            'area_id' => $area->id,
            'delivery_date' => $today,
            'kitchen_id' => $kitchen->id,
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);
        $group->orders()->attach($order->id);

        Sanctum::actingAs($kitchen);

        $this->getJson('/api/kitchen/orders/active')
            ->assertOk()
            ->assertJsonPath('groups.0.id', $group->id)
            ->assertJsonPath('groups.0.orders.0.id', $order->id);

        $this->postJson('/api/kitchen/orders/'.$order->id.'/ready')
            ->assertOk();

        $this->assertContains($order->fresh()->order_status, [
            OrderTransition::READY,
            OrderTransition::RIDER_ASSIGNED,
        ]);
    }

    public function test_kitchen_menus_today_and_boxes_list(): void
    {
        $kitchen = $this->makeKitchen();
        Sanctum::actingAs($kitchen);

        $this->getJson('/api/kitchen/menus/today')
            ->assertOk()
            ->assertJsonStructure(['delivery_date', 'menus']);

        $this->getJson('/api/kitchen/boxes/at-kitchen')
            ->assertOk()
            ->assertJsonStructure(['boxes', 'count', 'meta']);
    }

    public function test_corporate_token_cannot_access_kitchen_routes(): void
    {
        $corporate = $this->makeCorporate();
        Sanctum::actingAs($corporate);

        $this->getJson('/api/kitchen/dashboard')->assertForbidden();
    }
}
