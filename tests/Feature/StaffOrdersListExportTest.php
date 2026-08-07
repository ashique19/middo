<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserShow;
use App\Livewire\Operation\ActiveOrders as OperationActiveOrders;
use App\Livewire\Operation\SearchOrder;
use App\Livewire\Shared\AccountsHub;
use App\Livewire\Shared\CorporateShow;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\StaffOrderRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffOrdersListExportTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOrder(User $owner): Order
    {
        $menu = MenuItem::create([
            'name' => 'Tehari',
            'summary' => 'Test',
            'price' => 420,
            'thumbnail' => 'img/menu/menu-1.jpg',
            'is_featured' => true,
            'display_order' => 1,
        ]);

        return Order::create([
            'user_id' => $owner->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'amount_paid' => 100,
            'kitchen_share_amount' => 50,
            'delivery_share_amount' => 40,
            'middo_rest_amount' => 10,
            'cash_collected' => 0,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
    }

    public function test_accounts_can_open_order_list_pages_with_list_and_export(): void
    {
        $role = Role::create(['name' => 'accounts']);
        $accounts = User::create([
            'first_name' => 'Acc',
            'last_name' => 'User',
            'mobile' => '01995000001',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $corpRole = Role::create(['name' => 'corporate']);
        $corp = User::create([
            'first_name' => 'Corp',
            'last_name' => 'A',
            'company_name' => 'Acme',
            'mobile' => '01995000002',
            'password' => '12345678',
            'role_id' => $corpRole->id,
            'status' => 'active',
        ]);
        $this->makeOrder($corp);

        $this->actingAs($accounts);

        $this->get(route('accounts.orders.active'))->assertOk();
        $this->get(route('accounts.orders.history'))->assertOk();
        $this->get(route('accounts.orders.search'))->assertOk();

        Livewire::test(OperationActiveOrders::class)
            ->assertSee('List view')
            ->assertSee('Export Excel')
            ->call('setViewMode', 'list')
            ->assertSet('viewMode', 'list')
            ->call('exportExcel')
            ->assertFileDownloaded();

        Livewire::test(SearchOrder::class)
            ->set('search', 'Tehari')
            ->assertSee('List view')
            ->call('setViewMode', 'list')
            ->call('exportExcel')
            ->assertFileDownloaded();
    }

    public function test_corporate_show_and_accounts_hub_support_list_and_export(): void
    {
        $role = Role::create(['name' => 'accounts']);
        $accounts = User::create([
            'first_name' => 'Acc',
            'last_name' => 'Two',
            'mobile' => '01995000003',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $corpRole = Role::create(['name' => 'corporate']);
        $corp = User::create([
            'first_name' => 'Corp',
            'last_name' => 'B',
            'company_name' => 'Beta',
            'mobile' => '01995000004',
            'password' => '12345678',
            'role_id' => $corpRole->id,
            'status' => 'active',
        ]);
        $this->makeOrder($corp);

        $this->actingAs($accounts);

        Livewire::test(CorporateShow::class, ['corporate' => $corp])
            ->assertSee('List view')
            ->assertSee('Export Excel')
            ->call('setViewMode', 'list')
            ->call('exportExcel')
            ->assertFileDownloaded();

        Livewire::test(AccountsHub::class)
            ->assertSee('List view')
            ->assertSee('Export Excel')
            ->call('setViewMode', 'list')
            ->call('exportExcel')
            ->assertFileDownloaded();

        $this->assertSame('accounts', StaffOrderRoutes::prefix());
    }

    public function test_admin_user_show_supports_list_and_export(): void
    {
        $adminRole = Role::create(['name' => 'admin']);
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'One',
            'mobile' => '01995000005',
            'password' => '12345678',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        $corpRole = Role::create(['name' => 'corporate']);
        $corp = User::create([
            'first_name' => 'Corp',
            'last_name' => 'C',
            'company_name' => 'Gamma',
            'mobile' => '01995000006',
            'password' => '12345678',
            'role_id' => $corpRole->id,
            'status' => 'active',
        ]);
        $this->makeOrder($corp);

        $this->actingAs($admin);

        Livewire::test(UserShow::class, ['user' => $corp])
            ->assertSee('List view')
            ->assertSee('Export Excel')
            ->call('setViewMode', 'list')
            ->call('exportExcel')
            ->assertFileDownloaded();
    }
}
