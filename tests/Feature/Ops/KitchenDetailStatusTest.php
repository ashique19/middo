<?php

namespace Tests\Feature\Ops;

use App\Livewire\Shared\StaffProfileShow;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenDetailStatusTest extends TestCase
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

    public function test_admin_active_and_onboarding_lists_link_to_kitchen_detail(): void
    {
        $admin = $this->user('admin', ['mobile' => '01310666001']);
        $activeKitchen = $this->user('kitchen', [
            'mobile' => '01310666002',
            'first_name' => 'Active',
            'last_name' => 'Kitchen',
            'status' => 'active',
        ]);
        $pendingKitchen = $this->user('kitchen', [
            'mobile' => '01310666003',
            'first_name' => 'Pending',
            'last_name' => 'Kitchen',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.kitchens.active'))
            ->assertOk()
            ->assertSee('Active Kitchen')
            ->assertSee(route('admin.kitchens.show', $activeKitchen), false);

        $this->actingAs($admin)
            ->get(route('admin.kitchens.onboarding'))
            ->assertOk()
            ->assertSee('Pending Kitchen')
            ->assertSee(route('admin.kitchens.show', $pendingKitchen), false);
    }

    public function test_admin_can_activate_and_suspend_kitchen_from_detail_page(): void
    {
        $admin = $this->user('admin', ['mobile' => '01310666101']);
        $kitchen = $this->user('kitchen', [
            'mobile' => '01310666102',
            'first_name' => 'Chef',
            'last_name' => 'Two',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.kitchens.show', $kitchen))
            ->assertOk()
            ->assertSee('Activate')
            ->assertSee('Suspend')
            ->assertSee('Chef Two');

        Livewire::actingAs($admin)
            ->test(StaffProfileShow::class, ['kitchen' => $kitchen])
            ->call('activate')
            ->assertSee('activated');

        $this->assertSame('active', $kitchen->fresh()->status);

        Livewire::actingAs($admin)
            ->test(StaffProfileShow::class, ['kitchen' => $kitchen->fresh()])
            ->call('suspend')
            ->assertSee('suspended');

        $this->assertSame('inactive', $kitchen->fresh()->status);
    }

    public function test_operation_cannot_activate_or_suspend_kitchen(): void
    {
        $ops = $this->user('operation', ['mobile' => '01310666201']);
        $kitchen = $this->user('kitchen', [
            'mobile' => '01310666202',
            'first_name' => 'Chef',
            'last_name' => 'Ops',
            'status' => 'pending',
        ]);

        Livewire::actingAs($ops)
            ->test(StaffProfileShow::class, ['kitchen' => $kitchen])
            ->assertDontSee('Activate')
            ->call('activate')
            ->assertForbidden();

        $this->assertSame('pending', $kitchen->fresh()->status);
    }
}
