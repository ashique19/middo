<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\StaffAlert;
use App\Models\MiddoBox;
use App\Models\User;
use App\Support\OperationPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationMobileApiTest extends TestCase
{
    use RefreshDatabase;

    private Role $operationRole;

    private Role $kitchenRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operationRole = Role::query()->create(['name' => 'operation']);
        $this->kitchenRole = Role::query()->create(['name' => 'kitchen']);
        OperationPermissions::syncOperationRole($this->operationRole);
    }

    private function makeOps(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'first_name' => 'Ops',
            'last_name' => 'Lead',
            'mobile' => '01310123451',
            'password' => '12345678',
            'role_id' => $this->operationRole->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ], $overrides));
    }

    public function test_operation_can_login_and_receive_token(): void
    {
        $this->makeOps();

        $response = $this->postJson('/api/operation/login', [
            'mobile' => '01310123451',
            'password' => '12345678',
            'device_name' => 'ops-pixel',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.mobile', '01310123451')
            ->assertJsonPath('user.role', 'operation')
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'first_name', 'name']]);

        $this->assertSame('Bearer', $response->json('token_type'));
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_non_operation_cannot_login_to_operation_api(): void
    {
        User::query()->create([
            'first_name' => 'Kit',
            'last_name' => 'Chen',
            'mobile' => '01310123453',
            'password' => '12345678',
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ]);

        $this->postJson('/api/operation/login', [
            'mobile' => '01310123453',
            'password' => '12345678',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Login as Operation to continue.');
    }

    public function test_operation_me_requires_auth(): void
    {
        $this->getJson('/api/operation/me')->assertUnauthorized();
    }

    public function test_operation_me_dashboard_and_alerts(): void
    {
        $ops = $this->makeOps();

        StaffAlert::query()->create([
            'user_id' => $ops->id,
            'type' => StaffAlert::TYPE_NEEDS_REASSIGNMENT,
            'title' => 'Kitchen missed accept window',
            'body' => 'Group G-9 needs a kitchen.',
            'read_at' => null,
        ]);

        Sanctum::actingAs($ops);

        $this->getJson('/api/operation/me')
            ->assertOk()
            ->assertJsonPath('user.role', 'operation')
            ->assertJsonPath('user.mobile', '01310123451');

        $dashboard = $this->getJson('/api/operation/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'tiles',
                'today',
                'tomorrow',
                'attention',
                'money',
            ]);

        $keys = collect($dashboard->json('tiles'))->pluck('key')->all();
        $this->assertSame([
            'alerts',
            'sla',
            'awaiting_rider',
            'box_requests',
            'cash_handovers',
            'complaints',
        ], $keys);

        $this->getJson('/api/operation/alerts')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('alerts.0.title', 'Kitchen missed accept window');

        $alertId = (int) $this->getJson('/api/operation/alerts')->json('alerts.0.id');

        $this->patchJson("/api/operation/alerts/{$alertId}/read")
            ->assertOk();

        $this->getJson('/api/operation/alerts')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_operation_can_register_device_token(): void
    {
        $ops = $this->makeOps();
        Sanctum::actingAs($ops);

        $this->postJson('/api/operation/device-tokens', [
            'token' => str_repeat('a', 40),
            'platform' => 'android',
            'device_name' => 'ops-pixel',
        ])->assertOk()
            ->assertJsonPath('message', 'Device token registered.');
    }

    public function test_phase1_and_phase2_read_endpoints_return_ok(): void
    {
        $ops = $this->makeOps();
        Sanctum::actingAs($ops);

        $this->getJson('/api/operation/boxes')
            ->assertOk()
            ->assertJsonStructure(['summary', 'custody', 'boxes', 'meta']);

        $this->getJson('/api/operation/boxes/requests')
            ->assertOk()
            ->assertJsonStructure(['requests']);

        $this->getJson('/api/operation/riders/board')
            ->assertOk()
            ->assertJsonStructure([
                'counts',
                'riders',
                'awaiting_accept',
                'on_the_way',
                'box_custody',
                'custom_runs',
            ]);

        $this->getJson('/api/operation/cash-handovers')
            ->assertOk()
            ->assertJsonStructure(['handovers']);

        $this->getJson('/api/operation/sla')
            ->assertOk()
            ->assertJsonStructure([
                'counts',
                'unassigned_groups',
                'late_to_pack',
                'kitchen_hints',
                'kitchens',
            ]);

        $this->getJson('/api/operation/complaints')
            ->assertOk()
            ->assertJsonStructure(['complaints']);

        $this->getJson('/api/operation/ops-day')
            ->assertOk()
            ->assertJsonStructure(['date', 'sections', 'totals']);

        $this->getJson('/api/operation/orders/search?q=999999')
            ->assertOk()
            ->assertJsonPath('orders', []);
    }


    public function test_operation_can_lookup_box_by_qr(): void
    {
        $ops = $this->makeOps();
        Sanctum::actingAs($ops);

        MiddoBox::create([
            'qr_code_id' => 'MB-OPS-LOOKUP-1',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);

        $this->getJson('/api/operation/boxes/lookup?qr=MB-OPS-LOOKUP-1')
            ->assertOk()
            ->assertJsonPath('box.qr_code_id', 'MB-OPS-LOOKUP-1');

        $this->getJson('/api/operation/boxes/lookup?qr=MB-DOES-NOT-EXIST')
            ->assertNotFound();

        $this->getJson('/api/operation/boxes/lookup')
            ->assertStatus(422);
    }

    public function test_operation_can_mark_alerts_read(): void
    {
        $ops = $this->makeOps(['mobile' => '01310999001']);
        Sanctum::actingAs($ops);

        $alert = StaffAlert::query()->create([
            'user_id' => $ops->id,
            'type' => StaffAlert::TYPE_NEEDS_REASSIGNMENT,
            'title' => 'Pilot rider',
            'body' => 'Order waiting',
        ]);

        $this->getJson('/api/operation/alerts')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->patchJson('/api/operation/alerts/'.$alert->id.'/read')
            ->assertOk();

        $this->getJson('/api/operation/alerts')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        StaffAlert::query()->create([
            'user_id' => $ops->id,
            'type' => StaffAlert::TYPE_NEEDS_REASSIGNMENT,
            'title' => 'Another',
            'body' => 'Body',
        ]);

        $this->postJson('/api/operation/alerts/read-all')
            ->assertOk();

        $this->getJson('/api/operation/alerts')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_operation_boards_endpoint_returns_serial_sections(): void
    {
        $ops = $this->makeOps();
        Sanctum::actingAs($ops);

        $response = $this->getJson('/api/operation/boards?date='.now()->toDateString());

        $response->assertOk()
            ->assertJsonStructure([
                'date',
                'packages' => ['unassigned_meals' => ['count', 'items'], 'orders' => ['count', 'items']],
                'orders' => ['all', 'package', 'individual'],
                'grouping' => [
                    'ungrouped',
                    'grouped_pending',
                    'accepted',
                    'packed',
                    'picked',
                    'delivered',
                    'failed',
                ],
                'cash_collection' => ['at_rider', 'kitchen', 'middo'],
                'box_requests' => ['count', 'items'],
                'complaints' => ['count', 'items'],
                'alerts_unread',
            ]);
    }
}
