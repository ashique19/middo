<?php

namespace Tests\Feature\Ops;

use App\Livewire\Shared\CorporateShow;
use App\Livewire\Shared\CorporateTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CorporateDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name]);
    }

    private function user(string $roleName, array $overrides = []): User
    {
        $role = $this->role($roleName);

        return User::create(array_merge([
            'first_name' => ucfirst($roleName),
            'last_name' => 'User',
            'mobile' => '01310'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ], $overrides));
    }

    public function test_operation_can_list_search_and_open_corporate_profile(): void
    {
        $ops = $this->user('operation', ['mobile' => '01310999111']);
        $match = $this->user('corporate', [
            'mobile' => '01310999222',
            'first_name' => 'Nabila',
            'last_name' => 'Rahman',
            'company_name' => 'Middo Demo Corp',
            'balance' => 50000,
            'address' => 'Gulshan Avenue',
        ]);
        $other = $this->user('corporate', [
            'mobile' => '01310999333',
            'first_name' => 'Other',
            'last_name' => 'Co',
            'company_name' => 'Other Foods Ltd',
            'balance' => 1000,
        ]);

        $menu = MenuItem::create([
            'name' => 'Lunch Box',
            'price' => 350,
            'status' => 'active',
        ]);

        Order::create([
            'user_id' => $match->id,
            'menu_item_id' => $menu->id,
            'quantity' => 2,
            'delivery_date' => now('Asia/Dhaka')->subDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 700,
            'amount_paid' => 700,
            'address' => 'Gulshan Avenue',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'created_by' => $match->id,
            'updated_by' => $match->id,
        ]);

        WalletTransaction::create([
            'user_id' => $match->id,
            'type' => WalletTransaction::TYPE_TOPUP,
            'amount' => 50000,
            'balance_after' => 50000,
            'description' => 'Opening balance',
        ]);

        $this->actingAs($ops)
            ->get(route('operation.corporates.index'))
            ->assertOk()
            ->assertSee('Middo Demo Corp')
            ->assertSee('Other Foods Ltd');

        Livewire::actingAs($ops)
            ->test(CorporateTable::class)
            ->set('search', 'Demo Corp')
            ->assertSee('Middo Demo Corp')
            ->assertDontSee('Other Foods Ltd');

        $this->actingAs($ops)
            ->get(route('operation.corporates.show', $match))
            ->assertOk()
            ->assertSee('Middo Demo Corp')
            ->assertSee('Gulshan Avenue')
            ->assertSee('৳50,000')
            ->assertSee('Opening balance')
            ->assertSee('#'.$match->orders()->first()->id);

        Livewire::actingAs($ops)
            ->test(CorporateShow::class, ['corporate' => $match])
            ->assertSee('Order history')
            ->assertSee('Wallet activity');

        $this->actingAs($ops)
            ->get(route('operation.corporates.show', $ops))
            ->assertNotFound();

        // Ensure the other corporate id is referenced so factories aren't unused.
        $this->assertNotSame($match->id, $other->id);
    }

    public function test_admin_corporates_list_is_clickable_and_legacy_url_works(): void
    {
        $admin = $this->user('admin', ['mobile' => '01310999444']);
        $corporate = $this->user('corporate', [
            'mobile' => '01310999555',
            'company_name' => 'Clickable Corp',
            'first_name' => 'Ada',
            'last_name' => 'Corp',
            'balance' => 1200,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.corporates.index'))
            ->assertOk()
            ->assertSee('Clickable Corp')
            ->assertSee(route('admin.corporates.show', $corporate), false);

        $this->actingAs($admin)
            ->get(route('admin.users.corporate'))
            ->assertOk()
            ->assertSee('Clickable Corp');

        $this->actingAs($admin)
            ->get(route('admin.corporates.show', $corporate))
            ->assertOk()
            ->assertSee('Clickable Corp')
            ->assertSee('৳1,200');
    }

    public function test_corporate_role_cannot_access_directory(): void
    {
        $corporate = $this->user('corporate', [
            'mobile' => '01310999666',
            'company_name' => 'Self Corp',
        ]);

        $this->actingAs($corporate)
            ->get(route('operation.corporates.index'))
            ->assertRedirect();
    }
}
