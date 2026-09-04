<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\Role;
use App\Models\User;
use App\Support\CorporateOrderGatewayCheckout;
use App\Support\PackageGatewayCheckout;
use App\Support\Payments\EpsPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EpsCallbackReturnsSignedResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_eps_success_for_order_returns_signed_result_not_login_or_dashboard(): void
    {
        config(['payments.driver' => 'pseudo']);

        $user = $this->corporate();
        $checkout = app(PaymentGateway::class)->createCheckout($user->id, 500, [
            'purpose' => CorporateOrderGatewayCheckout::PURPOSE,
        ]);
        $token = $checkout['token'];
        app(PaymentGateway::class)->markPaid($token);

        $response = $this->get(route('payments.eps.success', ['token' => $token]));
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString('/pay/corporate-prepay/'.$token, $location);
        $this->assertStringContainsString('eps_status=paid', $location);
        $this->assertStringNotContainsString('/login', $location);
        $this->assertStringNotContainsString('/corporates/dashboard', $location);
    }

    public function test_eps_success_for_package_returns_signed_result_not_login(): void
    {
        config(['payments.driver' => 'pseudo']);

        $user = $this->corporate();
        $checkout = app(PaymentGateway::class)->createCheckout($user->id, 2000, [
            'purpose' => PackageGatewayCheckout::PURPOSE,
            'meal_package_id' => 1,
            'quantity' => 1,
            'amount' => 2000,
        ]);
        $token = $checkout['token'];
        app(PaymentGateway::class)->markPaid($token);

        // Guest (no web session) — previously redirected to /login and trapped the app WebView.
        $response = $this->get(route('payments.eps.success', ['token' => $token]));
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString('/pay/corporate-prepay/'.$token, $location);
        $this->assertStringContainsString('eps_status=paid', $location);
        $this->assertStringNotContainsString('/login', $location);
    }

    public function test_signed_result_page_exposes_paid_marker_for_webview(): void
    {
        $user = $this->corporate();
        $checkout = app(PaymentGateway::class)->createCheckout($user->id, 500, [
            'purpose' => CorporateOrderGatewayCheckout::PURPOSE,
        ]);
        app(PaymentGateway::class)->markPaid($checkout['token']);

        $url = URL::temporarySignedRoute(
            'corporate.gateway-prepay.show',
            now()->addMinutes(45),
            [
                'token' => $checkout['token'],
                'eps_status' => 'paid',
                'order_placed' => '1',
            ]
        );

        $this->get($url)
            ->assertOk()
            ->assertSeeHtml('data-middo-payment-status="paid"')
            ->assertSee('return to the Middo app', false);
    }

    private function corporate(): User
    {
        $role = Role::create(['name' => 'corporate']);

        return User::create([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'company_name' => 'Acme',
            'mobile' => '01310123999',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 0,
        ]);
    }
}
