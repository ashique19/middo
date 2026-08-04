<?php

namespace Tests\Feature\Ops;

use App\Livewire\Accounts\Dashboard as AccountsDashboard;
use App\Livewire\Shared\AccountsHub;
use App\Livewire\Shared\MiddoCashLedgerPage;
use App\Models\Role;
use App\Models\User;
use App\Support\StaffPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class OpsClearGateO9Test extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected User $accounts;

    protected function setUp(): void
    {
        parent::setUp();

        $opsRole = Role::create(['name' => 'operation']);
        Role::create(['name' => 'admin']);
        $accountsRole = Role::create(['name' => 'accounts']);
        Role::create(['name' => 'kitchen']);
        Role::create(['name' => 'corporate']);
        Role::create(['name' => 'delivery']);

        $this->ops = User::create([
            'first_name' => 'Ops', 'last_name' => 'O9', 'mobile' => '01913000001',
            'password' => 'password', 'role_id' => $opsRole->id, 'status' => 'active',
        ]);
        $this->accounts = User::create([
            'first_name' => 'Accounts', 'last_name' => 'O9', 'mobile' => '01913000002',
            'password' => 'password', 'role_id' => $accountsRole->id, 'status' => 'active',
        ]);
    }

    public function test_o0_o8_money_surface_routes_exist(): void
    {
        $gate = json_decode(file_get_contents(base_path('docs/ops-clear-gate.json')), true);
        $this->assertSame('cleared', $gate['status']);

        $routes = [];
        foreach ($gate['money_surfaces'] as $surface) {
            foreach ($surface['routes'] as $name) {
                $routes[] = $name;
            }
        }

        foreach (array_unique($routes) as $name) {
            $this->assertTrue(Route::has($name), "Missing money surface route: {$name}");
        }
    }

    public function test_ops_still_reaches_core_money_pages(): void
    {
        Livewire::actingAs($this->ops)
            ->test(AccountsHub::class)
            ->assertSuccessful();

        Livewire::actingAs($this->ops)
            ->test(MiddoCashLedgerPage::class)
            ->assertSuccessful();

        $this->assertTrue(StaffPortal::canAccessMoney('operation'));
        $this->assertTrue(StaffPortal::isDayOps('operation'));
    }

    public function test_accounts_a0_shell_dashboard_and_money_pages(): void
    {
        $this->assertTrue(StaffPortal::canAccessMoney('accounts'));
        $this->assertFalse(StaffPortal::isDayOps('accounts'));

        foreach ([
            'accounts.dashboard',
            'accounts.accounts.index',
            'accounts.middo-cash',
            'accounts.cod-recon.index',
            'accounts.operating-costs.index',
            'accounts.kitchen-money.index',
            'accounts.rider-money.index',
            'accounts.orders.show',
        ] as $route) {
            $this->assertTrue(Route::has($route), "Missing A0 route: {$route}");
        }

        Livewire::actingAs($this->accounts)
            ->test(AccountsDashboard::class)
            ->assertSee('Accounts')
            ->assertSee('Middo cash');

        Livewire::actingAs($this->accounts)
            ->test(AccountsHub::class)
            ->assertSuccessful();

        Livewire::actingAs($this->accounts)
            ->test(MiddoCashLedgerPage::class)
            ->assertSuccessful();

        $this->actingAs($this->accounts)
            ->get(route('dashboard.redirect'))
            ->assertRedirect(route('accounts.dashboard'));
    }

    public function test_accounts_cannot_open_ops_day_routes(): void
    {
        $this->actingAs($this->accounts)
            ->get(route('operation.cash-handovers'))
            ->assertRedirect(route('dashboard.redirect'));
    }
}
