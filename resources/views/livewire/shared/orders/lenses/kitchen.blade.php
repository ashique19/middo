@if($lensActions['mark_ready'] ?? false)
    <div class="bg-white border border-sky-200 rounded-2xl p-5 shadow-sm space-y-3 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-sky-800">Kitchen actions</h2>
                <p class="text-xs font-semibold text-gray-500 mt-0.5">Mark ready when prep is complete (before pack/dispatch).</p>
            </div>
            <button
                type="button"
                wire:click="markReady"
                wire:confirm="Mark this order ready?"
                class="inline-flex items-center px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-sm font-bold transition">
                Mark ready
            </button>
        </div>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-3 lg:col-span-2">
        <h2 class="text-lg font-bold text-middo-dark">Cook / dispatch</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-[11px] font-bold uppercase text-gray-400">Group</dt>
                <dd class="mt-0.5">
                    <x-orders.group-link
                        :group-id="$lensContext['group_id'] ?? null"
                        :name="$lensContext['group_name'] ?? '—'"
                        class="text-gray-800"
                    />
                </dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase text-gray-400">Menu</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $order->menuItem?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase text-gray-400">Qty</dt>
                <dd class="font-mono font-bold text-middo-orange mt-0.5">{{ $order->quantity }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase text-gray-400">Area</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $party['area_name'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase text-gray-400">Dispatch deadline</dt>
                <dd @class([
                    'font-semibold mt-0.5',
                    'text-red-700' => ($lensContext['deadline']['is_late'] ?? false),
                    'text-gray-800' => !($lensContext['deadline']['is_late'] ?? false),
                ])>
                    {{ $lensContext['deadline']['label'] ?? '—' }}
                    @if($lensContext['deadline']['is_late'] ?? false)
                        · late
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase text-gray-400">Dispatched</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $lensContext['dispatched_at'] ?? 'Not yet' }}</dd>
            </div>
        </dl>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-3">
        <h2 class="text-lg font-bold text-middo-dark">Kitchen share</h2>
        <p class="text-2xl font-black font-mono text-amber-900">৳{{ number_format($lensMoney['kitchen_share'] ?? 0) }}</p>
        @if(!empty($lensMoney['payable_status']))
            <p class="text-xs font-semibold text-gray-500">
                Payable ৳{{ number_format($lensMoney['payable_amount'] ?? 0) }}
                · {{ $lensMoney['payable_status'] }}
            </p>
        @else
            <p class="text-xs font-semibold text-gray-400">Share accrues on kitchen dispatch.</p>
        @endif
        <p class="text-[11px] text-gray-400">Corporate wallet and platform P&amp;L are hidden on this lens.</p>
    </div>
</div>

@if(!empty($lensContext['group_mates']))
    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm mt-6">
        <h2 class="text-lg font-bold text-middo-dark mb-3">Group mates</h2>
        <div class="divide-y divide-gray-50">
            @foreach($lensContext['group_mates'] as $mate)
                <div class="py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <div>
                        <span class="font-mono font-bold text-middo-dark">#{{ $mate['id'] }}</span>
                        <span class="text-gray-600">· {{ $mate['area_name'] ?? '—' }} · Qty {{ $mate['quantity'] }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-bold uppercase text-gray-400">{{ str_replace('_', ' ', $mate['order_status']) }}</span>
                        @if($this->orderShowUrl($mate['id'], 'kitchen'))
                            <a href="{{ $this->orderShowUrl($mate['id'], 'kitchen') }}" class="text-xs font-bold text-middo-orange hover:underline">Open →</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if(!empty($lensContext['boxes']))
    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm mt-6">
        <h2 class="text-lg font-bold text-middo-dark mb-3">Boxes</h2>
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
