<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\KitchenPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleRouteCatalogSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Primary index/list routes that do not require model IDs.
     *
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function roleRouteProvider(): array
    {
        return [
            'admin' => ['admin', [
                'admin.dashboard',
                'admin.alerts.index',
                'admin.kitchens.active',
                'admin.kitchens.onboarding',
                'admin.menu.index',
                'admin.meal-items.index',
                'admin.packages.index',
                'admin.coupons.index',
                'admin.charges.index',
                'admin.subscriptions.index',
                'admin.packages.demand',
                'admin.packages.insights',
                'admin.orders.active',
                'admin.orders.history',
                'admin.orders.search',
                'admin.navrole.index',
                'admin.settings.index',
                'admin.middo-cash',
                'admin.cash-handovers',
                'admin.sla.index',
                'admin.riders.index',
                'admin.areas.index',
                'admin.coverage.index',
                'admin.complaints.index',
                'admin.accounts.index',
                'admin.cod-recon.index',
                'admin.operating-costs.index',
                'admin.kitchen-money.index',
                'admin.rider-money.index',
                'admin.custom-runs.index',
                'admin.corporates.index',
                'admin.users.admin',
                'admin.users.operation',
                'admin.users.accounts',
                'admin.users.kitchen',
                'admin.users.corporate',
                'admin.users.delivery',
            ]],
            'operation' => ['operation', [
                'operation.dashboard',
                'operation.alerts.index',
                'operation.corporates.index',
                'operation.kitchens.index',
                'operation.menu.index',
                'operation.meal-items.index',
                'operation.packages.index',
                'operation.subscriptions.index',
                'operation.packages.demand',
                'operation.packages.insights',
                'operation.orders.active',
                'operation.orders.history',
                'operation.orders.search',
                'operation.middo-boxes.index',
                'operation.middo-cash',
                'operation.cash-handovers',
                'operation.sla.index',
                'operation.riders.index',
                'operation.areas.index',
                'operation.coverage.index',
                'operation.complaints.index',
                'operation.accounts.index',
                'operation.cod-recon.index',
                'operation.operating-costs.index',
                'operation.kitchen-money.index',
                'operation.rider-money.index',
                'operation.custom-runs.index',
            ]],
            'kitchen' => ['kitchen', [
                'kitchen.dashboard',
                'kitchen.alerts',
                'kitchen.orders.this-month',
                'kitchen.orders.last-three-months',
                'kitchen.orders.active',
                'kitchen.menus.today',
                'kitchen.prep.shopping-list',
                'kitchen.order-groups.middo',
                'kitchen.middo-boxes.at-kitchen',
                'kitchen.middo-boxes.incoming',
                'kitchen.cash-handovers',
                'kitchen.account',
                'kitchen.complaints',
                'kitchen.profile',
            ]],
            'delivery' => ['delivery', [
                'delivery.dashboard',
                'delivery.kitchen-dispatches',
                'delivery.middo-boxes.pending-run',
                'delivery.orders.delivered',
                'delivery.cash-handovers',
                'delivery.custom-runs',
                'delivery.account',
                'delivery.alerts',
            ]],
            'corporate' => ['corporate', [
                'corporates.dashboard',
                'corporates.packages.index',
                'corporates.orders.scheduled',
                'corporates.orders.history',
                'corporates.wallet',
                'corporates.profile',
                'corporates.change-password',
            ]],
            'accounts' => ['accounts', [
                'accounts.dashboard',
                'accounts.accounts.index',
                'accounts.middo-cash',
                'accounts.cash-handovers',
                'accounts.cod-recon.index',
                'accounts.operating-costs.index',
                'accounts.kitchen-money.index',
                'accounts.rider-money.index',
                'accounts.corporates.index',
            ]],
        ];
    }

    #[DataProvider('roleRouteProvider')]
    public function test_role_can_open_primary_routes(string $roleName, array $routeNames): void
    {
        foreach (['admin', 'operation', 'accounts', 'kitchen', 'corporate', 'delivery'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }

        $role = Role::query()->where('name', $roleName)->firstOrFail();

        if ($roleName === 'kitchen') {
            KitchenPermissions::syncKitchenRole($role);
        }

        $user = User::create([
            'first_name' => ucfirst($roleName),
            'last_name' => 'Catalog',
            'mobile' => '0180'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'balance' => 0,
            'company_name' => $roleName === 'corporate' ? 'Catalog Corp' : null,
        ]);

        foreach ($routeNames as $routeName) {
            $response = $this->actingAs($user)->get(route($routeName));
            $this->assertSame(
                200,
                $response->status(),
                "Expected OK for {$roleName} route {$routeName}, got {$response->status()}"
            );
        }
    }
}
