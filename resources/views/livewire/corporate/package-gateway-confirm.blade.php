<div class="min-h-screen bg-[#F7F4EB] text-[#2B1A11] antialiased font-sans p-4 md:p-8">
    <div class="max-w-md mx-auto w-full space-y-6">
        <div>
            <a href="{{ route('corporates.packages.index') }}" class="text-xs font-bold text-middo-orange hover:underline">← Meal packages</a>
            <p class="text-[11px] font-black uppercase tracking-wider text-middo-orange mt-3">Package checkout</p>
            <h1 class="text-2xl font-black tracking-tight mt-1">{{ $packageName ?: 'Monthly package' }}</h1>
            <p class="text-sm font-semibold text-[#635347] mt-1">
                @if($paid)
                    Payment received · finishing your package
                @else
                    Complete payment to create your package
                @endif
            </p>
        </div>

        <div class="bg-white border border-[#DDD3BE] rounded-2xl p-5 space-y-4 shadow-sm">
            <div class="flex justify-between gap-3 text-sm">
                <span class="text-gray-500 font-semibold">Amount</span>
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

        @if($statusMessage && ! $errorMessage)
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm font-semibold px-4 py-3" role="status">
                {{ $statusMessage }}
            </div>
        @endif

        @if($errorMessage)
            <div class="rounded-xl border border-red-200 bg-red-50 text-red-800 text-sm font-semibold px-4 py-3" role="alert">
                {{ $errorMessage }}
            </div>
        @endif

        @if(! $paid && $paymentUrl)
            <a href="{{ $paymentUrl }}"
               class="block w-full text-center bg-middo-orange hover:bg-[#733614] text-white font-black text-xs uppercase tracking-wider py-3.5 rounded-xl transition">
                Continue to payment · ৳{{ number_format($amount) }}
            </a>
        @elseif($paid)
            <button type="button" wire:click="retryCompletion" wire:loading.attr="disabled"
                    class="w-full bg-emerald-800 hover:bg-emerald-900 text-white font-black text-xs uppercase tracking-wider py-3 rounded-xl disabled:opacity-60"
                    data-testid="package-confirm-retry">
                <span wire:loading.remove wire:target="retryCompletion">Finish package creation</span>
                <span wire:loading wire:target="retryCompletion">Creating…</span>
            </button>
        @endif

        <a href="{{ route('corporates.dashboard') }}"
           class="inline-flex w-full items-center justify-center border border-[#EBE3D3] bg-[#F7F4EB] hover:bg-[#EFE9DC] text-[#635347] py-3 rounded-xl text-sm font-bold transition">
            Go to Dashboard
        </a>
    </div>
</div>
