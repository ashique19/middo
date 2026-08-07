<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Kitchen\ActiveOrders;
use App\Livewire\Shared\OrderShow;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderLens;
use App\Support\OrdersExcelExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenOrderPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected User $kitchen;

    protected User $customer;

    protected Area $area;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $corporateRole = Role::create(['name' => 'corporate']);

        $city = City::create(['name' => 'Dhaka']);
        $this->area = Area::create(['name' => 'Banani', 'city_id' => $city->id]);

        $this->kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'One',
            'mobile' => '01994000001',
            'password' => '12345678',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
            'area_id' => $this->area->id,
        ]);

        $this->customer = User::create([
            'first_name' => 'Secret',
            'last_name' => 'Customer',
            'mobile' => '01994000002',
            'password' => '12345678',
            'role_id' => $corporateRole->id,
            'status' => 'active',
            'company_name' => 'Acme',
        ]);

        $menu = MenuItem::create([
            'name' => 'Tehari',
            'summary' => 'Test',
            'price' => 420,
            'thumbnail' => 'img/menu/menu-1.jpg',
            'is_featured' => true,
            'display_order' => 1,
            'kitchen_commission' => 100,
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-TEST',
            'menu_id' => $menu->id,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'kitchen_id' => $this->kitchen->id,
            'area_id' => $this->area->id,
        ]);

        $this->order = Order::create([
            'user_id' => $this->customer->id,
            'menu_item_id' => $menu->id,
            'quantity' => 2,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 840,
            'amount_paid' => 0,
            'address' => 'House 12, Road 5, Banani secret street',
            'area_id' => $this->area->id,
            'order_status' => 'processing',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'created_by' => $this->customer->id,
            'updated_by' => $this->customer->id,
        ]);

        $group->orders()->attach($this->order->id);
    }

    public function test_kitchen_active_orders_show_area_not_customer_or_address(): void
    {
        $this->actingAs($this->kitchen);

        Livewire::test(ActiveOrders::class)
            ->assertSee('#'.$this->order->id, false)
            ->assertSee('Banani')
            ->assertDontSee('Secret Customer')
            ->assertDontSee('House 12, Road 5, Banani secret street');
    }

    public function test_kitchen_order_lens_hides_customer_pii(): void
    {
        $this->actingAs($this->kitchen);

        Livewire::test(OrderShow::class, ['order' => $this->order])
            ->assertSee('Banani')
            ->assertDontSee('Secret Customer')
            ->assertDontSee('House 12, Road 5, Banani secret street')
            ->assertDontSee('Receiver');

        $payload = OrderLens::payload($this->order->fresh(), OrderLens::KITCHEN, $this->kitchen);
        $this->assertSame('Banani', $payload['party']['area_name'] ?? null);
        $this->assertArrayNotHasKey('receiver_name', $payload['party']);
        $this->assertArrayNotHasKey('customer_name', $payload['party']);
    }

    public function test_kitchen_csv_export_omits_customer_columns(): void
    {
        $response = OrdersExcelExport::download(collect([$this->order]), 'kitchen-test.csv', kitchenSafe: true);
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('Area', $csv);
        $this->assertStringContainsString('Banani', $csv);
        $this->assertStringContainsString((string) $this->order->id, $csv);
        $this->assertStringNotContainsString('Customer', $csv);
        $this->assertStringNotContainsString('Receiver', $csv);
        $this->assertStringNotContainsString('Address', $csv);
        $this->assertStringNotContainsString('Secret Customer', $csv);
        $this->assertStringNotContainsString('House 12, Road 5, Banani secret street', $csv);
    }
}
