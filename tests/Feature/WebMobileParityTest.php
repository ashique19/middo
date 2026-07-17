<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SitePage;
use App\Models\User;
use App\Support\CorporateGatewayPrepay;
use App\Support\CorporateWalletTopUp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class WebMobileParityTest extends TestCase
{
    use RefreshDatabase;

    private function corporateUser(array $overrides = []): User
    {
        $role = Role::create(['name' => 'corporate']);

        return User::create(array_merge([
            'first_name' => 'Nabila',
            'last_name' => 'Rahman',
            'company_name' => 'Acme Corp',
            'mobile' => '01711999888',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 1000,
            'address' => 'House 1, Road 2',
        ], $overrides));
    }

    public function test_forgot_password_page_renders(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Reset Password')
            ->assertSee('Send Reset Code');
    }

    public function test_web_password_reset_matches_mobile_otp_flow(): void
    {
        $user = $this->corporateUser();

        $this->post(route('password.email'), ['mobile' => $user->mobile])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('reset_step', 'reset')
            ->assertSessionHas('debug_otp');

        $otp = session('debug_otp');
        $this->assertNotEmpty($otp);

        $this->post(route('password.update'), [
            'mobile' => $user->mobile,
            'otp' => $otp,
            'password' => 'newpass99',
            'password_confirmation' => 'newpass99',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('newpass99', $user->fresh()->password));
    }

    public function test_web_wallet_top_up_uses_pseudo_gateway(): void
    {
        $user = $this->corporateUser(['balance' => 250]);

        $component = Livewire::actingAs($user)
            ->test(\App\Livewire\Account\WalletTopUpModal::class)
            ->call('openModal')
            ->set('amount', '500')
            ->call('topUp');

        $token = $component->get('paymentToken');
        $this->assertNotEmpty($token);
        $this->assertNotEmpty($component->get('paymentUrl'));
        $this->assertSame(250, (int) $user->fresh()->balance);

        CorporateGatewayPrepay::markPaid($token);
        CorporateWalletTopUp::creditIfPaid($token);

        $component->call('refreshAfterPayment')
            ->assertSet('successMessage', 'Balance already topped up.');

        $this->assertSame(750, (int) $user->fresh()->balance);
    }

    public function test_corporate_dashboard_uses_live_next_meal_metric(): void
    {
        $user = $this->corporateUser();

        Livewire::actingAs($user)
            ->test(\App\Livewire\Corporate\Dashboard::class)
            ->assertSee('None Scheduled')
            ->assertDontSee('Saved:')
            ->assertDontSee('Bulk Orders')
            ->assertDontSee('Track Live Couriers')
            ->assertSee('Place an Order')
            ->assertSee('Add Money');
    }

    public function test_faq_no_longer_promises_wallet_withdraw_or_one_hour_cancel_window(): void
    {
        $this->get(route('faq'))
            ->assertOk()
            ->assertDontSee('withdraw your full wallet balance')
            ->assertDontSee('one hour prior to the kitchen dispatch')
            ->assertSee('While an order is still pending');
    }

    public function test_privacy_and_terms_pages_render_from_seeded_site_pages(): void
    {
        $this->seed(\Database\Seeders\SitePageSeeder::class);

        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Information we collect');

        $this->get(route('terms'))
            ->assertOk()
            ->assertSee('Terms & Conditions')
            ->assertSee('Middo Boxes');

        $this->assertDatabaseHas('site_pages', ['slug' => 'privacy']);
        $this->assertDatabaseHas('site_pages', ['slug' => 'terms']);
        $this->assertSame(2, SitePage::query()->count());
    }
}
