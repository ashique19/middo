<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use App\Support\DeliveryPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryMobileApiTest extends TestCase
{
    use RefreshDatabase;

    private Role $deliveryRole;

    private Role $corporateRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deliveryRole = Role::create(['name' => 'delivery']);
        $this->corporateRole = Role::create(['name' => 'corporate']);
        DeliveryPermissions::syncDeliveryRole($this->deliveryRole);
    }

    private function makeRider(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Demo',
            'last_name' => 'Rider',
            'mobile' => '01310123454',
            'password' => '12345678',
            'role_id' => $this->deliveryRole->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 0,
            'rider_shift_status' => 'on',
            'address' => 'Rider Depot',
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

    public function test_delivery_can_login_and_receive_token(): void
    {
        $this->makeRider();

        $response = $this->postJson('/api/delivery/login', [
            'mobile' => '01310123454',
            'password' => '12345678',
            'device_name' => 'delivery-pixel',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.mobile', '01310123454')
            ->assertJsonPath('user.role', 'delivery')
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'first_name', 'name', 'rider_shift_status']]);

        $this->assertSame('Bearer', $response->json('token_type'));
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_non_delivery_cannot_login_to_delivery_api(): void
    {
        $this->makeCorporate();

        $this->postJson('/api/delivery/login', [
            'mobile' => '01310123452',
            'password' => '12345678',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Login as Delivery to continue.');
    }

    public function test_delivery_me_requires_auth(): void
    {
        $this->getJson('/api/delivery/me')->assertUnauthorized();
    }

    public function test_delivery_me_dashboard_and_shift(): void
    {
        $rider = $this->makeRider();

        Sanctum::actingAs($rider);

        $this->getJson('/api/delivery/me')
            ->assertOk()
            ->assertJsonPath('user.role', 'delivery')
            ->assertJsonPath('user.mobile', '01310123454')
            ->assertJsonPath('shift_status', 'on')
            ->assertJsonPath('can_accept_new_runs', true);

        $dashboard = $this->getJson('/api/delivery/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'tiles',
                'shift_status',
                'shift_options',
            ]);

        $keys = collect($dashboard->json('tiles'))->pluck('key')->all();
        $this->assertSame([
            'alerts',
            'runs',
            'custom_runs',
            'boxes',
            'delivered',
            'cash',
        ], $keys);

        $this->postJson('/api/delivery/shift', ['status' => 'off'])
            ->assertOk()
            ->assertJsonPath('shift_status', 'off')
            ->assertJsonPath('can_accept_new_runs', false);

        $this->assertSame('off', $rider->fresh()->rider_shift_status);
    }

    public function test_delivery_runs_list_empty_ok(): void
    {
        $rider = $this->makeRider();
        Sanctum::actingAs($rider);

        $this->getJson('/api/delivery/runs')
            ->assertOk()
            ->assertJsonPath('runs', [])
            ->assertJsonStructure(['runs', 'meta']);
    }

    public function test_delivery_can_register_device_token(): void
    {
        $rider = $this->makeRider();
        Sanctum::actingAs($rider);

        $token = str_repeat('b', 40);

        $this->postJson('/api/delivery/device-tokens', [
            'token' => $token,
            'platform' => 'android',
            'device_name' => 'delivery-1',
        ])->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $rider->id,
            'token' => $token,
            'platform' => 'android',
        ]);

        $this->deleteJson('/api/delivery/device-tokens', ['token' => $token])
            ->assertOk();

        $this->assertDatabaseMissing('device_tokens', [
            'token' => $token,
        ]);
    }

    public function test_corporate_token_cannot_access_delivery_routes(): void
    {
        $corporate = $this->makeCorporate();
        Sanctum::actingAs($corporate);

        $this->getJson('/api/delivery/dashboard')->assertForbidden();
    }
}
