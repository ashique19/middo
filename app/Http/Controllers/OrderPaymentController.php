<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderPaymentController extends Controller
{
    public function show(Request $request, Order $order): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $order->load(['menuItem', 'user']);

        return view('public.order-payment', [
            'order' => $order,
            'amountDue' => $order->amountDue(),
            'party' => $order->partyPayload(),
        ]);
    }

    public function confirm(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        DB::transaction(function () use ($order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPaid() || $locked->amountDue() <= 0) {
                return;
            }

            $locked->update([
                'amount_paid' => (int) $locked->total_amount,
                'payment_status' => 'paid',
                'order_status' => $locked->isDelivered() ? 'delivered_and_paid' : $locked->order_status,
                // Online residual is not rider cash — leave cash_collected unchanged.
            ]);
        });

        return redirect()->to(
            \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'public.order-payment',
                now()->addDays(3),
                ['order' => $order->id]
            )
        );
    }
}
