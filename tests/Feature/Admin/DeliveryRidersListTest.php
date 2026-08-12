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

class DeliveryRidersListTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_list_shows_riders_title_areas_and_hides_role(): void
    {
        $adminRole = Role::create(['name' => 'admin']);
        $deliveryRole = Role::create(['name' => 'delivery']);

        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01310123451',
            'password' => '12345678',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        $city = City::create(['name' => 'Dhaka']);
        $gulshan = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);
        $banani = Area::create(['name' => 'Banani', 'city_id' => $city->id]);

        $rider = User::create([
            'first_name' => 'Rafiq',
            'last_name' => 'Rider',
            'mobile' => '01310129999',
            'password' => '12345678',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
            'area_id' => $gulshan->id,
        ]);
        $rider->areas()->sync([$gulshan->id, $banani->id]);

        Livewire::actingAs($admin)
            ->test(UserTable::class, ['role' => 'delivery'])
            ->assertSee('Delivery Riders')
            ->assertDontSee('delivery Users', false)
            ->assertSee('Areas')
            ->assertDontSeeHtml('>Role</th>')
            ->assertSee('Gulshan')
            ->assertSee('Banani')
            ->assertSee('01310129999');
    }
}
