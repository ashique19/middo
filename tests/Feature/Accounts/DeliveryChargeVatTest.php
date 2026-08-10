<?php

namespace Tests\Feature\Accounts;

use App\Models\Charge;
use App\Models\Coupon;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCharge;
use App\Models\OrderMoneyEvent;
use App\Models\Role;
use App\Models\User;
use App\Support\DeliveryChargeVat;
use App\Support\MiddoSettings;
use App\Support\OrderMoneyFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryChargeVatTest extends TestCase
{
    use RefreshDatabase;

    protected User $corporate;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $corporateRole = Role::create(['name' => 'corporate']);
        Role::create(['name' => 'admin']);

        $this->corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'DelVAT',
            'mobile' => '01880000091',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);

        $this->menu = MenuItem::create([
            'name' => 'Del VAT Thali',
            'price' => 400,
            'kitchen_commission' => 100,
            'delivery_commission' => 50,
        ]);
    }

    public function test_inclusive_delivery_vat_unbundles_after_delivery_coupon(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_VAT_RATE_PCT, '5');
        MiddoSettings::set(MiddoSettings::KEY_DELIVERY_VAT_RATE_PCT, '15');

        $coupon = Coupon::create([
            'code' => 'FREEDEL15',
            'name' => 'Waive 15 delivery',
            'type' => Coupon::TYPE_WAIVE_CHARGE,
            'value' => 0,
            'waive_charge_category' => Charge::CATEGORY_DELIVERY,
            'applies_to' => Coupon::APPLIES_BOTH,
            'is_active' => true,
            'per_user_limit' => 10,
        ]);

        $lines = [
            ['charge_id' => 1, 'category' => 'delivery', 'amount' => 115],
            ['charge_id' => 2, 'category' => 'handling', 'amount' => 40],
        ];

        $quoted = DeliveryChargeVat::quote($coupon, 15, $lines, 15.0);
        // net delivery = 115 - 15 = 100 inclusive @ 15% → ex 87, vat 13
        $this->assertSame(115, $quoted['delivery_gross']);
        $this->assertSame(15, $quoted['delivery_discount']);
        $this->assertSame(100, $quoted['delivery_net']);
        $this->assertSame(40, $quoted['other_gross']);
        $this->assertSame(13, $quoted['delivery_vat_amount']);
        $this->assertSame(87, $quoted['delivery_ex_vat']);
    }

    public function test_order_snapshots_delivery_vat_separately_from_food_vat(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_VAT_RATE_PCT, '5');
        MiddoSettings::set(MiddoSettings::KEY_DELIVERY_VAT_RATE_PCT, '15');

        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 555,
            'charges_amount' => 155,
            'delivery_charge_amount' => 115,
            'other_charges_amount' => 40,
            'delivery_discount_amount' => 0,
            'delivery_vat_rate_pct' => 15,
            'delivery_vat_amount' => 15, // 115 - round(115/1.15)=115-100
            'discount_amount' => 0,
            'address' => 'Office',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
        ]);

        OrderCharge::create([
            'order_id' => $order->id,
            'name' => 'Delivery',
            'category' => 'delivery',
            'calculation' => 'per_delivery',
            'unit_amount' => 115,
            'quantity' => 1,
            'amount' => 115,
        ]);
        OrderCharge::create([
            'order_id' => $order->id,
            'name' => 'Handling',
            'category' => 'handling',
            'calculation' => 'per_checkout',
            'unit_amount' => 40,
            'quantity' => 1,
            'amount' => 40,
        ]);

        $order->refresh();
        $this->assertSame(400, (int) $order->food_amount);
        $this->assertSame(19, (int) $order->vat_amount); // food 5%
        $this->assertSame(115, (int) $order->delivery_charge_amount);
        $this->assertSame(15, (int) $order->delivery_vat_amount);
        $this->assertSame(40, (int) $order->other_charges_amount);

        // billNet = 400+155 = 555; middo = 555 - 19 - 15 - 100 - 50 = 371
        $this->assertSame(371, (int) $order->middo_rest_amount);

        $this->assertTrue(OrderMoneyEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', OrderMoneyEvent::TYPE_VAT)
            ->where('amount', 15)
            ->where('description', 'like', 'Delivery VAT%')
            ->exists());

        $tree = OrderMoneyFlow::treeForOrder($order->fresh(['moneyEvents', 'partnerPayables']));
        $this->assertSame(15, $tree['summary']['delivery_vat']);
        $this->assertSame(100, $tree['summary']['delivery_ex_vat']);
        $this->assertSame(19, $tree['summary']['vat']);
    }

    public function test_admin_can_set_delivery_vat_rate(): void
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'VAT',
            'mobile' => '01880000092',
            'password' => 'password',
            'role_id' => Role::query()->where('name', 'admin')->value('id'),
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\SettingsPage::class)
            ->set('delivery_vat_rate_pct', 15)
            ->set('vat_rate_pct', 5)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsWithDelta(15.0, MiddoSettings::deliveryVatRatePct(), 0.001);
    }

    public function test_percent_coupon_is_not_treated_as_delivery_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'PCT10',
            'name' => 'Ten percent',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 10,
            'applies_to' => Coupon::APPLIES_BOTH,
            'is_active' => true,
            'per_user_limit' => 10,
        ]);

        $quoted = DeliveryChargeVat::quote($coupon, 50, [
            ['category' => 'delivery', 'amount' => 100],
        ], 15.0);

        $this->assertSame(0, $quoted['delivery_discount']);
        $this->assertSame(100, $quoted['delivery_net']);
    }
}
