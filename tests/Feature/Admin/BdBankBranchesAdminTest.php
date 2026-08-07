<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\BdBankBranchesPage;
use App\Models\BdBank;
use App\Models\BdBankBranch;
use App\Models\BdBankCity;
use App\Models\Role;
use App\Models\User;
use App\Support\BdBanks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BdBankBranchesAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin']);
        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01750000001',
            'password' => 'password',
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_add_and_rename_branch(): void
    {
        $bank = BdBank::create(['name' => 'Test Bank Limited', 'is_active' => true]);
        $city = BdBankCity::create(['bd_bank_id' => $bank->id, 'name' => 'Dhaka']);

        Livewire::actingAs($this->admin)
            ->test(BdBankBranchesPage::class)
            ->set('filterBankId', $bank->id)
            ->set('newBranchCityId', $city->id)
            ->set('newBranchName', 'Gulshan Branch')
            ->call('createBranch')
            ->assertSet('errorMessage', '');

        $branch = BdBankBranch::query()->firstOrFail();
        $this->assertSame('Gulshan Branch', $branch->name);
        $this->assertTrue(BdBanks::isValidSelection('Test Bank Limited', 'Dhaka', 'Gulshan Branch'));

        Livewire::actingAs($this->admin)
            ->test(BdBankBranchesPage::class)
            ->set('filterBankId', $bank->id)
            ->call('startEditBranch', $branch->id)
            ->set('editingBranchName', 'Gulshan Avenue Branch')
            ->call('saveBranch')
            ->assertSet('errorMessage', '');

        $this->assertSame('Gulshan Avenue Branch', $branch->fresh()->name);
        $this->assertTrue(BdBanks::isValidSelection('Test Bank Limited', 'Dhaka', 'Gulshan Avenue Branch'));
    }
}
