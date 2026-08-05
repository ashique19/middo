<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleIsolationMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function forbiddenPrefixProvider(): array
    {
        $cases = [];

        $probe = [
            'admin' => '/admin/dashboard',
            'operation' => '/operation/dashboard',
            'kitchen' => '/kitchen/dashboard',
            'delivery' => '/delivery/dashboard',
            'corporate' => '/corporate/dashboard',
            'accounts' => '/accounts/dashboard',
        ];

        foreach ($probe as $actor => $_) {
            foreach ($probe as $target => $path) {
                if ($actor === $target) {
                    continue;
                }
                $cases["{$actor}_denied_{$target}"] = [$actor, $path, $target];
            }
        }

        return $cases;
    }

    #[DataProvider('forbiddenPrefixProvider')]
    public function test_role_cannot_open_other_role_prefix(string $actorRole, string $forbiddenPath, string $targetRole): void
    {
        Role::create(['name' => $targetRole]);
        $role = Role::create(['name' => $actorRole]);

        $user = User::create([
            'first_name' => ucfirst($actorRole),
            'last_name' => 'Isolated',
            'mobile' => '0181'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get($forbiddenPath)
            ->assertRedirect(route('dashboard.redirect'));
    }

    public function test_guest_is_sent_to_login_for_staff_routes(): void
    {
        foreach ([
            '/admin/dashboard',
            '/operation/dashboard',
            '/kitchen/dashboard',
            '/delivery/dashboard',
            '/corporate/dashboard',
            '/accounts/dashboard',
        ] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }
}
