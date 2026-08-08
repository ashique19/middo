<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\SettingsAuditPage;
use App\Livewire\Admin\SettingsPage;
use App\Models\Role;
use App\Models\SettingChangeLog;
use App\Models\User;
use App\Support\MiddoSettings;
use App\Support\SettingsAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'admin']);
        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01780000001',
            'password' => 'password',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);
    }

    public function test_settings_page_links_to_audit_log(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Settings audit log', false)
            ->assertSee(route('admin.settings.audit'), false);
    }

    public function test_saving_settings_writes_audit_log(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_AUTO_GROUP_QUANTITY, '10');

        Livewire::actingAs($this->admin)
            ->test(SettingsPage::class)
            ->set('auto_group_quantity', 15)
            ->call('save')
            ->assertSet('errorMessage', '')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('setting_change_logs', 1);
        $log = SettingChangeLog::query()->first();
        $this->assertNotNull($log);
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame(SettingsAudit::SOURCE_ADMIN_SETTINGS, $log->source);

        $match = collect($log->changes)->first(
            fn ($c) => ($c['key'] ?? null) === MiddoSettings::KEY_AUTO_GROUP_QUANTITY
        );
        $this->assertNotNull($match);
        $this->assertSame('10', $match['old']);
        $this->assertSame('15', $match['new']);

        Livewire::actingAs($this->admin)
            ->test(SettingsAuditPage::class)
            ->assertOk()
            ->assertSee('Auto group max quantity', false)
            ->assertSee('10', false)
            ->assertSee('15', false);
    }

    public function test_saving_unchanged_settings_does_not_write_audit_log(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SettingsPage::class)
            ->call('save')
            ->assertHasNoErrors();

        // First save may write many keys from empty → defaults.
        $countAfterFirst = SettingChangeLog::query()->count();
        $this->assertGreaterThanOrEqual(1, $countAfterFirst);

        Livewire::actingAs($this->admin)
            ->test(SettingsPage::class)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($countAfterFirst, SettingChangeLog::query()->count());
    }
}
