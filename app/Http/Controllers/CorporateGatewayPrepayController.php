<?php

namespace App\Http\Controllers;

use App\Support\CorporateGatewayPrepay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class CorporateGatewayPrepayController extends Controller
{
    public function show(Request $request, string $token): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $payload = Cache::get(CorporateGatewayPrepay::cacheKey($token));
        abort_unless(is_array($payload), 404);

        return view('public.corporate-gateway-prepay', [
            'token' => $token,
            'amount' => (int) ($payload['amount'] ?? 0),
            'paid' => (bool) ($payload['paid'] ?? false),
        ]);
    }

    public function confirm(Request $request, string $token): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $payload = Cache::get(CorporateGatewayPrepay::cacheKey($token));
        abort_unless(is_array($payload), 404);

        if (! ($payload['paid'] ?? false)) {
            // Placeholder until SSLCommerz / bKash is wired — marks the prepaid session paid.
            CorporateGatewayPrepay::markPaid($token);
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
