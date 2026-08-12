<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\UserTable;
use App\Models\Role;
use App\Models\User;
use App\Support\UsersExcelExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsersExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_table_can_export_excel(): void
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

        User::create([
            'first_name' => 'Rider',
            'last_name' => 'One',
            'mobile' => '01310123999',
            'email' => 'rider@example.com',
            'password' => '12345678',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        Livewire::test(UserTable::class, ['role' => 'delivery'])
            ->call('exportExcel')
            ->assertFileDownloaded('users-delivery-'.now('Asia/Dhaka')->format('Y-m-d').'.csv');
    }

    public function test_users_excel_export_includes_filtered_rows(): void
    {
        $deliveryRole = Role::create(['name' => 'delivery']);
        $kitchenRole = Role::create(['name' => 'kitchen']);

        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'Alpha',
            'mobile' => '01310123888',
            'email' => 'rider-alpha@example.com',
            'password' => '12345678',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
        ]);

        User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'Beta',
            'mobile' => '01310123777',
            'password' => '12345678',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);

        $response = UsersExcelExport::download(collect([$rider]), 'delivery', 'test-riders.csv');
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('Name,Phone,Email,Areas,Status', $csv);
        $this->assertStringContainsString('Rider Alpha', $csv);
        $this->assertStringContainsString('01310123888', $csv);
        $this->assertStringNotContainsString('Kitchen Beta', $csv);
    }

    public function test_export_respects_search_filter(): void
    {
        $adminRole = Role::create(['name' => 'admin']);
        $opsRole = Role::create(['name' => 'operation']);

        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01310123451',
            'password' => '12345678',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        User::create([
            'first_name' => 'Match',
            'last_name' => 'Person',
            'mobile' => '01310123666',
            'password' => '12345678',
            'role_id' => $opsRole->id,
            'status' => 'active',
        ]);

        User::create([
            'first_name' => 'Other',
            'last_name' => 'Person',
            'mobile' => '01310123555',
            'password' => '12345678',
            'role_id' => $opsRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        Livewire::test(UserTable::class, ['role' => 'operation'])
            ->set('search', 'Match')
            ->call('exportExcel')
            ->assertFileDownloaded();
    }
}
