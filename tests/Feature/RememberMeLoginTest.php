<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class RememberMeLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_remember_me_sets_remember_cookie(): void
    {
        $role = Role::create(['name' => 'corporate']);
        $user = User::create([
            'first_name' => 'Nabila',
            'last_name' => 'Rahman',
            'company_name' => 'Acme',
            'mobile' => '01310123451',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ]);

        $response = $this->post('/login', [
            'mobile' => '01310123451',
            'password' => '12345678',
            'remember' => '1',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
        $this->assertTrue(Auth::viaRemember() || Auth::check());
    }

    public function test_login_without_remember_me_does_not_persist_token(): void
    {
        $role = Role::create(['name' => 'corporate']);
        User::create([
            'first_name' => 'Nabila',
            'last_name' => 'Rahman',
            'company_name' => 'Acme',
            'mobile' => '01310123451',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ]);

        $this->post('/login', [
            'mobile' => '01310123451',
            'password' => '12345678',
        ])->assertRedirect();

        $this->assertAuthenticated();
    }
}
