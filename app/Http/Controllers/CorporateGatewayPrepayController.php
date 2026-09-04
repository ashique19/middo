<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\User;
use App\Support\CorporateOrderGatewayCheckout;
use App\Support\CorporateWalletTopUp;
use App\Support\PackageGatewayCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class CorporateGatewayPrepayController extends Controller
{
    public function show(Request $request, string $token, PaymentGateway $gateway): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $payload = $gateway->find($token);
        abort_unless(is_array($payload), 404);

        $purpose = $payload['metadata']['purpose'] ?? 'order_prepay';
        $isWallet = $purpose === CorporateWalletTopUp::PURPOSE;
        $epsStatus = strtolower((string) $request->query('eps_status', ''));
        $paid = (bool) ($payload['paid'] ?? false) || $epsStatus === 'paid';
        $credited = (bool) ($payload['credited'] ?? false);
        $orderPlaced = (bool) ($payload['order_placed'] ?? false)
            || $request->query('order_placed') === '1';

        // Do not bounce WebViews (no session) into authenticated corporate pages.
        // Keep the public signed result page so the app can detect paid/failed.
        $resolvedEpsStatus = $epsStatus !== '' ? $epsStatus : ($paid ? 'paid' : 'pending');
        $paymentStatusMarker = match (true) {
            $resolvedEpsStatus === 'unpaid' => 'failed',
            $paid || $credited || $orderPlaced => 'paid',
            default => 'pending',
        };

        return view('public.corporate-gateway-prepay', [
            'token' => $token,
            'amount' => (int) ($payload['amount'] ?? 0),
            'paid' => $paid,
            'credited' => $credited,
            'driver' => $gateway->driver(),
            'purpose' => $purpose,
            'is_wallet' => $isWallet,
            'is_package' => $purpose === PackageGatewayCheckout::PURPOSE,
            'is_order_checkout' => $purpose === CorporateOrderGatewayCheckout::PURPOSE,
            'order_placed' => $orderPlaced,
            'redirect_url' => $payload['redirect_url'] ?? null,
            'eps_message' => $request->query('eps_message'),
            'eps_status' => $resolvedEpsStatus,
            'payment_status_marker' => $paymentStatusMarker,
            'balance' => $isWallet && $credited
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

        $orderPlaced = false;
        $packageDone = false;

        if ($purpose === PackageGatewayCheckout::PURPOSE) {
            $completed = PackageGatewayCheckout::completeIfPaid($token);
            $packageDone = (bool) ($completed['ok'] ?? false);
            if (! $packageDone) {
                PackageGatewayCheckout::markIntentPaid($token);
            }
            // Always stay on the signed result page (mobile WebView has no web session).
        }

        if ($purpose === CorporateOrderGatewayCheckout::PURPOSE) {
            $completed = CorporateOrderGatewayCheckout::completeIfPaid($token);
            $orderPlaced = (bool) ($completed['ok'] ?? false);
            // Do not redirect authenticated users to the dashboard from this confirm —
            // that traps the Middo app WebView on web login / dashboard.
        }

        return redirect()->to(
            URL::temporarySignedRoute(
                'corporate.gateway-prepay.show',
                now()->addMinutes(45),
                [
                    'token' => $token,
                    'order_placed' => $orderPlaced ? '1' : null,
                    'eps_status' => ($orderPlaced || $packageDone || ($fresh['paid'] ?? false)) ? 'paid' : null,
                ]
            )
        );
    }
}