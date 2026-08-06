@props([
    'intent' => null,
])

@if($intent)
    @php
        $packageName = is_array($intent)
            ? ($intent['package_name'] ?? 'Package')
            : ($intent->package?->name ?? 'Package');
        $amount = is_array($intent)
            ? (int) ($intent['amount'] ?? 0)
            : (int) $intent->amount;
        $confirmUrl = is_array($intent)
            ? ($intent['confirm_url'] ?? '#')
            : $intent->confirmUrl();
    @endphp
    <div {{ $attributes->merge(['class' => 'rounded-2xl border border-amber-300 bg-amber-50 text-amber-950 px-4 py-4 shadow-sm']) }}>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-black tracking-tight">Payment received — finish creating your package</p>
                <p class="text-xs font-semibold text-amber-900/80 mt-1">
                    {{ $packageName }} · ৳{{ number_format($amount) }}
                </p>
                <p class="text-[11px] text-amber-800/70 mt-1">
                    Your payment is locked. Continue to finish package creation if the browser closed earlier.
                </p>
            </div>
            <a href="{{ $confirmUrl }}"
               class="shrink-0 inline-flex items-center justify-center rounded-xl bg-middo-orange px-4 py-2.5 text-xs font-black uppercase tracking-wider text-white hover:bg-[#733614] transition">
                Finish package →
            </a>
        </div>
    </div>
@endif
