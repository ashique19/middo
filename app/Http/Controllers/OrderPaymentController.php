<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Support\OrderPaymentMethod;
use App\Support\OrderTransition;
use App\Support\Payments\EpsPaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class OrderPaymentController extends Controller
{
    public function show(Request $request, Order $order, PaymentGateway $gateway): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $order->load(['menuItem', 'user']);

        return view('public.order-payment', [
            'order' => $order,
            'amountDue' => $order->amountDue(),
            'party' => $order->partyPayload(),
            'driver' => $gateway->driver(),
        ]);
    }

    public function confirm(Request $request, Order $order, PaymentGateway $gateway): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $order->loadMissing('user');

        if ($order->isPaid() || $order->amountDue() <= 0) {
            return redirect()->to(
                URL::temporarySignedRoute(
                    'public.order-payment',
                    now()->addDays(3),
                    ['order' => $order->id]
                )
            );
        }

        if ($gateway->driver() === 'eps') {
            try {
                $checkout = $gateway->createCheckout(
                    (int) $order->user_id,
                    $order->amountDue(),
                    [
                        'purpose' => EpsPaymentGateway::PURPOSE_ORDER_RESIDUAL,
                        'order_id' => $order->id,
                        'receiver_name' => $order->receiver_name ?: $order->user?->full_name,
                        'mobile' => $order->receiver_mobile ?: $order->user?->mobile,
                        'address' => $order->address,
                        'customer_city' => $order->user?->city_name ?? 'Dhaka',
                    ]
                );
            } catch (Throwable $e) {
                report($e);

                return redirect()->to(
                    URL::temporarySignedRoute(
                        'public.order-payment',
                        now()->addDays(3),
                        ['order' => $order->id]
                    )
                )->with('payment_error', $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Unable to start online payment. Please try again.');
            }

            return redirect()->away($checkout['payment_url']);
        }

        DB::transaction(function () use ($order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPaid() || $locked->amountDue() <= 0) {
                return;
            }

            $attributes = [
                'amount_paid' => $locked->netTotalAmount(),
                'payment_status' => 'paid',
                'payment_method' => OrderPaymentMethod::GATEWAY,
                // Online residual is not rider cash — leave cash_collected unchanged.
            ];

            if ($locked->isDelivered()) {
                OrderTransition::apply($locked, OrderTransition::DELIVERED_AND_PAID, $attributes);

                return;
            }

            $locked->update($attributes);
        });

        return redirect()->to(
            URL::temporarySignedRoute(
                'public.order-payment',
                now()->addDays(3),
                ['order' => $order->id]
            )
        )->with('order_payment_just_completed', true);
    }
}
