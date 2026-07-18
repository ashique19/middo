<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdersExcelExport
{
    /**
     * @param  Collection<int, Order>|iterable<int, Order>  $orders
     */
    public static function download(iterable $orders, string $filename = 'orders.csv'): StreamedResponse
    {
        $filename = str_ends_with(strtolower($filename), '.csv')
            ? $filename
            : $filename.'.csv';

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens Bengali/Unicode cleanly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Order #',
                'Delivery Date',
                'Delivery Time',
                'Customer',
                'Receiver',
                'Receiver Mobile',
                'Menu',
                'Qty',
                'Address',
                'Status',
                'Payment Status',
                'Payment Method',
                'Total',
                'Amount Paid',
                'Amount Due',
                'Group',
            ]);

            foreach ($orders as $order) {
                if (! $order instanceof Order) {
                    continue;
                }

                $order->loadMissing(['menuItem', 'user', 'orderGroup']);
                $party = $order->partyPayload();

                fputcsv($out, [
                    $order->id,
                    optional($order->delivery_date)->toDateString(),
                    $order->delivery_time,
                    $party['account_holder_name'],
                    $party['receiver_name'],
                    $party['receiver_mobile'],
                    $order->menuItem?->name ?? 'Custom Selection',
                    $order->quantity,
                    $order->address,
                    $order->order_status,
                    $order->payment_status,
                    $order->paymentMethodLabel(),
                    $order->total_amount,
                    $party['amount_paid'],
                    $party['amount_due'],
                    $order->orderGroup?->name ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
