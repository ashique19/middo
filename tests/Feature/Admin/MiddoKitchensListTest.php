<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\UserTable;
use App\Models\Area;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MiddoKitchensListTest extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_list_shows_middo_kitchens_phone_area_hides_role(): void
    {
        $adminRole = Role::create(['name' => 'admin']);
        $kitchenRole = Role::create(['name' => 'kitchen']);

        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01310123451',
            'password' => '12345678',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Dhanmondi', 'city_id' => $city->id]);

        User::create([
            'first_name' => 'Spice',
            'last_name' => 'Kitchen',
            'mobile' => '01310128888',
            'password' => '12345678',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
            'area_id' => $area->id,
        ]);

        Livewire::actingAs($admin)
            ->test(UserTable::class, ['role' => 'kitchen'])
            ->assertSee('Middo Kitchens')
            ->assertDontSee('kitchen Users', false)
            ->assertSee('Phone')
            ->assertSee('Area')
            ->assertDontSeeHtml('>Role</th>')
            ->assertDontSeeHtml('>Email</th>')
            ->assertSee('Dhanmondi')
            ->assertSee('01310128888');
    }
}
