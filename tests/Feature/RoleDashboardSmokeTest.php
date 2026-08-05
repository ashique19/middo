<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleDashboardSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function roleDashboardProvider(): array
    {
        return [
            'admin' => ['admin', '/admin/dashboard'],
            'operation' => ['operation', '/operation/dashboard'],
            'kitchen' => ['kitchen', '/kitchen/dashboard'],
            'delivery' => ['delivery', '/delivery/dashboard'],
            'corporate' => ['corporate', '/corporate/dashboard'],
            'accounts' => ['accounts', '/accounts/dashboard'],
        ];
    }

    #[DataProvider('roleDashboardProvider')]
    public function test_each_role_can_open_its_dashboard(string $roleName, string $path): void
    {
        $role = Role::create(['name' => $roleName]);
        $user = User::create([
            'first_name' => ucfirst($roleName),
            'last_name' => 'Tester',
            'mobile' => '0171'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'balance' => 0,
        ]);

        $this->actingAs($user)
            ->get($path)
            ->assertOk();
    }

    public function test_staff_cannot_open_corporate_routes(): void
    {
        $role = Role::create(['name' => 'kitchen']);
        $user = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'Staff',
            'mobile' => '01718887766',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get('/corporate/orders/scheduled')
            ->assertRedirect(route('dashboard.redirect'));

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('kitchen.dashboard'));
    }

    public function test_dashboard_redirect_requires_auth_and_routes_corporate(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));

        $role = Role::create(['name' => 'corporate']);
        $user = User::create([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'mobile' => '01718887777',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('corporates.dashboard'));
    }
}
