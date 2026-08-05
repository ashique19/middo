<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSurfaceSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'operation', 'accounts', 'kitchen', 'corporate', 'delivery'] as $name) {
            Role::create(['name' => $name]);
        }

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Surface',
            'mobile' => '01990000001',
            'password' => '12345678',
            'role_id' => Role::query()->where('name', 'admin')->value('id'),
            'status' => 'active',
        ]);
    }

    public function test_admin_only_surfaces_are_reachable(): void
    {
        $routes = [
            'admin.users.operation',
            'admin.users.kitchen',
            'admin.users.accounts',
            'admin.users.delivery',
            'admin.users.corporate',
            'admin.kitchens.onboarding',
            'admin.coupons.index',
            'admin.charges.index',
            'admin.settings.index',
            'admin.navrole.index',
        ];

        foreach ($routes as $name) {
            $this->actingAs($this->admin)
                ->get(route($name))
                ->assertOk("Expected OK for {$name}");
        }
    }

    public function test_operation_cannot_open_admin_only_surfaces(): void
    {
        $ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'Blocked',
            'mobile' => '01990000002',
            'password' => '12345678',
            'role_id' => Role::query()->where('name', 'operation')->value('id'),
            'status' => 'active',
        ]);

        $this->actingAs($ops)
            ->get(route('admin.settings.index'))
            ->assertRedirect(route('dashboard.redirect'));

        $this->actingAs($ops)
            ->get(route('admin.coupons.index'))
            ->assertRedirect(route('dashboard.redirect'));

        $this->actingAs($ops)
            ->get(route('admin.navrole.index'))
            ->assertRedirect(route('dashboard.redirect'));
    }
}
