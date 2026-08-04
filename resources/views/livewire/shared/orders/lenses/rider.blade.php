@if($lensActions['release_rider'] ?? false)
    <div class="bg-white border border-amber-200 rounded-2xl p-5 shadow-sm space-y-3 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-amber-800">Ops intervene</h2>
                <p class="text-xs font-semibold text-gray-500 mt-0.5">Release this run back to packed if the rider is stuck.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <input
                    type="text"
                    wire:model="forceReason"
                    maxlength="255"
                    placeholder="Reason (optional)"
                    class="text-sm border border-gray-200 rounded-xl px-3 py-2 font-semibold text-gray-800 focus:ring-middo-orange focus:border-middo-orange" />
                <button
                    type="button"
                    wire:click="releaseRider"
                    wire:confirm="Release the rider and return this order to packed?"
                    class="inline-flex items-center px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold transition">
                    Release → packed
                </button>
            </div>
        </div>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-3 lg:col-span-2">
        <h2 class="text-lg font-bold text-middo-dark">Run sheet</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-[11px] font-bold uppercase text-gray-400">Pickup kitchen</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $lensContext['kitchen_name'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase text-gray-400">Drop time</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $order->delivery_time ?: '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-[11px] font-bold uppercase text-gray-400">Drop address</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $order->address ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase text-gray-400">Receiver</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">
                    {{ $party['receiver_name'] ?: '—' }}
                    @if(!empty($party['receiver_mobile'])) · {{ $party['receiver_mobile'] }} @endif
                </dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase text-gray-400">Menu / qty</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">
                    {{ $order->menuItem?->name ?? '—' }} · {{ $order->quantity }}
                </dd>
            </div>
            @if($lensContext['awaiting_rider'] ?? false)
                <div class="sm:col-span-2">
                    <p class="text-sm font-semibold text-amber-800">Awaiting rider accept</p>
                </div>
            @endif
        </dl>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-3">
        <h2 class="text-lg font-bold text-middo-dark">COD / commission</h2>
        <dl class="space-y-3 text-sm">
            <div>
                <dt class="text-[11px] font-bold uppercase text-gray-400">Due from customer</dt>
                <dd class="font-mono font-black text-middo-dark text-xl">৳{{ number_format($lensMoney['amount_due'] ?? 0) }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase text-gray-400">Cash collected</dt>
                <dd class="font-mono font-bold text-gray-800">৳{{ number_format($lensMoney['cash_collected'] ?? 0) }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase text-gray-400">Commission</dt>
                <dd class="font-mono font-bold text-sky-900">৳{{ number_format($lensMoney['commission'] ?? 0) }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase text-gray-400">Payment method</dt>
                <dd class="font-semibold text-gray-800">{{ $lensMoney['payment_method_label'] ?? $paymentMethodLabel }}</dd>
            </div>
        </dl>
        <p class="text-[11px] text-gray-400">Kitchen capacity and platform P&amp;L are hidden on this lens.</p>
    </div>
</div>

@if(!empty($lensContext['boxes']))
    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm mt-6">
        <h2 class="text-lg font-bold text-middo-dark mb-3">Boxes on this run</h2>
        <div class="flex flex-wrap gap-2">
            @foreach($lensContext['boxes'] as $box)
                <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-700 font-mono">
                    {{ $box['qr_code_id'] }}
                </span>
            @endforeach
        </div>
    </div>
@endif

<div class="mt-6">
    @include('livewire.shared.orders.partials.tracking-log')
</div>
