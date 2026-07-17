<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Support\CorporateWalletTopUp;
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

        return view('public.corporate-gateway-prepay', [
            'token' => $token,
            'amount' => (int) ($payload['amount'] ?? 0),
            'paid' => (bool) ($payload['paid'] ?? false),
            'credited' => (bool) ($payload['credited'] ?? false),
            'driver' => $gateway->driver(),
            'purpose' => $purpose,
            'is_wallet' => $isWallet,
            'balance' => $isWallet && ($payload['credited'] ?? false)
                ? (int) (\App\Models\User::query()->find((int) ($payload['user_id'] ?? 0))?->balance ?? 0)
                : null,
        ]);
    }

    public function confirm(Request $request, string $token, PaymentGateway $gateway): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $payload = $gateway->find($token);
        abort_unless(is_array($payload), 404);

        if (! ($payload['paid'] ?? false)) {
            // Pseudo driver: mark paid immediately. Real drivers will confirm via webhook/callback.
            $gateway->markPaid($token);
        }

        $fresh = $gateway->find($token);
        if (is_array($fresh) && (($fresh['metadata']['purpose'] ?? null) === CorporateWalletTopUp::PURPOSE)) {
            CorporateWalletTopUp::creditIfPaid($token);
        }

        return redirect()->to(
            URL::temporarySignedRoute(
                'corporate.gateway-prepay.show',
                now()->addMinutes(30),
                ['token' => $token]
            )
        );
    }
}
