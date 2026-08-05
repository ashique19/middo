<?php

namespace Tests\Feature\Accounts;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsSurfaceSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $accounts;

    protected User $corporate;

    protected function setUp(): void
    {
        parent::setUp();

        $accountsRole = Role::create(['name' => 'accounts']);
        $corporateRole = Role::create(['name' => 'corporate']);
        Role::create(['name' => 'operation']);
        Role::create(['name' => 'admin']);

        $this->accounts = User::create([
            'first_name' => 'Accounts',
            'last_name' => 'Surface',
            'mobile' => '01992000001',
            'password' => '12345678',
            'role_id' => $accountsRole->id,
            'status' => 'active',
        ]);

        $this->corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'A',
            'company_name' => 'Acme',
            'mobile' => '01992000002',
            'password' => '12345678',
            'role_id' => $corporateRole->id,
            'status' => 'active',
            'balance' => 1000,
        ]);
    }

    public function test_all_accounts_routes_return_ok(): void
    {
        foreach ([
            'accounts.dashboard',
            'accounts.accounts.index',
            'accounts.middo-cash',
            'accounts.cash-handovers',
            'accounts.cod-recon.index',
            'accounts.operating-costs.index',
            'accounts.kitchen-money.index',
            'accounts.rider-money.index',
            'accounts.corporates.index',
        ] as $name) {
            $this->actingAs($this->accounts)
                ->get(route($name))
                ->assertOk("Expected OK for {$name}");
        }

        $this->actingAs($this->accounts)
            ->get(route('accounts.corporates.show', $this->corporate))
            ->assertOk();
    }

    public function test_accounts_redirects_to_own_dashboard(): void
    {
        $this->actingAs($this->accounts)
            ->get(route('dashboard.redirect'))
            ->assertRedirect(route('accounts.dashboard'));
    }
}
