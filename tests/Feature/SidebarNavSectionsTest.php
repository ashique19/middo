<?php

namespace Tests\Feature;

use App\Models\Nav;
use App\Models\Role;
use App\Models\User;
use App\Support\StaffNavStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SidebarNavSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (StaffNavStructure::roleNames() as $name) {
            Role::firstOrCreate(['name' => $name]);
        }

        Artisan::call('db:seed', ['--class' => 'NavSeeder']);
    }

    public function test_each_staff_role_has_labeled_section_parents_only(): void
    {
        foreach (['admin', 'operation', 'accounts', 'kitchen', 'delivery', 'ground_marketing'] as $roleName) {
            $roleId = (int) Role::query()->where('name', $roleName)->value('id');
            $expected = collect(StaffNavStructure::sectionsFor($roleName))->pluck('title')->all();

            $parents = Nav::query()
                ->where('role_id', $roleId)
                ->whereNull('parent_id')
                ->orderBy('order')
                ->get();

            $this->assertSame(
                $expected,
                $parents->pluck('title')->all(),
                "Unexpected section parents for {$roleName}"
            );

            foreach ($parents as $parent) {
                $this->assertNull($parent->route_name);
                $this->assertTrue(
                    Nav::query()->where('parent_id', $parent->id)->exists(),
                    "Section {$parent->title} for {$roleName} has no children"
                );
            }

            $this->assertFalse(
                Nav::query()
                    ->where('role_id', $roleId)
                    ->whereNull('parent_id')
                    ->whereNotNull('route_name')
                    ->exists(),
                "{$roleName} still has flat top-level links"
            );
        }
    }

    public function test_admin_sidebar_shows_section_labels(): void
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Nav',
            'mobile' => '01700000001',
            'password' => 'password',
            'role_id' => Role::query()->where('name', 'admin')->value('id'),
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Overview')
            ->assertSee('Catalog')
            ->assertSee('Logistics')
            ->assertSee('Money')
            ->assertSee('System')
            ->assertSee('Coupons')
            ->assertSee('Settings');
    }

    public function test_operation_kitchen_delivery_sidebars_show_sections(): void
    {
        $ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'Nav',
            'mobile' => '01700000002',
            'password' => 'password',
            'role_id' => Role::query()->where('name', 'operation')->value('id'),
            'status' => 'active',
        ]);
        $kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'Nav',
            'mobile' => '01700000003',
            'password' => 'password',
            'role_id' => Role::query()->where('name', 'kitchen')->value('id'),
            'status' => 'active',
        ]);
        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'Nav',
            'mobile' => '01700000004',
            'password' => 'password',
            'role_id' => Role::query()->where('name', 'delivery')->value('id'),
            'status' => 'active',
        ]);

        $this->actingAs($ops)
            ->get(route('operation.dashboard'))
            ->assertOk()
            ->assertSee('Partners')
            ->assertSee('Packages')
            ->assertSee('Boxes')
            ->assertSee('Middo Boxes');

        $this->actingAs($kitchen)
            ->get(route('kitchen.dashboard'))
            ->assertOk()
            ->assertSee('Prep')
            ->assertSee('Middo boxes')
            ->assertSee('Boxes at kitchen');

        $this->actingAs($rider)
            ->get(route('delivery.dashboard'))
            ->assertOk()
            ->assertSee('Runs')
            ->assertSee('Kitchen dispatches')
            ->assertSee('Account');
    }
}
