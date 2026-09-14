<?php

namespace Tests\Feature\Api;

use App\Livewire\Kitchen\BoxesAtKitchen;
use App\Livewire\Operation\MiddoBoxes;
use App\Livewire\Shared\RiderMoneyApprovals;
use App\Models\Area;
use App\Models\City;
use App\Models\KitchenWarehouseHandoff;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use App\Support\DeliveryPermissions;
use App\Support\DeliveryRunType;
use App\Support\MiddoSettings;
use App\Support\OrderTransition;
use App\Support\PayoutChannel;
use App\Support\RiderAccountLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Support\LunchRunFlow;
use Tests\TestCase;

class DeliveryMobileHardenTest extends TestCase
{
    use RefreshDatabase;

    private Role $deliveryRole;

    private User $kitchen;

    private User $rider;

    private User $customer;

    private MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $this->deliveryRole = Role::create(['name' => 'delivery']);
        $corporateRole = Role::create(['name' => 'corporate']);
        Role::create(['name' => 'operation']);
        DeliveryPermissions::syncDeliveryRole($this->deliveryRole);

        $this->kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'HD',
            'mobile' => '01932000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'HD',
            'mobile' => '01932000002',
            'password' => 'password',
            'role_id' => $this->deliveryRole->id,
            'status' => 'active',
            'rider_shift_status' => 'on',
            'balance' => 0,
        ]);
        $this->customer = User::create([
            'first_name' => 'Corp',
            'last_name' => 'HD',
            'mobile' => '01932000003',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);
        $this->menu = MenuItem::create([
            'name' => 'HD Lunch',
            'price' => 200,
            'kitchen_commission' => 50,
            'delivery_commission' => 40,
        ]);
    }

    /**
     * @return array{0: Order, 1: MiddoBox}
     */
    private function makeReadyOrderWithBox(): array
    {
        $today = now('Asia/Dhaka')->toDateString();
        $order = Order::create([
            'user_id' => $this->customer->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'receiver_mobile' => '01932000003',
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-HD-'.uniqid(),
            'menu_id' => $this->menu->id,
            'delivery_date' => $today,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);
        OrderTransition::apply($order->fresh(), OrderTransition::PROCESSING);
        OrderTransition::apply($order->fresh(), OrderTransition::READY);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-HD-'.uniqid(),
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->kitchen->id,
            'total_uses_count' => 0,
        ]);

        return [$order->fresh(), $box];
    }

    private function makeOnTheWayOrder(): Order
    {
        [$order, $box] = $this->makeReadyOrderWithBox();

        return LunchRunFlow::fromReadyToOnTheWay(
            $this->kitchen,
            $this->rider,
            $order,
            $box
        );
    }

    private function makePackedOrder(): Order
    {
        [$order, $box] = $this->makeReadyOrderWithBox();
        LunchRunFlow::riderAccept($this->rider, $order);
        LunchRunFlow::kitchenDispatch($this->kitchen, $order->fresh(), $box);

        return $order->fresh();
    }

    public function test_eta_then_deliver_payment_link_and_corporate_track(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_DELIVERY_REQUIRE_POD, '0');
        Sanctum::actingAs($this->rider);
        $order = $this->makeOnTheWayOrder();

        $this->postJson("/api/delivery/runs/{$order->id}/eta", [
            'eta_minutes' => 25,
        ])->assertOk()
            ->assertJsonPath('eta_minutes', 25);

        $this->postJson("/api/delivery/runs/{$order->id}/deliver", [])->assertOk();

        $this->postJson("/api/delivery/orders/{$order->id}/send-payment-link", [
            'phone' => '01710123456',
        ])->assertOk()
            ->assertJsonStructure(['payment_url', 'phone', 'sms_sent', 'message']);

        Sanctum::actingAs($this->customer);
        $track = $this->getJson("/api/corporate/orders/{$order->id}/track")->assertOk();
        $this->assertSame($this->rider->name, $track->json('order.rider_name'));
        $this->assertSame(25, (int) $track->json('order.eta_minutes'));
        $this->assertNotEmpty($track->json('order.eta_label'));
    }

    public function test_short_cash_requires_reason(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_DELIVERY_REQUIRE_POD, '0');
        Sanctum::actingAs($this->rider);
        $order = $this->makeOnTheWayOrder();
        $this->postJson("/api/delivery/runs/{$order->id}/deliver", [])->assertOk();

        $this->postJson("/api/delivery/orders/{$order->id}/collect-cash", [
            'amount' => 100,
        ])->assertStatus(422);

        $this->postJson("/api/delivery/orders/{$order->id}/collect-cash", [
            'amount' => 100,
            'notes' => 'Customer short on cash',
        ])->assertOk();

        $order->refresh();
        $this->assertFalse($order->isPaid());
        $this->assertGreaterThan(0, $order->amountDue());
    }

    public function test_withdraw_blocked_without_complete_payout_profile(): void
    {
        Sanctum::actingAs($this->rider);
        $this->rider->forceFill(['balance' => 0])->save();
        RiderAccountLedger::credit(
            (int) $this->rider->id,
            200,
            'test_topup',
            null,
            null,
            'Receivable without payout profile'
        );

        $account = $this->getJson('/api/delivery/account')->assertOk();
        $this->assertFalse((bool) $account->json('has_complete_payout_method'));

        $this->postJson('/api/delivery/account/withdraw', [
            'notes' => 'missing payout',
        ])->assertStatus(422);
    }

    public function test_profile_payout_methods_round_trip_and_withdraw_path(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_DELIVERY_REQUIRE_POD, '0');
        Sanctum::actingAs($this->rider);

        $this->patchJson('/api/delivery/profile', [
            'preferred_payout_channel' => PayoutChannel::BKASH,
            'payout_methods' => [
                'preferred' => PayoutChannel::BKASH,
                PayoutChannel::BKASH => ['mobile' => '01710123456'],
            ],
        ])->assertOk()
            ->assertJsonPath('user.preferred_payout_channel', PayoutChannel::BKASH)
            ->assertJsonPath('user.has_complete_payout_method', true);

        $order = $this->makeOnTheWayOrder();
        $this->postJson("/api/delivery/runs/{$order->id}/deliver", [])->assertOk();
        $this->postJson("/api/delivery/orders/{$order->id}/collect-cash", [
            'amount' => 200,
        ])->assertOk();
        $this->postJson('/api/delivery/cash-handovers', [
            'order_ids' => [$order->id],
            'target' => 'middo',
        ])->assertSuccessful();

        // Pending handover does not clear Due float until ops accepts.
        \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->rider->id)
            ->update(['balance' => 0]);

        RiderAccountLedger::credit(
            (int) $this->rider->id,
            40,
            'delivery_commission',
            'order',
            $order->id,
            'Commission after cycle'
        );

        $this->postJson('/api/delivery/account/withdraw', [
            'notes' => 'cycle withdraw',
        ])->assertSuccessful();
    }

    public function test_full_api_cycle_pickup_deliver_collect_handover_withdraw(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_DELIVERY_REQUIRE_POD, '0');
        Sanctum::actingAs($this->rider);

        $this->patchJson('/api/delivery/profile', [
            'preferred_payout_channel' => PayoutChannel::BKASH,
            'payout_methods' => [
                'preferred' => PayoutChannel::BKASH,
                PayoutChannel::BKASH => ['mobile' => '01710123456'],
            ],
        ])->assertOk();

        $order = $this->makePackedOrder();
        $this->assertSame(OrderTransition::PACKED, $order->order_status);

        // Livewire setup actors replace Sanctum; re-auth the rider for API calls.
        Sanctum::actingAs($this->rider);

        $this->postJson("/api/delivery/runs/{$order->id}/pickup", [])->assertOk()
            ->assertJsonPath('run.status', OrderTransition::ON_THE_WAY_TO_DELIVERY);

        $this->postJson("/api/delivery/runs/{$order->id}/deliver", [])->assertOk()
            ->assertJsonPath('run.status', OrderTransition::DELIVERED);

        $this->postJson("/api/delivery/orders/{$order->id}/collect-cash", [
            'amount' => 200,
        ])->assertOk();

        $this->postJson('/api/delivery/cash-handovers', [
            'order_ids' => [$order->id],
            'target' => 'middo',
        ])->assertSuccessful();

        // Pending handover does not clear Due float until ops accepts.
        \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $this->rider->id)
            ->update(['balance' => 0]);

        if (RiderAccountLedger::balance((int) $this->rider->id) <= 0) {
            RiderAccountLedger::credit(
                (int) $this->rider->id,
                40,
                'delivery_commission',
                'order',
                $order->id,
                'Commission after full API cycle'
            );
        }

        $this->postJson('/api/delivery/account/withdraw', [
            'notes' => 'full cycle withdraw',
        ])->assertSuccessful();
    }

    public function test_kitchen_to_ops_box_accept_and_hand_via_api(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_KITCHEN_TO_OPS_VIA_RIDER, '1');
        MiddoSettings::set('delivery.commission.'.DeliveryRunType::KITCHEN_TO_OPS, '33');

        $city = City::create(['name' => 'Dhaka HD']);
        $area = Area::create(['name' => 'Gulshan HD', 'city_id' => $city->id]);
        $this->kitchen->forceFill(['area_id' => $area->id])->save();
        $this->rider->forceFill([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'rider_shift_status' => 'on',
        ])->save();
        $this->rider->areas()->sync([$area->id]);

        $opsRole = Role::query()->where('name', 'operation')->firstOrFail();
        $ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'HD',
            'mobile' => '01932000099',
            'password' => 'password',
            'role_id' => $opsRole->id,
            'status' => 'active',
        ]);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-HD-K2O-'.uniqid(),
            'box_model_type' => 'standard_insulated',
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->kitchen->id,
            'asset_status' => 'active',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($this->kitchen)
            ->test(BoxesAtKitchen::class)
            ->call('sendToWarehouse', $box->id)
            ->assertSet('errorMessage', null);

        Livewire::actingAs($ops)
            ->test(MiddoBoxes::class)
            ->call('openAssignRider', $box->id, 'kitchen_to_ops')
            ->set('assignRiderId', $this->rider->id)
            ->call('saveAssignRider')
            ->assertSet('errorMessage', null);

        Livewire::actingAs($this->kitchen)
            ->test(BoxesAtKitchen::class)
            ->call('dispatchWarehouseRun', $box->id)
            ->assertSet('errorMessage', null);

        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'rider_id' => $this->rider->id,
            'status' => KitchenWarehouseHandoff::STATUS_DISPATCHED,
        ]);

        // Re-auth after Livewire kitchen/ops actors.
        Sanctum::actingAs($this->rider);
        $this->postJson("/api/delivery/boxes/{$box->id}/accept-kitchen-return", [])
            ->assertOk();

        $box->refresh();
        $this->assertSame($this->rider->id, $box->held_by_user_id);
        $this->assertNull($box->kitchen_id);

        $this->postJson("/api/delivery/boxes/{$box->id}/hand-to-ops", [])
            ->assertOk();

        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'status' => KitchenWarehouseHandoff::STATUS_HANDED_TO_OPS,
        ]);
    }

    public function test_accounts_can_post_hoc_adjust_rider_commission(): void
    {
        $accountsRole = Role::create(['name' => 'accounts']);
        $accounts = User::create([
            'first_name' => 'Accounts',
            'last_name' => 'HD',
            'mobile' => '01932000088',
            'password' => 'password',
            'role_id' => $accountsRole->id,
            'status' => 'active',
        ]);

        $before = RiderAccountLedger::balance((int) $this->rider->id);

        Livewire::actingAs($accounts)
            ->test(RiderMoneyApprovals::class)
            ->set('adjustRiderId', $this->rider->id)
            ->set('adjustDirection', 'credit')
            ->set('adjustAmount', '75')
            ->set('adjustReason', 'Post-hoc delivery commission correction')
            ->call('adjustCommission')
            ->assertSet('errorMessage', '');

        $this->assertSame($before + 75, RiderAccountLedger::balance((int) $this->rider->id));

        Livewire::actingAs($accounts)
            ->test(RiderMoneyApprovals::class)
            ->set('adjustRiderId', $this->rider->id)
            ->set('adjustDirection', 'debit')
            ->set('adjustAmount', '25')
            ->set('adjustReason', 'Clawback overpaid commission')
            ->call('adjustCommission')
            ->assertSet('errorMessage', '');

        $this->assertSame($before + 50, RiderAccountLedger::balance((int) $this->rider->id));
    }
}
