<?php

namespace Tests\Feature\Admin;

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CouponsChargesAdminNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_nav_seeder_puts_coupons_and_charges_under_admin_menu(): void
    {
        foreach (['admin', 'corporate', 'kitchen', 'delivery', 'operation', 'accounts', 'ground_marketing'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }

        Artisan::call('db:seed', ['--class' => 'NavSeeder']);

        $adminId = Role::query()->where('name', 'admin')->value('id');
        $this->assertNotNull($adminId);

        $menu = Nav::query()
            ->where('role_id', $adminId)
            ->where('title', 'Menu')
            ->whereNull('parent_id')
            ->first();
        $this->assertNotNull($menu);

        $coupons = Nav::query()
            ->where('role_id', $adminId)
            ->where('route_name', 'admin.coupons.index')
            ->first();
        $charges = Nav::query()
            ->where('role_id', $adminId)
            ->where('route_name', 'admin.charges.index')
            ->first();

        $this->assertNotNull($coupons);
        $this->assertNotNull($charges);
        $this->assertSame((int) $menu->id, (int) $coupons->parent_id);
        $this->assertSame((int) $menu->id, (int) $charges->parent_id);
        $this->assertSame('Coupons', $coupons->title);
        $this->assertSame('Charges', $charges->title);
    }

    public function test_ensure_migration_repairs_missing_coupons_charges_navs(): void
    {
        foreach (['admin', 'corporate', 'kitchen', 'delivery', 'operation', 'accounts', 'ground_marketing'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }

        Artisan::call('db:seed', ['--class' => 'NavSeeder']);

        $adminId = (int) Role::query()->where('name', 'admin')->value('id');

        Nav::query()
            ->where('role_id', $adminId)
            ->whereIn('route_name', ['admin.coupons.index', 'admin.charges.index'])
            ->delete();

        $this->assertFalse(
            Nav::query()->where('route_name', 'admin.coupons.index')->exists()
        );

        // RefreshDatabase already ran this migration once; re-invoke up() as a repair.
        $migration = require database_path('migrations/2026_08_10_061500_ensure_admin_coupons_charges_navs.php');
        $migration->up();

        $menuId = Nav::query()
            ->where('role_id', $adminId)
            ->where('title', 'Menu')
            ->whereNull('parent_id')
            ->value('id');

        $this->assertNotNull($menuId);
        $this->assertTrue(
            Nav::query()
                ->where('role_id', $adminId)
                ->where('route_name', 'admin.coupons.index')
                ->where('parent_id', $menuId)
                ->exists()
        );
        $this->assertTrue(
            Nav::query()
                ->where('role_id', $adminId)
                ->where('route_name', 'admin.charges.index')
                ->where('parent_id', $menuId)
                ->exists()
        );
    }
}
