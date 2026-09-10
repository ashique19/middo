<?php

namespace Tests\Feature\Api;

use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use App\Support\DeliveryPermissions;
use App\Support\DeliveryPodOtp;
use App\Support\MiddoSettings;
use App\Support\OrderTransition;
use App\Support\RiderAccountLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\LunchRunFlow;
use Tests\TestCase;

class DeliveryMobileNextWaveTest extends TestCase
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
            'last_name' => 'NW',
            'mobile' => '01931000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'NW',
            'mobile' => '01931000002',
            'password' => 'password',
            'role_id' => $this->deliveryRole->id,
            'status' => 'active',
            'rider_shift_status' => 'on',
            'balance' => 0,
        ]);
        $this->customer = User::create([
            'first_name' => 'Corp',
            'last_name' => 'NW',
            'mobile' => '01931000003',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);
        $this->menu = MenuItem::create([
            'name' => 'NW Lunch',
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
            'receiver_mobile' => '01931000003',
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-NW-'.uniqid(),
            'menu_id' => $this->menu->id,
            'delivery_date' => $today,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);
        OrderTransition::apply($order->fresh(), OrderTransition::PROCESSING);
        OrderTransition::apply($order->fresh(), OrderTransition::READY);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-NW-'.uniqid(),
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

    public function test_deliver_requires_otp_and_accepts_pod_photo(): void
    {
        Storage::fake('public');
        MiddoSettings::set(MiddoSettings::KEY_DELIVERY_REQUIRE_POD, '1');

        $order = $this->makeOnTheWayOrder();
        Sanctum::actingAs($this->rider);

        $this->postJson("/api/delivery/runs/{$order->id}/deliver", [])
            ->assertStatus(422);

        $otpResponse = $this->postJson("/api/delivery/runs/{$order->id}/send-delivery-otp")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $otp = (string) ($otpResponse->json('debug_otp') ?? '1234');

        $this->post("/api/delivery/runs/{$order->id}/deliver", [
            'otp' => $otp,
            'pod_photo' => UploadedFile::fake()->image('pod.jpg', 400, 400),
        ], [
            'Accept' => 'application/json',
        ])->assertOk();

        $order->refresh();
        $this->assertNotNull($order->pod_verified_at);
        $this->assertNotNull($order->pod_photo_path);
        $this->assertTrue(in_array($order->order_status, [
            OrderTransition::DELIVERED,
            OrderTransition::DELIVERED_AND_PAID,
        ], true));
    }

    public function test_collect_cash_returns_due_split_and_idempotent_replay(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_DELIVERY_REQUIRE_POD, '0');
        $order = $this->makeOnTheWayOrder();
        Sanctum::actingAs($this->rider);

        $deliver = $this->postJson("/api/delivery/runs/{$order->id}/deliver", []);
        if ($deliver->status() !== 200) {
        }
        $deliver->assertOk();

        $list = $this->getJson('/api/delivery/orders/delivered')->assertOk();
        $row = collect($list->json('orders'))->firstWhere('id', $order->id);
        $this->assertNotNull($row);
        $this->assertSame(200, (int) $row['cash_due']);
        $this->assertSame(40, (int) $row['commission_open']);
        $this->assertSame(40, (int) $row['projected_commission']);
        $this->assertSame(160, (int) $row['projected_due_to_middo']);

        $headers = ['Idempotency-Key' => 'cash-collect-nw-1'];

        $first = $this->withHeaders($headers)->postJson("/api/delivery/orders/{$order->id}/collect-cash", [
            'amount' => 200,
        ]);
        $first->assertOk();

        $this->assertSame(160, (int) $first->json('due_to_middo'));
        $this->assertSame(40, (int) $first->json('commission'));

        $second = $this->withHeaders($headers)->postJson("/api/delivery/orders/{$order->id}/collect-cash", [
            'amount' => 200,
        ])->assertOk();

        $this->assertSame($first->json('due_to_middo'), $second->json('due_to_middo'));
        $this->assertSame(160, (int) $this->rider->fresh()->balance);
    }

    public function test_cash_handover_requires_order_ids_and_target(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_DELIVERY_REQUIRE_POD, '0');
        $order = $this->makeOnTheWayOrder();
        Sanctum::actingAs($this->rider);
        $this->postJson("/api/delivery/runs/{$order->id}/deliver", [])->assertOk();
        $this->postJson("/api/delivery/orders/{$order->id}/collect-cash", ['amount' => 200])->assertOk();

        $this->postJson('/api/delivery/cash-handovers', [
            'amount' => 160,
        ])->assertStatus(422);

        $eligible = $this->getJson('/api/delivery/cash-handovers')->assertOk();
        $this->assertNotEmpty($eligible->json('eligible_orders'));

        $this->postJson('/api/delivery/cash-handovers', [
            'order_ids' => [$order->id],
            'target' => 'middo',
            'notes' => 'NW handover',
        ])->assertSuccessful()
            ->assertJsonPath('handover.target', 'middo');
    }

    public function test_withdraw_blocked_while_due_to_middo_positive(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_DELIVERY_REQUIRE_POD, '0');
        Sanctum::actingAs($this->rider);

        // Due float lives on users.balance; receivable alone is not withdrawable.
        $this->rider->forceFill(['balance' => 150])->save();
        RiderAccountLedger::credit(
            (int) $this->rider->id,
            500,
            'test_topup',
            null,
            null,
            'Test receivable'
        );

        $account = $this->getJson('/api/delivery/account')->assertOk();
        $due = (int) ($account->json('due_to_middo') ?? $account->json('cash_on_hand') ?? 0);
        $this->assertGreaterThan(0, $due);
        $this->assertFalse((bool) $account->json('can_request_payment'));

        $this->postJson('/api/delivery/account/withdraw', [
            'notes' => 'should fail',
        ])->assertStatus(422);
    }


    public function test_pending_boxes_includes_requests_alias(): void
    {
        Sanctum::actingAs($this->rider);

        $payload = $this->getJson('/api/delivery/boxes/pending')->assertOk();
        $this->assertArrayHasKey('boxes', $payload->json());
        $this->assertArrayHasKey('run_groups', $payload->json());
        $this->assertArrayHasKey('requests', $payload->json());
        $this->assertSame($payload->json('run_groups'), $payload->json('requests'));
    }

    public function test_otp_helper_round_trip(): void
    {
        $otp = DeliveryPodOtp::generate(99);
        $this->assertTrue(DeliveryPodOtp::verify(99, $otp));
        $this->assertFalse(DeliveryPodOtp::verify(99, $otp));
    }
}
