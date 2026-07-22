<div class="min-h-screen bg-[#F7F4EB] text-[#2B1A11] antialiased font-sans p-4 md:p-8">
    <div class="max-w-md mx-auto w-full space-y-6">
        <div>
            <a href="{{ route('corporates.packages.index') }}" class="text-xs font-bold text-middo-orange hover:underline">← Meal packages</a>
            <p class="text-[11px] font-black uppercase tracking-wider text-middo-orange mt-3">Confirm package</p>
            <h1 class="text-2xl font-black tracking-tight mt-1">{{ $packageName ?: 'Monthly package' }}</h1>
            <p class="text-sm font-semibold text-[#635347] mt-1">
                @if($paid)
                    Payment received · enter OTP to finish
                @else
                    Complete payment, then confirm with OTP
                @endif
            </p>
        </div>

        <div class="bg-white border border-[#DDD3BE] rounded-2xl p-5 space-y-4 shadow-sm">
            <div class="flex justify-between gap-3 text-sm">
                <span class="text-gray-500 font-semibold">Amount paid</span>
                <span class="font-black text-middo-orange">৳{{ number_format($amount) }}</span>
            </div>
            <div class="flex justify-between gap-3 text-sm">
                <span class="text-gray-500 font-semibold">Receiver</span>
                <span class="font-bold text-right">{{ $customerName }}</span>
            </div>
            <div class="flex justify-between gap-3 text-sm">
                <span class="text-gray-500 font-semibold">Mobile</span>
                <span class="font-bold">{{ $mobile }}</span>
            </div>
            <div class="flex justify-between gap-3 text-sm">
                <span class="text-gray-500 font-semibold">Payment</span>
                <span class="font-bold {{ $paid ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $paid ? 'Paid' : 'Awaiting payment' }}
                </span>
            </div>
        </div>

        @if($errorMessage)
            <div class="rounded-xl border border-red-200 bg-red-50 text-red-800 text-sm font-semibold px-4 py-3">
                {{ $errorMessage }}
            </div>
        @endif

        @if($statusMessage)
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm font-semibold px-4 py-3">
                {{ $statusMessage }}
            </div>
        @endif

        @if(! $paid)
            @if($paymentUrl)
                <a href="{{ $paymentUrl }}"
                   class="block w-full text-center bg-middo-orange hover:bg-[#733614] text-white font-black text-xs uppercase tracking-wider py-3.5 rounded-xl transition">
                    Continue to payment · ৳{{ number_format($amount) }}
                </a>
            @endif
        @else
            <div class="bg-white border border-[#DDD3BE] rounded-2xl p-5 space-y-3 shadow-sm">
                <p class="text-xs font-semibold text-gray-600">Enter the 4-digit OTP sent to {{ $mobile }}</p>
                @if($debugOtp)
                    <p class="text-xs font-black text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-xl px-3 py-2">
                        Debug OTP: {{ $debugOtp }}
                    </p>
                @endif
                <input wire:model.live="otpInput" type="text" inputmode="numeric" maxlength="4"
                       class="w-full border-gray-200 rounded-xl text-sm p-2.5 tracking-[0.4em] text-center font-black"
                       placeholder="••••">
                @error('otpInput')
                    <p class="text-xs font-semibold text-red-600">{{ $message }}</p>
                @enderror
                <button type="button" wire:click="confirm" wire:loading.attr="disabled"
                        class="w-full bg-emerald-800 hover:bg-emerald-900 text-white font-black text-xs uppercase tracking-wider py-3 rounded-xl disabled:opacity-60">
                    <span wire:loading.remove wire:target="confirm">Create package</span>
                    <span wire:loading wire:target="confirm">Creating…</span>
                </button>
                <button type="button" wire:click="resendOtp" class="w-full text-xs font-bold text-middo-orange underline">
                    Resend OTP
                </button>
            </div>
        @endif
    </div>
</div>
