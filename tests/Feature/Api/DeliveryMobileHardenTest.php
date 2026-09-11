<?php

namespace Tests\Feature\Api;

use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use App\Support\DeliveryPermissions;
use App\Support\MiddoSettings;
use App\Support\OrderTransition;
use App\Support\PayoutChannel;
use App\Support\RiderAccountLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

    private function makeOnTheWayOrder(): Order
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

        return LunchRunFlow::fromReadyToOnTheWay(
            $this->kitchen,
            $this->rider,
            $order->fresh(),
            $box
        );
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
}
