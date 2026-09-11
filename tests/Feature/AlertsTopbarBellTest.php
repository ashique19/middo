<?php

namespace Tests\Feature;

use App\Models\Nav;
use App\Models\Role;
use App\Models\StaffAlert;
use App\Models\User;
use App\Support\StaffNavStructure;
use App\Support\StaffNavSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertsTopbarBellTest extends TestCase
{
    use RefreshDatabase;

    private function seedRole(string $name): Role
    {
        return Role::query()->firstOrCreate(['name' => $name]);
    }

    private function user(string $roleName): User
    {
        $role = $this->seedRole($roleName);

        return User::query()->create([
            'first_name' => ucfirst($roleName),
            'last_name' => 'Staff',
            'mobile' => '017'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'password' => 'password',
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    public function test_alerts_are_not_sidebar_leaves_after_nav_sync(): void
    {
        foreach (StaffNavStructure::roleNames() as $name) {
            $this->seedRole($name);
        }

        StaffNavSync::syncAll();

        foreach (['admin', 'operation', 'kitchen', 'delivery'] as $roleName) {
            $roleId = (int) Role::query()->where('name', $roleName)->value('id');
            $this->assertFalse(
                Nav::query()
                    ->where('role_id', $roleId)
                    ->where('title', 'Alerts')
                    ->exists(),
                "Expected no sidebar Alerts leaf for {$roleName}"
            );
        }
    }

    public function test_operation_dashboard_shows_topbar_alerts_bell_with_unread_badge(): void
    {
        $ops = $this->user('operation');
        StaffNavSync::syncAll();

        StaffAlert::query()->create([
            'user_id' => $ops->id,
            'type' => StaffAlert::TYPE_GROUP_ASSIGNED,
            'title' => 'Needs attention',
            'body' => 'A kitchen missed the accept window.',
            'read_at' => null,
        ]);

        $this->actingAs($ops)
            ->get(route('operation.dashboard'))
            ->assertOk()
            ->assertSee(route('operation.alerts.index'), false)
            ->assertSee('aria-label="Alerts (1 unread)"', false)
            ->assertDontSee('>Alerts</span>', false);
    }

    public function test_admin_dashboard_shows_topbar_alerts_bell(): void
    {
        $admin = $this->user('admin');
        StaffNavSync::syncAll();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.alerts.index'), false)
            ->assertSee('aria-label="Alerts"', false);
    }
}
