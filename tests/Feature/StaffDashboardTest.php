<?php

namespace Tests\Feature;

use App\Livewire\Shared\StaffDashboard;
use App\Models\KitchenBoxRequest;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PackageSubscription;
use App\Models\Role;
use App\Models\User;
use App\Support\OpsDashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name]);
    }

    private function user(string $roleName, array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => ucfirst($roleName),
            'last_name' => 'User',
            'mobile' => '01310'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'password' => '12345678',
            'role_id' => $this->role($roleName)->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 0,
        ], $overrides));
    }

    public function test_admin_and_operation_dashboards_render_kpis(): void
    {
        $admin = $this->user('admin', ['mobile' => '01310999001']);
        $ops = $this->user('operation', ['mobile' => '01310999002']);
        $corporate = $this->user('corporate', ['mobile' => '01310999003']);
        $menu = MenuItem::create(['name' => 'Thali', 'price' => 200]);

        Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 3,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 600,
            'amount_paid' => 0,
            'address' => 'Test',
            'receiver_name' => 'R',
            'receiver_mobile' => '01710123456',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        Livewire::actingAs($admin)
            ->test(StaffDashboard::class)
            ->assertSee('Admin dashboard')
            ->assertSee('Today')
            ->assertSee('Active pipeline')
            ->assertSee('Needs attention');

        Livewire::actingAs($ops)
            ->test(StaffDashboard::class)
            ->assertSee('Operations dashboard')
            ->assertSee('Upcoming delivery days')
            ->assertSee('Money snapshot');

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $this->actingAs($ops)->get('/operation/dashboard')->assertOk();
    }

    public function test_metrics_count_today_meals_and_ungrouped(): void
    {
        $corporate = $this->user('corporate');
        $menu = MenuItem::create(['name' => 'Bowl', 'price' => 150]);

        Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 2,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 300,
            'amount_paid' => 0,
            'address' => 'Test',
            'receiver_name' => 'R',
            'receiver_mobile' => '01710123456',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        $metrics = OpsDashboardMetrics::forRole('operation');

        $this->assertSame(2, $metrics['today']['qty']);
        $this->assertSame(1, $metrics['today']['orders']);
        $this->assertSame(1, $metrics['pipeline']['ungrouped']);
        $this->assertNotEmpty($metrics['attention']);
        $this->assertSame('Ungrouped active orders', $metrics['attention'][0]['label']);
    }

    public function test_admin_metrics_include_pending_kitchen_attention(): void
    {
        $this->user('kitchen', [
            'mobile' => '01310999111',
            'status' => 'pending',
        ]);

        $metrics = OpsDashboardMetrics::forRole('admin');
        $labels = collect($metrics['attention'])->pluck('label')->all();

        $this->assertContains('Kitchens pending onboarding', $labels);
        $this->assertSame(1, $metrics['pending_kitchens']);
        $this->assertNotEmpty($metrics['users_by_role']);
    }

    public function test_package_awaiting_schedule_surfaces_on_dashboard(): void
    {
        $corporate = $this->user('corporate', ['balance' => 10000]);
        $admin = $this->user('admin', ['mobile' => '01310999222']);

        $package = MealPackage::create([
            'name' => 'Classic',
            'summary' => 'Test',
            'price_per_day' => 100,
            'diet_tag' => 'classic',
            'duration_days' => 20,
            'start_date' => now('Asia/Dhaka')->startOfMonth()->toDateString(),
            'end_date' => now('Asia/Dhaka')->startOfMonth()->addYear()->toDateString(),
            'status' => MealPackage::STATUS_PUBLISHED,
            'display_order' => 1,
            'created_by' => $admin->id,
        ]);

        PackageSubscription::create([
            'user_id' => $corporate->id,
            'meal_package_id' => $package->id,
            'quantity' => 1,
            'start_date' => now('Asia/Dhaka')->startOfMonth()->toDateString(),
            'end_date' => now('Asia/Dhaka')->endOfMonth()->toDateString(),
            'target_month' => now('Asia/Dhaka')->format('Y-m'),
            'omitted_weekdays' => [5, 6],
            'billable_days' => 20,
            'price_per_day' => 100,
            'total_amount' => 2000,
            'amount_paid' => 2000,
            'payment_status' => 'paid',
            'status' => PackageSubscription::STATUS_ACTIVE,
            'schedule_status' => PackageSubscription::SCHEDULE_AWAITING,
            'delivery_time' => '12:00 PM',
            'address' => 'Addr',
            'receiver_name' => 'R',
            'receiver_mobile' => '01710123456',
            'created_by' => $corporate->id,
        ]);

        $metrics = OpsDashboardMetrics::forRole('operation');
        $this->assertSame(1, $metrics['packages']['awaiting_schedule']);
        $this->assertSame(2000, $metrics['packages']['prepaid_revenue']);
        $this->assertContains(
            'Packages awaiting schedule',
            collect($metrics['attention'])->pluck('label')->all()
        );
    }

    public function test_open_box_requests_surface_on_operation_dashboard(): void
    {
        $ops = $this->user('operation', ['mobile' => '01310999333']);
        $kitchen = $this->user('kitchen', ['mobile' => '01310999334']);

        KitchenBoxRequest::create([
            'kitchen_id' => $kitchen->id,
            'quantity' => 4,
            'allocated_qty' => 1,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $kitchen->id,
        ]);

        KitchenBoxRequest::create([
            'kitchen_id' => $kitchen->id,
            'quantity' => 2,
            'allocated_qty' => 0,
            'status' => KitchenBoxRequest::STATUS_CANCELLED,
            'requested_by' => $kitchen->id,
        ]);

        $metrics = OpsDashboardMetrics::forRole('operation');
        $this->assertSame(1, $metrics['box_requests']['open']);
        $this->assertSame(3, $metrics['box_requests']['remaining_qty']);
        $this->assertContains(
            'Open kitchen box requests',
            collect($metrics['attention'])->pluck('label')->all()
        );

        Livewire::actingAs($ops)
            ->test(StaffDashboard::class)
            ->assertSee('Box Req (1)', false)
            ->assertSee('boxes still needed', false);
    }
}
