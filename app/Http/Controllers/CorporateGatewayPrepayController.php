<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\User;
use App\Support\CorporateOrderGatewayCheckout;
use App\Support\CorporateWalletTopUp;
use App\Support\PackageGatewayCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class CorporateGatewayPrepayController extends Controller
{
    public function show(Request $request, string $token, PaymentGateway $gateway): View|RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $payload = $gateway->find($token);
        abort_unless(is_array($payload), 404);

        $purpose = $payload['metadata']['purpose'] ?? 'order_prepay';
        $isWallet = $purpose === CorporateWalletTopUp::PURPOSE;

        if ($purpose === PackageGatewayCheckout::PURPOSE && ($payload['paid'] ?? false) && Auth::check()) {
            $completed = PackageGatewayCheckout::completeIfPaid($token);
            if ($completed['ok'] ?? false) {
                $subscriptionId = (int) ($completed['subscription_id'] ?? 0);
                $redirect = $subscriptionId > 0
                    ? redirect()->to(route('corporates.packages.show', ['subscriptionId' => $subscriptionId]))
                    : redirect()->to(route('corporates.packages.index'));

                return $redirect->with('message', $completed['message'] ?? 'Package prepaid successfully.');
            }

            return redirect()->to(PackageGatewayCheckout::confirmUrl($token));
        }

        return view('public.corporate-gateway-prepay', [
            'token' => $token,
            'amount' => (int) ($payload['amount'] ?? 0),
            'paid' => (bool) ($payload['paid'] ?? false),
            'credited' => (bool) ($payload['credited'] ?? false),
            'driver' => $gateway->driver(),
            'purpose' => $purpose,
            'is_wallet' => $isWallet,
            'is_package' => $purpose === PackageGatewayCheckout::PURPOSE,
            'is_order_checkout' => $purpose === CorporateOrderGatewayCheckout::PURPOSE,
            'order_placed' => (bool) ($payload['order_placed'] ?? false),
            'redirect_url' => $payload['redirect_url'] ?? null,
            'eps_message' => $request->query('eps_message'),
            'balance' => $isWallet && ($payload['credited'] ?? false)
                ? (int) (User::query()->find((int) ($payload['user_id'] ?? 0))?->balance ?? 0)
                : null,
        ]);
    }

    public function confirm(Request $request, string $token, PaymentGateway $gateway): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $payload = $gateway->find($token);
        abort_unless(is_array($payload), 404);

        // Real drivers (EPS): send the customer to the hosted checkout instead of faking payment.
        if ($gateway->driver() !== 'pseudo') {
            $redirect = $payload['redirect_url'] ?? $gateway->paymentUrl($token);

            return redirect()->away($redirect);
        }

        if (! ($payload['paid'] ?? false)) {
            $gateway->markPaid($token);
        }

        $fresh = $gateway->find($token);
        $purpose = is_array($fresh) ? ($fresh['metadata']['purpose'] ?? null) : null;

        if ($purpose === CorporateWalletTopUp::PURPOSE) {
            CorporateWalletTopUp::creditIfPaid($token);
        }

        if ($purpose === PackageGatewayCheckout::PURPOSE) {
            $completed = PackageGatewayCheckout::completeIfPaid($token);
            if (($completed['ok'] ?? false) && Auth::check()) {
                $subscriptionId = (int) ($completed['subscription_id'] ?? 0);
                $redirect = $subscriptionId > 0
                    ? redirect()->to(route('corporates.packages.show', ['subscriptionId' => $subscriptionId]))
                    : redirect()->to(route('corporates.packages.index'));

                return $redirect->with('message', $completed['message'] ?? 'Package prepaid successfully.');
            }
            PackageGatewayCheckout::markIntentPaid($token);
            if (Auth::check()) {
                return redirect()->to(PackageGatewayCheckout::confirmUrl($token));
            }
        }

        $orderPlaced = false;
        if ($purpose === CorporateOrderGatewayCheckout::PURPOSE) {
            $completed = CorporateOrderGatewayCheckout::completeIfPaid($token);
            $orderPlaced = (bool) ($completed['ok'] ?? false);
            if ($orderPlaced && Auth::check()) {
                return redirect()
                    ->to(route('corporates.dashboard'))
                    ->with('message', $completed['message'] ?? 'Your meal track has been scheduled successfully!');
            }
        }

        return redirect()->to(
            URL::temporarySignedRoute(
                'corporate.gateway-prepay.show',
                now()->addMinutes(45),
                [
                    'token' => $token,
                    'order_placed' => $orderPlaced ? '1' : null,
                ]
            )
        );
    }
}