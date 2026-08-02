<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\OrderCancellation;
use App\Support\OrderCutoff;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderBusinessLogicTest extends TestCase
{
    use RefreshDatabase;

    private function corporateUser(array $overrides = []): User
    {
        $role = Role::firstOrCreate(['name' => 'corporate']);

        return User::create(array_merge([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'company_name' => 'Acme',
            'mobile' => '01310123452',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 1000,
        ], $overrides));
    }

    private function menu(): MenuItem
    {
        return MenuItem::create([
            'name' => 'Tehari',
            'summary' => 'Test',
            'price' => 500,
            'thumbnail' => 'img/menu/menu-1.jpg',
            'is_featured' => true,
            'display_order' => 1,
        ]);
    }

    public function test_amount_due_subtracts_discount(): void
    {
        $user = $this->corporateUser();
        $menu = $this->menu();

        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 500,
            'discount_amount' => 100,
            'amount_paid' => 400,
            'prepaid_amount' => 400,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'balance',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertSame(400, $order->netTotalAmount());
        $this->assertSame(0, $order->amountDue());
    }

    public function test_discounted_order_delivers_as_paid_without_cod(): void
    {
        $user = $this->corporateUser();
        $menu = $this->menu();

        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 500,
            'discount_amount' => 100,
            'amount_paid' => 400,
            'prepaid_amount' => 400,
            'address' => 'Gulshan',
            'order_status' => 'on_the_way_to_delivery',
            'payment_status' => 'paid',
            'payment_method' => 'balance',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Same oracle KitchenDispatches uses when choosing delivered vs delivered_and_paid.
        $status = $order->amountDue() === 0 ? 'delivered_and_paid' : 'delivered';
        $this->assertSame('delivered_and_paid', $status);
    }

    public function test_cancel_refunds_amount_paid_only(): void
    {
        $user = $this->corporateUser(['balance' => 200]);
        $menu = $this->menu();

        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 500,
            'discount_amount' => 50,
            'amount_paid' => 225,
            'prepaid_amount' => 225,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'balance',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $result = OrderCancellation::cancelPendingOwnedBy($user, $order->id);

        $this->assertSame(225, $result['refunded_amount']);
        $this->assertSame('cancelled', $order->fresh()->order_status);
        $this->assertSame(425, (int) $user->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_REFUND,
            'amount' => 225,
            'reference_id' => $order->id,
        ]);
    }

    public function test_cancel_denied_after_cutoff(): void
    {
        $user = $this->corporateUser();
        $menu = $this->menu();
        $today = now(OrderCutoff::timezone())->toDateString();

        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 500,
            'amount_paid' => 100,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'balance',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Carbon::setTestNow(OrderCutoff::forDate($today)->addMinute());

        try {
            OrderCancellation::cancelPendingOwnedBy($user, $order->id);
            $this->fail('Expected cancel after cutoff to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('order', $e->errors());
        }

        $this->assertSame('pending', $order->fresh()->order_status);
        $this->assertSame(1000, (int) $user->fresh()->balance);

        Carbon::setTestNow();
    }

    public function test_cancel_denied_for_non_pending_status(): void
    {
        $user = $this->corporateUser();
        $menu = $this->menu();

        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 500,
            'amount_paid' => 500,
            'address' => 'Gulshan',
            'order_status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'balance',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->expectException(ValidationException::class);
        OrderCancellation::cancelPendingOwnedBy($user, $order->id);
    }

    public function test_api_cancel_uses_shared_cancellation_and_refunds_paid_only(): void
    {
        $user = $this->corporateUser(['balance' => 50, 'mobile' => '01310123499']);
        $menu = $this->menu();
        Sanctum::actingAs($user);

        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 500,
            'amount_paid' => 250,
            'prepaid_amount' => 250,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'balance',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->deleteJson("/api/corporate/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('refunded_amount', 250);

        $this->assertSame('cancelled', $order->fresh()->order_status);
        $this->assertSame(300, (int) $user->fresh()->balance);
    }
}
