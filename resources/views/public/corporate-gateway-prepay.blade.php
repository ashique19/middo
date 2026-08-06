<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $is_wallet ? 'Middo Balance Top-up' : 'Middo Prepayment' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-middo-dark font-sans min-h-screen flex items-center justify-center p-6">
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm max-w-md w-full p-6 space-y-4">
        <h1 class="text-2xl font-black text-middo-dark">
            {{ $is_wallet ? 'Add Middo Balance' : ($is_package ?? false ? 'Pay for meal package' : 'Middo Prepayment') }}
        </h1>
        <p class="text-sm text-gray-500">
            {{ $is_wallet ? 'Wallet top-up' : (($is_package ?? false) ? 'Monthly package checkout' : 'Corporate order checkout') }}
            · {{ $driver === 'eps' ? 'EPS' : $driver }} gateway
        </p>

        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 space-y-2 text-sm">
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Amount due</span>
                <span class="font-black text-middo-orange">৳{{ number_format($amount) }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Status</span>
                <span class="font-semibold">
                    @if($is_wallet && ($credited ?? false))
                        Credited to balance
                    @elseif($paid)
                        Paid
                    @else
                        Awaiting payment
                    @endif
                </span>
            </div>
            @if($is_wallet && ($credited ?? false) && $balance !== null)
                <div class="flex justify-between gap-3">
                    <span class="text-gray-500">New balance</span>
                    <span class="font-black text-[#1E4630]">৳{{ number_format($balance) }}</span>
                </div>
            @endif
        </div>

        @if(!empty($eps_message) && ! $paid)
            <p class="text-sm font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                {{ $eps_message }}
            </p>
        @endif

        @if($paid)
            @if($is_wallet)
                <p class="text-sm font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3">
                    Payment recorded and Middo Balance updated. You can close this window and return to Middo.
                </p>
            @else
                <p class="text-sm font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3">
                    @if($is_package ?? false)
                        Payment recorded. Return to Middo and enter the SMS OTP to create your package.
                    @else
                        Payment recorded. Return to Middo and confirm your order with the SMS OTP.
                    @endif
                </p>
                <p class="text-xs text-gray-400 break-all">Payment token: {{ $token }}</p>
            @endif
        @else
            <p class="text-sm text-gray-600">
                @if($is_wallet)
                    Complete this checkout to add funds to your Middo Balance for future office lunch orders.
                @else
                    @if($is_package ?? false)
                        Complete this checkout to prepay your monthly meal package. After payment you will confirm with OTP.
                    @else
                        Complete this prepayment to schedule meals when the receiver differs from the account holder,
                        or when you reach {{ \App\Support\MiddoSettings::fullPrepayFromActiveOrders() }}+ active orders.
                    @endif
                @endif
            </p>
            @if($driver === 'eps' && !empty($redirect_url))
                <a href="{{ $redirect_url }}"
                   class="block w-full text-center bg-middo-orange text-white py-3.5 rounded-xl font-bold hover:opacity-90 transition">
                    Continue to EPS · ৳{{ number_format($amount) }}
                </a>
                <p class="text-xs text-gray-400">
                    You will complete payment on EPS (Easy Payment System), then return here.
                </p>
            @else
                <form method="POST" action="{{ URL::temporarySignedRoute('corporate.gateway-prepay.confirm', now()->addMinutes(45), ['token' => $token]) }}">
                    @csrf
                    <button type="submit" class="w-full bg-middo-orange text-white py-3.5 rounded-xl font-bold hover:opacity-90 transition">
                        Pay ৳{{ number_format($amount) }} now
                    </button>
                </form>
                @if($driver === 'pseudo')
                    <p class="text-xs text-gray-400">
                        Pseudo gateway for development and automated tests.
                    </p>
                @endif
            @endif
        @endif
    </div>
</body>
</html>
