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
        <p class="text-sm text-gray-500">
            Order #{{ $order->id }}
            · {{ ($driver ?? 'pseudo') === 'eps' ? 'EPS' : ($driver ?? 'pseudo') }} gateway
        </p>

        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 space-y-2 text-sm">
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Menu</span>
                <span class="font-semibold">{{ $order->menuItem?->name ?? 'Order' }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Quantity</span>
                <span class="font-semibold">{{ $order->quantity }}</span>
            </div>
            @if(!empty($party['has_separate_receiver']))
                <div class="flex justify-between gap-3">
                    <span class="text-gray-500">Account</span>
                    <span class="font-semibold text-right">{{ $party['account_holder_name'] }}</span>
                </div>
                <div class="flex justify-between gap-3">
                    <span class="text-gray-500">Receiver</span>
                    <span class="font-semibold text-right">{{ $party['receiver_name'] }} · {{ $party['receiver_mobile'] }}</span>
                </div>
            @endif
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Bill total</span>
                <span class="font-semibold">৳{{ number_format($order->total_amount) }}</span>
            </div>
            @if(($party['amount_paid'] ?? 0) > 0)
                <div class="flex justify-between gap-3">
                    <span class="text-gray-500">Already prepaid</span>
                    <span class="font-semibold text-emerald-700">৳{{ number_format($party['amount_paid']) }}</span>
                </div>
            @endif
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Amount due</span>
                <span class="font-black text-middo-orange">৳{{ number_format($amountDue) }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Status</span>
                <span class="font-semibold">{{ str($order->order_status)->replace('_', ' ')->title() }}</span>
            </div>
        </div>

        @if(session('payment_error'))
            <p class="text-sm font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3" data-testid="order-payment-error">
                {{ session('payment_error') }}
            </p>
        @endif

        @if($order->isPaid() || $amountDue <= 0)
            @if(session('order_payment_just_completed'))
                <div class="space-y-3" data-testid="order-payment-thank-you">
                    <p class="text-sm font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3">
                        Thank you for your payment
                    </p>
                    <a
                        href="{{ route('corporates.dashboard') }}"
                        class="inline-flex w-full items-center justify-center bg-[#1E4630] hover:bg-[#143021] text-white py-3.5 rounded-xl font-bold transition"
                        data-testid="order-payment-dashboard-link"
                    >
                        Go to Dashboard
                    </a>
                </div>
            @else
                <p class="text-sm font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3">
                    This order is already paid. Thank you!
                </p>
            @endif
        @else
            <p class="text-sm text-gray-600">
                Complete the remaining payment for this Middo delivery.
            </p>
            <form method="POST" action="{{ URL::temporarySignedRoute('public.order-payment.confirm', now()->addDays(3), ['order' => $order->id]) }}">
                @csrf
                <button type="submit" class="w-full bg-middo-orange text-white py-3.5 rounded-xl font-bold hover:opacity-90 transition">
                    @if(($driver ?? 'pseudo') === 'eps')
                        Pay ৳{{ number_format($amountDue) }} with EPS
                    @else
                        Pay ৳{{ number_format($amountDue) }} now
                    @endif
                </button>
            </form>
            <p class="text-xs text-gray-400">
                @if(($driver ?? 'pseudo') === 'eps')
                    You will be redirected to EPS (Easy Payment System) to complete payment securely.
                @else
                    Pseudo checkout for development and automated tests.
                @endif
            </p>
            @if(session('order_payment_failed_or_cancelled') || session('payment_error'))
                <a
                    href="{{ route('corporates.dashboard') }}"
                    class="inline-flex w-full items-center justify-center bg-[#1E4630] hover:bg-[#143021] text-white py-3.5 rounded-xl font-bold transition"
                    data-testid="order-payment-dashboard-link"
                >
                    Go to Dashboard
                </a>
            @endif
        @endif
    </div>
</body>
</html>
