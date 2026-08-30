<?php

namespace Tests\Feature;

use App\Jobs\SendStaffAlertPush;
use App\Models\DeviceToken;
use App\Models\MenuItem;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\StaffAlert;
use App\Models\User;
use App\Support\StaffAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StaffAlertPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_alert_dispatches_fcm_push_job(): void
    {
        Queue::fake();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $kitchen = User::create([
            'first_name' => 'Test',
            'last_name' => 'Kitchen',
            'mobile' => '01770000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);

        DeviceToken::create([
            'user_id' => $kitchen->id,
            'token' => 'fcm-kitchen-alert-token-abcdefghijklmnopqrstuvwxyz',
            'platform' => 'android',
            'device_name' => 'pixel-kitchen',
        ]);

        $menu = MenuItem::create([
            'name' => 'Lunch Box',
            'price' => 250,
            'kitchen_commission' => 40,
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-PUSH-'.uniqid(),
            'menu_id' => $menu->id,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'kitchen_id' => null,
        ]);

        $alert = StaffAlerts::notifyKitchenAssigned($group, $kitchen);

        $this->assertInstanceOf(StaffAlert::class, $alert);
        Queue::assertPushed(SendStaffAlertPush::class, function (SendStaffAlertPush $job) use ($alert) {
            return $job->alertId === $alert->id;
        });
    }

    public function test_deduped_alert_does_not_dispatch_push_again(): void
    {
        Queue::fake();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $kitchen = User::create([
            'first_name' => 'Test',
            'last_name' => 'Kitchen',
            'mobile' => '01770000002',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);

        $menu = MenuItem::create([
            'name' => 'Lunch Box',
            'price' => 250,
            'kitchen_commission' => 40,
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-DEDUP-'.uniqid(),
            'menu_id' => $menu->id,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'kitchen_id' => null,
        ]);

        $first = StaffAlerts::notifyKitchenAssigned($group, $kitchen);
        $second = StaffAlerts::notifyKitchenAssigned($group, $kitchen);

        $this->assertInstanceOf(StaffAlert::class, $first);
        $this->assertNull($second);
        Queue::assertPushed(SendStaffAlertPush::class, 1);
    }
}
