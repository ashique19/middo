<?php

namespace Tests\Feature\Public;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicHeaderNavTest extends TestCase
{
    use RefreshDatabase;

    protected User $corporate;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'corporate']);

        $this->corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'Nav',
            'company_name' => 'Nav Corp',
            'mobile' => '01991000020',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'balance' => 1000,
        ]);
    }

    public function test_guest_sees_sign_up_cta_instead_of_track_todays_lunch(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Sign up', false)
            ->assertDontSee("Track Today's Lunch", false);
    }

    public function test_logged_in_user_sees_dashboard_cta_instead_of_track_todays_lunch(): void
    {
        $this->actingAs($this->corporate)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Dashboard', false)
            ->assertDontSee("Track Today's Lunch", false)
            ->assertDontSee('Sign up', false);
    }
}
