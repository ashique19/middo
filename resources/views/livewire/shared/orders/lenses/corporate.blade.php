<div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-middo-dark">Buyer view</h2>
            <p class="text-xs font-semibold text-gray-400 mt-0.5">Corporate track parity — no Middo P&amp;L or partner shares</p>
        </div>
        @if($lensActions['cancel_pending'] ?? false)
            <button
                type="button"
                wire:click="corporateCancel"
                wire:confirm="Cancel this pending order? Paid amount will be refunded to the corporate wallet."
                class="inline-flex items-center px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-bold transition">
                Cancel order
            </button>
        @endif
    </div>

    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div>
            <dt class="text-[11px] font-bold uppercase text-gray-400">Menu</dt>
            <dd class="font-semibold text-gray-800 mt-0.5">{{ $order->menuItem?->name ?? 'Custom Selection' }}</dd>
        </div>
        <div>
            <dt class="text-[11px] font-bold uppercase text-gray-400">Quantity</dt>
            <dd class="font-mono font-bold text-middo-orange mt-0.5">{{ $order->quantity }}</dd>
        </div>
        <div>
            <dt class="text-[11px] font-bold uppercase text-gray-400">Total</dt>
            <dd class="font-mono font-bold text-gray-900 mt-0.5">৳{{ number_format((int) ($lensMoney['total_amount'] ?? $order->total_amount)) }}</dd>
        </div>
        <div>
            <dt class="text-[11px] font-bold uppercase text-gray-400">Payment</dt>
            <dd class="font-semibold text-gray-800 mt-0.5">
                {{ $lensMoney['payment_method_label'] ?? $paymentMethodLabel }}
                · {{ $lensMoney['payment_status'] ?? $order->payment_status }}
            </dd>
        </div>
        <div>
            <dt class="text-[11px] font-bold uppercase text-gray-400">Paid / Due</dt>
            <dd class="font-mono font-semibold text-gray-800 mt-0.5">
                ৳{{ number_format($lensMoney['amount_paid'] ?? 0) }}
                / ৳{{ number_format($lensMoney['amount_due'] ?? 0) }}
            </dd>
        </div>
        <div>
            <dt class="text-[11px] font-bold uppercase text-gray-400">Eligibility</dt>
            <dd class="font-semibold text-gray-800 mt-0.5">
                @if($lensContext['can_delete'] ?? false)
                    Can cancel before cutoff
                @else
                    Cancel locked
                @endif
                @if($lensContext['can_skip'] ?? false)
                    · Can skip package day
                @endif
            </dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-[11px] font-bold uppercase text-gray-400">Delivery</dt>
            <dd class="font-semibold text-gray-800 mt-0.5">{{ $order->address ?: '—' }}</dd>
            <dd class="text-xs text-gray-500 mt-1">
                {{ $party['receiver_name'] ?: '—' }}
                @if(!empty($party['receiver_mobile'])) · {{ $party['receiver_mobile'] }} @endif
            </dd>
        </div>
    </dl>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    @include('livewire.shared.orders.partials.tracking-log')
    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
        <h2 class="text-lg font-bold text-middo-dark mb-3">Support</h2>
        @forelse($lensContext['complaints'] ?? [] as $complaint)
            <div class="border-b border-gray-50 py-3 last:border-0">
                <p class="text-xs font-bold uppercase text-gray-400">{{ $complaint['category'] ?: 'Support' }}</p>
                <p class="text-sm text-gray-800 mt-0.5">{{ $complaint['message'] }}</p>
            </div>
        @empty
            <p class="text-sm font-semibold text-gray-400 italic">No support messages on this order.</p>
        @endforelse
    </div>
</div>
