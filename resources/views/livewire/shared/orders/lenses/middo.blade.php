@if(($lensActions['force_cancel'] ?? false) || ($lensActions['release_rider'] ?? false))
    <div class="bg-white border border-amber-200 rounded-2xl p-5 shadow-sm space-y-3 mb-6">
        <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-amber-800">Ops force tools</h2>
            <p class="text-xs font-semibold text-gray-500 mt-0.5">Audited actions. Use only when the normal flow is stuck.</p>
        </div>
        <div>
            <label class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Reason (optional)</label>
            <input
                type="text"
                wire:model="forceReason"
                maxlength="255"
                placeholder="Why are you intervening?"
                class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2 font-semibold text-gray-800 focus:ring-middo-orange focus:border-middo-orange" />
        </div>
        <div class="flex flex-wrap gap-2">
            @if($lensActions['force_cancel'] ?? false)
                <button
                    type="button"
                    wire:click="forceCancel"
                    wire:confirm="Cancel this order before packed? Paid amount will be refunded to the corporate wallet."
                    class="inline-flex items-center px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-bold transition">
                    Cancel before packed
                </button>
            @endif
            @if($lensActions['release_rider'] ?? false)
                <button
                    type="button"
                    wire:click="releaseRider"
                    wire:confirm="Release the rider and return this order to packed? Delivery share will be voided and boxes returned to kitchen."
                    class="inline-flex items-center px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold transition">
                    Release rider → packed
                </button>
            @endif
        </div>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-3">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Corporate</h2>
            @if($this->corporateShowRoute())
                <a href="{{ $this->corporateShowRoute() }}" class="text-xs font-bold text-middo-orange hover:underline">Profile →</a>
            @endif
        </div>
        @if($corporate)
            <p class="text-lg font-black text-middo-dark">
                {{ $corporate->company_name ?: trim($corporate->first_name.' '.$corporate->last_name) }}
            </p>
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Contact</dt>
                    <dd class="font-semibold text-gray-800">{{ $corporate->first_name }} {{ $corporate->last_name }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Mobile</dt>
                    <dd class="font-mono font-semibold text-gray-800">{{ $corporate->mobile }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Balance</dt>
                    <dd class="font-mono font-bold text-middo-dark">৳{{ number_format((int) $corporate->balance) }}</dd>
                </div>
            </dl>
        @else
            <p class="text-sm text-gray-400 italic">No corporate account linked.</p>
        @endif
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-3">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Kitchen</h2>
            <div class="flex items-center gap-3">
                @if($this->kitchenShowRoute())
                    <a href="{{ $this->kitchenShowRoute() }}" class="text-xs font-bold text-middo-orange hover:underline">Profile →</a>
                @endif
                @if($this->kitchenOrdersRoute())
                    <a href="{{ $this->kitchenOrdersRoute() }}" class="text-xs font-bold text-gray-500 hover:text-middo-orange hover:underline">Orders →</a>
                @endif
            </div>
        </div>
        @if($kitchen)
            <p class="text-lg font-black text-middo-dark">{{ $kitchen->name ?: trim($kitchen->first_name.' '.$kitchen->last_name) }}</p>
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Mobile</dt>
                    <dd class="font-mono font-semibold text-gray-800">{{ $kitchen->mobile ?: '—' }}</dd>
                </div>
                @if($group)
                    <div>
                        <dt class="text-[11px] font-bold uppercase text-gray-400">Order group</dt>
                        <dd class="font-semibold text-gray-800">{{ $group->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-bold uppercase text-gray-400">Group menu</dt>
                        <dd class="font-semibold text-gray-800">
                            @if($group->menuItem && $this->menuShowRoute($group->menuItem))
                                <a href="{{ $this->menuShowRoute($group->menuItem) }}" class="text-middo-orange hover:underline">
                                    {{ $group->menuItem->name }} →
                                </a>
                            @else
                                {{ $group->menuItem?->name ?: '—' }}
                            @endif
                        </dd>
                    </div>
                @endif
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Dispatched</dt>
                    <dd class="font-semibold text-gray-800">
                        {{ $order->dispatched_at?->timezone('Asia/Dhaka')->format('M d, Y g:i A') ?: 'Not yet' }}
                    </dd>
                </div>
            </dl>
        @else
            <p class="text-sm text-gray-400 italic">No kitchen assigned yet.</p>
            @if($group)
                <p class="text-xs text-gray-500">Group: {{ $group->name }} (unassigned kitchen)</p>
            @endif
        @endif
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-3">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Delivery</h2>
            @if($this->deliveryShowRoute())
                <a href="{{ $this->deliveryShowRoute() }}" class="text-xs font-bold text-middo-orange hover:underline">Profile →</a>
            @endif
        </div>
        @if($rider)
            <p class="text-lg font-black text-middo-dark">{{ $rider->name ?: trim($rider->first_name.' '.$rider->last_name) }}</p>
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Mobile</dt>
                    <dd class="font-mono font-semibold text-gray-800">{{ $rider->mobile ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Status</dt>
                    <dd class="font-semibold text-gray-800">{{ str_replace('_', ' ', $order->order_status) }}</dd>
                </div>
            </dl>
        @elseif($order->isAwaitingRiderAccept())
            <p class="text-sm font-semibold text-amber-800">Awaiting rider accept</p>
            <p class="text-xs text-gray-500">Kitchen has dispatched; no rider assigned yet.</p>
        @else
            <p class="text-sm text-gray-400 italic">No delivery rider linked.</p>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    @include('livewire.shared.orders.partials.order-details')
    @include('livewire.shared.orders.partials.tracking-log')
</div>

@include('livewire.shared.orders.partials.money-tree')

@if(!empty($lensContext['boxes']))
    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm mt-6">
        <h2 class="text-lg font-bold text-middo-dark mb-3">Box custody</h2>
        <div class="flex flex-wrap gap-2">
            @foreach($lensContext['boxes'] as $box)
                <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-700 font-mono">
                    {{ $box['qr_code_id'] }}
                    <span class="text-[10px] uppercase text-gray-400 font-sans">{{ $box['asset_status'] }}</span>
                </span>
            @endforeach
        </div>
    </div>
@endif
