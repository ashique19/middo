<?php

namespace Tests\Feature\Corporate;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorporateSurfaceSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $corporate;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'corporate']);

        $this->corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'Surface',
            'company_name' => 'Surface Corp',
            'mobile' => '01991000001',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'balance' => 5000,
        ]);
    }

    public function test_corporate_primary_pages_are_reachable(): void
    {
        foreach ([
            'corporates.dashboard',
            'corporates.packages.index',
            'corporates.orders.scheduled',
            'corporates.orders.history',
            'corporates.wallet',
            'corporates.profile',
            'corporates.change-password',
        ] as $name) {
            $this->actingAs($this->corporate)
                ->get(route($name))
                ->assertOk("Expected OK for {$name}");
        }
    }
}
