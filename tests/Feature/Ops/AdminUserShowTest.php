<?php

namespace Tests\Feature\Ops;

use App\Livewire\Admin\UserShow;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderGroupOrder;
use App\Models\Role;
use App\Models\User;
use App\Models\UserLog;
use App\Support\UserAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserShowTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name]);
    }

    private function user(string $roleName, array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => ucfirst($roleName),
            'last_name' => 'User',
            'mobile' => '01310'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'password' => '12345678',
            'role_id' => $this->role($roleName)->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ], $overrides));
    }

    public function test_admin_user_lists_link_to_user_detail(): void
    {
        $admin = $this->user('admin', ['mobile' => '01310777001']);
        $ops = $this->user('operation', [
            'mobile' => '01310777002',
            'first_name' => 'Ops',
            'last_name' => 'Lead',
        ]);
        $kitchen = $this->user('kitchen', [
            'mobile' => '01310777003',
            'first_name' => 'Kitchen',
            'last_name' => 'Chef',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.operation'))
            ->assertOk()
            ->assertSee('Ops Lead')
            ->assertSee(route('admin.users.show', $ops), false);

        $this->actingAs($admin)
            ->get(route('admin.users.kitchen'))
            ->assertOk()
            ->assertSee('Kitchen Chef')
            ->assertSee(route('admin.users.show', $kitchen), false);
    }

    public function test_admin_can_open_user_detail_with_audit_and_related_links(): void
    {
        $admin = $this->user('admin', ['mobile' => '01310777101']);
        $corporate = $this->user('corporate', [
            'mobile' => '01310777102',
            'first_name' => 'Nabila',
            'last_name' => 'Rahman',
            'company_name' => 'Middo Demo Corp',
        ]);
        $kitchen = $this->user('kitchen', [
            'mobile' => '01310777103',
            'first_name' => 'Chef',
            'last_name' => 'One',
        ]);

        UserLog::create([
            'user_id' => $corporate->id,
            'performed_by' => $admin->id,
            'event' => UserLog::EVENT_LOGIN,
            'source' => UserAudit::SOURCE_WEB,
            'ip_address' => '127.0.0.1',
            'metadata' => ['note' => 'test-login'],
        ]);

        UserLog::create([
            'user_id' => $corporate->id,
            'performed_by' => $admin->id,
            'event' => UserLog::EVENT_STATUS_CHANGED,
            'source' => UserAudit::SOURCE_ADMIN,
            'ip_address' => '127.0.0.1',
            'metadata' => [
                'changes' => [
                    'status' => ['from' => 'active', 'to' => 'inactive'],
                ],
            ],
        ]);

        $menu = MenuItem::create([
            'name' => 'Beef Bowl',
            'price' => 400,
        ]);

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '1:00 PM',
            'total_amount' => 400,
            'amount_paid' => 0,
            'address' => 'Gulshan 2',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'created_by' => $corporate->id,
            'updated_by' => $admin->id,
        ]);

        $group = OrderGroup::create([
            'name' => 'Gulshan Lunch',
            'delivery_date' => $order->delivery_date,
            'menu_id' => $menu->id,
            'kitchen_id' => $kitchen->id,
            'created_by' => $admin->id,
        ]);

        OrderGroupOrder::create([
            'order_group_id' => $group->id,
            'order_id' => $order->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $corporate))
            ->assertOk()
            ->assertSee('Middo Demo Corp')
            ->assertSee('Audit log')
            ->assertSee('Login')
            ->assertSee('Via Website')
            ->assertSee('Note: test-login')
            ->assertSee('Status changed')
            ->assertSee('Status changed from active to inactive')
            ->assertSee('Via Admin panel')
            ->assertDontSee('"note":', false)
            ->assertSee('Corporate profile')
            ->assertSee('Related orders')
            ->assertSee('Beef Bowl')
            ->assertSee('#'.$order->id)
            ->assertSee(route('admin.corporates.show', $corporate), false)
            ->assertSee(route('admin.orders.show', $order), false);

        Livewire::actingAs($admin)
            ->test(UserShow::class, ['user' => $kitchen])
            ->assertSee('Kitchen profile')
            ->assertSee('Kitchen orders')
            ->assertSee('Menus')
            ->assertSee('Related orders')
            ->assertSee('Beef Bowl');
    }

    public function test_non_admin_cannot_open_admin_user_detail(): void
    {
        $ops = $this->user('operation', ['mobile' => '01310777201']);
        $target = $this->user('kitchen', ['mobile' => '01310777202']);

        $this->actingAs($ops)
            ->get(route('admin.users.show', $target))
            ->assertRedirect();
    }

    public function test_admin_corporate_list_links_contact_to_account_audit(): void
    {
        $admin = $this->user('admin', ['mobile' => '01310777301']);
        $corporate = $this->user('corporate', [
            'mobile' => '01310777302',
            'first_name' => 'Contact',
            'last_name' => 'Person',
            'company_name' => 'Clickable Corp',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.corporates.index'))
            ->assertOk()
            ->assertSee('Account & audit')
            ->assertSee(route('admin.users.show', $corporate), false)
            ->assertSee(route('admin.corporates.show', $corporate), false);
    }
}
