<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Kitchen\Dashboard;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenAppShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_dashboard_uses_app_shell_chrome(): void
    {
        $kitchenRole = Role::create(['name' => 'kitchen']);
        $kitchen = User::create([
            'first_name' => 'Shell',
            'last_name' => 'Kitchen',
            'mobile' => '01780000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($kitchen)
            ->get(route('kitchen.dashboard'))
            ->assertOk()
            ->assertSee('Middo Kitchen', false)
            ->assertSee('kitchen-bottom-nav', false)
            ->assertSee('Home', false)
            ->assertSee('Orders', false)
            ->assertSee('Groups', false)
            ->assertSee('Prep', false)
            ->assertSee('More', false)
            ->assertSee('manifest-kitchen.webmanifest', false)
            ->assertSee('apple-mobile-web-app-capable', false);

        Livewire::actingAs($kitchen)
            ->test(Dashboard::class)
            ->assertStatus(200)
            ->assertSee('Alerts');
    }
}
