<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Support\CorporateOrderGatewayCheckout;
use App\Support\CorporateWalletTopUp;
use App\Support\MiddoBankLedger;
use App\Support\OrderPaymentMethod;
use App\Support\OrderTransition;
use App\Support\PackageGatewayCheckout;
use App\Support\Payments\EpsPaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class EpsPaymentCallbackController extends Controller
{
    public function success(Request $request, string $token, PaymentGateway $gateway): RedirectResponse
    {
        return $this->handle($request, $token, $gateway, expectedSuccess: true);
    }

    public function fail(Request $request, string $token, PaymentGateway $gateway): RedirectResponse
    {
        return $this->handle($request, $token, $gateway, expectedSuccess: false);
    }

    public function cancel(Request $request, string $token, PaymentGateway $gateway): RedirectResponse
    {
        return $this->handle($request, $token, $gateway, expectedSuccess: false);
    }

    protected function handle(
        Request $request,
        string $token,
        PaymentGateway $gateway,
        bool $expectedSuccess
    ): RedirectResponse {
        if (! $gateway instanceof EpsPaymentGateway) {
            // Allow callback handling when driver was swapped mid-session.
            $gateway = app(EpsPaymentGateway::class);
        }

        $merchantTransactionId = $request->query('merchantTransactionId')
            ?? $request->query('MerchantTransactionId')
            ?? $request->query('merchantTransactonId');

        if (! is_string($merchantTransactionId) || $merchantTransactionId === '') {
            $payload = $gateway->find($token);
            $merchantTransactionId = is_array($payload)
                ? ($payload['merchant_transaction_id'] ?? null)
                : null;
        }

        $result = ['ok' => false, 'message' => 'Payment was not completed.'];

        if ($expectedSuccess) {
            $result = $gateway->confirmFromCallback(
                $token,
                is_string($merchantTransactionId) ? $merchantTransactionId : null
            );

            if ($result['ok'] ?? false) {
                $this->postBankSettlement($token, $result['payload'] ?? $gateway->find($token));
                $this->fulfillPaidSession($token, $result['payload'] ?? $gateway->find($token));
            }
        }

        $payload = $result['payload'] ?? $gateway->find($token);
        $purpose = is_array($payload) ? ($payload['metadata']['purpose'] ?? null) : null;

        if ($purpose === EpsPaymentGateway::PURPOSE_ORDER_RESIDUAL) {
            $orderId = (int) (is_array($payload) ? ($payload['metadata']['order_id'] ?? 0) : 0);

            if ($orderId > 0) {
                return redirect()->to(
                    URL::temporarySignedRoute(
                        'public.order-payment',
                        now()->addDays(3),
                        ['order' => $orderId]
                    )
                );
            }
        }

        if ($purpose === PackageGatewayCheckout::PURPOSE && ($result['ok'] ?? false)) {
            if (Auth::check()) {
                return redirect()->to(PackageGatewayCheckout::confirmUrl($token));
            }

            return redirect()->guest(route('login'))
                ->with('url.intended', PackageGatewayCheckout::confirmUrl($token));
        }

        if ($purpose === CorporateOrderGatewayCheckout::PURPOSE && ($result['ok'] ?? false) && Auth::check()) {
            return redirect()
                ->to(route('corporates.dashboard'))
                ->with('message', 'Your meal track has been scheduled successfully!');
        }

        return redirect()->to(
            URL::temporarySignedRoute(
                'corporate.gateway-prepay.show',
                now()->addMinutes(45),
                [
                    'token' => $token,
                    'eps_status' => ($result['ok'] ?? false) ? 'paid' : 'unpaid',
                    'eps_message' => $result['message'] ?? null,
                ]
            )
        );
    }

    /**
     * Credit Middo bank float with EPS net (gross − sub-gateway fee). Idempotent on token.
     *
     * @param  array<string, mixed>|null  $payload
     */
    protected function postBankSettlement(string $token, ?array $payload): void
    {
        if (! is_array($payload)) {
            return;
        }

        $gross = (int) ($payload['amount'] ?? 0);
        if ($gross < 1) {
            return;
        }

        $epsRaw = is_array($payload['eps_raw'] ?? null) ? $payload['eps_raw'] : [];
        $purpose = is_string($payload['metadata']['purpose'] ?? null)
            ? (string) $payload['metadata']['purpose']
            : null;

        MiddoBankLedger::postEpsPayment(
            $token,
            $gross,
            $epsRaw,
            is_string($payload['merchant_transaction_id'] ?? null)
                ? (string) $payload['merchant_transaction_id']
                : null,
            $purpose,
            Auth::id() ? (int) Auth::id() : null,
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function fulfillPaidSession(string $token, ?array $payload): void
    {
        if (! is_array($payload)) {
            return;
        }

        $purpose = $payload['metadata']['purpose'] ?? null;

        if ($purpose === CorporateWalletTopUp::PURPOSE) {
            CorporateWalletTopUp::creditIfPaid($token);

            return;
        }

        if ($purpose === PackageGatewayCheckout::PURPOSE) {
            PackageGatewayCheckout::markIntentPaid($token);

            return;
        }

        if ($purpose === CorporateOrderGatewayCheckout::PURPOSE) {
            CorporateOrderGatewayCheckout::completeIfPaid($token);

            return;
        }

        if ($purpose === EpsPaymentGateway::PURPOSE_ORDER_RESIDUAL) {
            $orderId = (int) ($payload['metadata']['order_id'] ?? 0);
            if ($orderId < 1) {
                return;
            }

            DB::transaction(function () use ($orderId, $payload) {
                $locked = Order::query()->whereKey($orderId)->lockForUpdate()->first();
                if (! $locked || $locked->isPaid() || $locked->amountDue() <= 0) {
                    return;
                }

                $expected = (int) ($payload['amount'] ?? 0);
                if ($expected > 0 && $expected !== $locked->amountDue()) {
                    // Still accept if customer paid the session amount that was due at checkout start.
                    // Prefer not under-paying: only apply when session amount covers current due.
                    if ($expected < $locked->amountDue()) {
                        return;
                    }
                }

                $attributes = [
                    'amount_paid' => $locked->netTotalAmount(),
                    'payment_status' => 'paid',
                    'payment_method' => OrderPaymentMethod::GATEWAY,
                ];

                if ($locked->isDelivered()) {
                    OrderTransition::apply($locked, OrderTransition::DELIVERED_AND_PAID, $attributes);

                    return;
                }

                $locked->update($attributes);
            });
        }
    }
}
