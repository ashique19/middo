<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\URL;

class CorporateOrderPresentation
{
    /**
     * Enrich an order row for corporate dashboard / scheduled / history cards.
     *
     * @return array<string, mixed>
     */
    public static function present(Order $order): array
    {
        $row = $order->toArray();
        $party = $order->partyPayload();
        $row['payment_method'] = $party['payment_method'];
        $row['payment_method_label'] = $party['payment_method_label'];
        $row['has_complaint'] = (bool) ($order->has_complaint ?? false);
        $row['amount_due'] = $order->amountDue();
        $row['can_pay_online'] = self::canPayOnline($order);
        $row['online_payment_url'] = $row['can_pay_online']
            ? URL::temporarySignedRoute(
                'public.order-payment',
                now()->addDays(3),
                ['order' => $order->id]
            )
            : null;

        return $row;
    }

    /**
     * Corporates may settle unpaid COD (or legacy unpaid residual) online at any time.
     */
    public static function canPayOnline(Order $order): bool
    {
        if (($order->order_status ?? '') === 'cancelled') {
            return false;
        }

        if ($order->isPaid() || $order->amountDue() <= 0) {
            return false;
        }

        return OrderPaymentMethod::resolve($order) === OrderPaymentMethod::CASH_ON_DELIVERY;
    }
}
