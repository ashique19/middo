<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Middo Prepayment</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-middo-dark font-sans min-h-screen flex items-center justify-center p-6">
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm max-w-md w-full p-6 space-y-4">
        <h1 class="text-2xl font-black text-middo-dark">Middo Prepayment</h1>
        <p class="text-sm text-gray-500">Corporate order checkout · {{ $driver }} gateway</p>

        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 space-y-2 text-sm">
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Amount due</span>
                <span class="font-black text-middo-orange">৳{{ number_format($amount) }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Status</span>
                <span class="font-semibold">{{ $paid ? 'Paid' : 'Awaiting payment' }}</span>
            </div>
        </div>

        @if($paid)
            <p class="text-sm font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3">
                Payment recorded. Return to Middo and confirm your order with the SMS OTP.
            </p>
            <p class="text-xs text-gray-400 break-all">Payment token: {{ $token }}</p>
        @else
            <p class="text-sm text-gray-600">
                Complete this prepayment to schedule meals when the receiver differs from the account holder,
                or when you would exceed 3 active orders.
            </p>
            <form method="POST" action="{{ URL::temporarySignedRoute('corporate.gateway-prepay.confirm', now()->addMinutes(30), ['token' => $token]) }}">
                @csrf
                <button type="submit" class="w-full bg-middo-orange text-white py-3.5 rounded-xl font-bold hover:opacity-90 transition">
                    Pay ৳{{ number_format($amount) }} now
                </button>
            </form>
            <p class="text-xs text-gray-400">
                Pseudo gateway for development. Replace <code>PaymentGateway</code> binding with the real provider when finalized.
            </p>
        @endif
    </div>
</body>
</html>
