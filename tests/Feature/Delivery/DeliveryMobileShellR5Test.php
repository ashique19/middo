<?php

namespace Tests\Feature\Delivery;

use App\Livewire\Delivery\Dashboard;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryMobileShellR5Test extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_dashboard_uses_rider_app_shell_chrome(): void
    {
        $role = Role::create(['name' => 'delivery']);
        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'Shell',
            'mobile' => '01760000001',
            'password' => 'password',
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($rider)
            ->get(route('delivery.dashboard'))
            ->assertOk()
            ->assertSee('Middo Rider', false)
            ->assertSee('kitchen-bottom-nav', false)
            ->assertSee('Home', false)
            ->assertSee('Runs', false)
            ->assertSee('Boxes', false)
            ->assertSee('Cash', false)
            ->assertSee('More', false)
            ->assertSee('manifest-delivery.webmanifest', false)
            ->assertSee('apple-mobile-web-app-capable', false);

        Livewire::actingAs($rider)
            ->test(Dashboard::class)
            ->assertStatus(200)
            ->assertSee('Kitchen dispatches');
    }

    public function test_delivery_pwa_assets_exist(): void
    {
        $this->assertFileExists(public_path('manifest-delivery.webmanifest'));
        $this->assertFileExists(public_path('sw-delivery.js'));

        $manifest = json_decode((string) file_get_contents(public_path('manifest-delivery.webmanifest')), true);
        $this->assertSame('Rider', $manifest['short_name'] ?? null);
        $this->assertSame('/delivery/dashboard', $manifest['start_url'] ?? null);
    }
}
