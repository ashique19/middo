<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay for Middo Order #{{ $order->id }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-middo-dark font-sans min-h-screen flex items-center justify-center p-6">
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm max-w-md w-full p-6 space-y-4">
        <h1 class="text-2xl font-black text-middo-dark">Middo Payment</h1>
        <p class="text-sm text-gray-500">Order #{{ $order->id }}</p>

        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 space-y-2 text-sm">
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Menu</span>
                <span class="font-semibold">{{ $order->menuItem?->name ?? 'Order' }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Quantity</span>
                <span class="font-semibold">{{ $order->quantity }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Amount due</span>
                <span class="font-black text-middo-orange">৳{{ number_format($order->total_amount) }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Status</span>
                <span class="font-semibold">{{ str($order->order_status)->replace('_', ' ')->title() }}</span>
            </div>
        </div>

        @if($order->payment_status === 'paid' || $order->order_status === 'delivered_and_paid')
            <p class="text-sm font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3">
                This order is already paid. Thank you!
            </p>
        @else
            <p class="text-sm text-gray-600">
                Complete payment with your preferred online method. Your Middo delivery rider sent you this secure link.
            </p>
            <p class="text-xs text-gray-400">Online checkout integration can be completed here.</p>
        @endif
    </div>
</body>
</html>
