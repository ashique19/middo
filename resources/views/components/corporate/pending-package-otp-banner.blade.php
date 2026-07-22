@props([
    'intent' => null,
])

@if($intent)
    <div {{ $attributes->merge(['class' => 'rounded-2xl border border-amber-300 bg-amber-50 text-amber-950 px-4 py-4 shadow-sm']) }}>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-black tracking-tight">Payment received — finish OTP to lock your package</p>
                <p class="text-xs font-semibold text-amber-900/80 mt-1">
                    {{ $intent->package?->name ?? 'Package' }} · ৳{{ number_format((int) $intent->amount) }}
                    · OTP sent to {{ $intent->mobile }}
                </p>
                <p class="text-[11px] text-amber-800/70 mt-1">
                    Your payment is locked. Enter the OTP to create the package. If you closed the browser earlier, continue here.
                </p>
            </div>
            <a href="{{ $intent->confirmUrl() }}"
               class="shrink-0 inline-flex items-center justify-center rounded-xl bg-middo-orange px-4 py-2.5 text-xs font-black uppercase tracking-wider text-white hover:bg-[#733614] transition">
                Enter OTP →
            </a>
        </div>
    </div>
@endif
