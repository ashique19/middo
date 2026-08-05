<?php

namespace Tests\Feature\Accounts;

use App\Livewire\Admin\SettingsPage;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderMoneyEvent;
use App\Models\Role;
use App\Models\User;
use App\Support\MiddoSettings;
use App\Support\OrderMoneyFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderVatF0aTest extends TestCase
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
            'last_name' => 'VAT',
            'mobile' => '01880000001',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);

        $this->menu = MenuItem::create([
            'name' => 'VAT Thali',
            'price' => 400,
            'kitchen_commission' => 100,
            'delivery_commission' => 50,
        ]);
    }

    public function test_inclusive_vat_unbundles_and_reduces_middo_rest(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_VAT_RATE_PCT, '5');

        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 400,
            'address' => 'Office',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
        ]);

        $order->refresh();
        $this->assertSame(400, (int) $order->food_amount);
        $this->assertEqualsWithDelta(5.0, (float) $order->vat_rate_pct, 0.001);
        $this->assertSame(19, (int) $order->vat_amount); // 400 - round(400/1.05)
        $this->assertSame(381, (int) $order->food_amount - (int) $order->vat_amount);
        $this->assertSame(100, (int) $order->kitchen_share_amount);
        $this->assertSame(50, (int) $order->delivery_share_amount);
        // middo_rest = billNet - vat - kitchen - delivery = 400 - 19 - 100 - 50
        $this->assertSame(231, (int) $order->middo_rest_amount);

        $this->assertTrue(OrderMoneyEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', OrderMoneyEvent::TYPE_VAT)
            ->where('amount', 19)
            ->exists());

        $tree = OrderMoneyFlow::treeForOrder($order->fresh(['moneyEvents', 'partnerPayables']));
        $this->assertSame(19, $tree['summary']['vat']);
        $this->assertSame(381, $tree['summary']['food_ex_vat']);
        $this->assertSame(231, $tree['summary']['middo_rest']);
    }

    public function test_vat_rate_snapshot_survives_settings_change(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_VAT_RATE_PCT, '5');

        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 400,
            'address' => 'Office',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
        ]);

        MiddoSettings::set(MiddoSettings::KEY_VAT_RATE_PCT, '10');

        $again = OrderMoneyFlow::computeBreakdown($order->fresh(['menuItem']));
        $this->assertEqualsWithDelta(5.0, $again['vat_rate_pct'], 0.001);
        $this->assertSame(19, $again['vat_amount']);
    }

    public function test_admin_can_edit_vat_rate_in_settings(): void
    {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'VAT',
            'mobile' => '01880000002',
            'password' => 'password',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('vat_rate_pct', 7.5)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('statusMessage', fn ($m) => is_string($m) && $m !== '');

        $this->assertEqualsWithDelta(7.5, MiddoSettings::vatRatePct(), 0.001);
    }
}
