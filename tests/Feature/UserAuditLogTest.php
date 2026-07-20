<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserLog;
use App\Support\UserAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAuditLogTest extends TestCase
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

    public function test_web_login_logout_and_failed_login_are_audited(): void
    {
        $user = $this->user('corporate', ['mobile' => '01310888111']);

        $this->post('/login', [
            'mobile' => '01310888111',
            'password' => '12345678',
        ])->assertRedirect();

        $this->assertDatabaseHas('user_logs', [
            'user_id' => $user->id,
            'event' => UserLog::EVENT_LOGIN,
            'source' => UserAudit::SOURCE_WEB,
        ]);

        $this->post('/logout')->assertRedirect('/');

        $this->assertDatabaseHas('user_logs', [
            'user_id' => $user->id,
            'event' => UserLog::EVENT_LOGOUT,
            'source' => UserAudit::SOURCE_WEB,
        ]);

        $this->post('/login', [
            'mobile' => '01310888111',
            'password' => 'wrong-password',
        ]);

        $this->assertDatabaseHas('user_logs', [
            'user_id' => $user->id,
            'event' => UserLog::EVENT_LOGIN_FAILED,
            'source' => UserAudit::SOURCE_WEB,
        ]);
    }

    public function test_inactive_web_login_is_logged_as_blocked(): void
    {
        $user = $this->user('kitchen', [
            'mobile' => '01310888112',
            'status' => 'pending',
        ]);

        $this->from('/login')->post('/login', [
            'mobile' => '01310888112',
            'password' => '12345678',
        ])->assertRedirect('/login');

        $this->assertDatabaseHas('user_logs', [
            'user_id' => $user->id,
            'event' => UserLog::EVENT_LOGIN_BLOCKED,
            'source' => UserAudit::SOURCE_WEB,
        ]);

        $this->assertDatabaseMissing('user_logs', [
            'user_id' => $user->id,
            'event' => UserLog::EVENT_LOGIN,
        ]);
    }

    public function test_corporate_api_login_and_logout_use_mobile_source(): void
    {
        $user = $this->user('corporate', [
            'mobile' => '01310888113',
            'company_name' => 'Audit Corp',
            'address' => 'Somewhere long enough',
            'balance' => 1000,
        ]);

        $this->postJson('/api/corporate/login', [
            'mobile' => '01310888113',
            'password' => '12345678',
            'device_name' => 'pixel-test',
        ])->assertOk();

        $this->assertDatabaseHas('user_logs', [
            'user_id' => $user->id,
            'event' => UserLog::EVENT_LOGIN,
            'source' => UserAudit::SOURCE_CORPORATE_MOBILE,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/corporate/logout')->assertOk();

        $this->assertDatabaseHas('user_logs', [
            'user_id' => $user->id,
            'event' => UserLog::EVENT_LOGOUT,
            'source' => UserAudit::SOURCE_CORPORATE_MOBILE,
        ]);
    }

    public function test_user_create_update_and_password_change_are_audited_without_hash(): void
    {
        $admin = $this->user('admin', ['mobile' => '01310888114']);
        $this->actingAs($admin);

        $created = User::create([
            'first_name' => 'New',
            'last_name' => 'Corp',
            'mobile' => '01310888115',
            'password' => '12345678',
            'role_id' => $this->role('corporate')->id,
            'status' => 'active',
            'company_name' => 'New Co',
            'address' => 'Address line long',
            'is_mobile_verified' => true,
        ]);

        $this->assertDatabaseHas('user_logs', [
            'user_id' => $created->id,
            'event' => UserLog::EVENT_CREATED,
            'performed_by' => $admin->id,
        ]);

        $created->update(['status' => 'inactive']);

        $this->assertDatabaseHas('user_logs', [
            'user_id' => $created->id,
            'event' => UserLog::EVENT_STATUS_CHANGED,
            'performed_by' => $admin->id,
        ]);

        $created->update(['password' => 'new-password-99']);

        $passwordLog = UserLog::query()
            ->where('user_id', $created->id)
            ->where('event', UserLog::EVENT_PASSWORD_CHANGED)
            ->latest('id')
            ->first();

        $this->assertNotNull($passwordLog);
        $this->assertSame('[changed]', $passwordLog->metadata['changes']['password']['to'] ?? null);
        $this->assertSame('[redacted]', $passwordLog->metadata['changes']['password']['from'] ?? null);
    }

    public function test_resolve_source_from_request_path(): void
    {
        $this->get('/admin/dashboard');
        // resolveSource uses current request when available.
        request()->server->set('REQUEST_URI', '/admin/users/corporate');
        $adminRequest = Request::create('/admin/users/corporate', 'GET');
        $this->assertSame(UserAudit::SOURCE_ADMIN, UserAudit::resolveSource($adminRequest));

        $apiRequest = Request::create('/api/corporate/login', 'POST');
        $this->assertSame(UserAudit::SOURCE_CORPORATE_MOBILE, UserAudit::resolveSource($apiRequest));

        $webRequest = Request::create('/login', 'POST');
        $this->assertSame(UserAudit::SOURCE_WEB, UserAudit::resolveSource($webRequest));
    }
}
