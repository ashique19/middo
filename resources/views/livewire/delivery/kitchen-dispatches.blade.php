<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="space-y-1">
        <a href="{{ route('delivery.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-3xl font-bold text-middo-dark">Kitchen dispatches</h1>
        <p class="text-sm font-semibold text-gray-500">
            Orders ready at kitchens. Accept to pick up, then deliver to the consumer.
        </p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            {{ $statusMessage }}
        </div>
    @endif

    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
            {{ $errorMessage }}
        </div>
    @endif

    @forelse($nodes as $order)
        <div
            wire:key="delivery-dispatch-{{ $order['id'] }}"
            class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-4 px-5 py-4 border-b border-gray-100 bg-gray-50/50">
                <div class="space-y-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono font-black text-middo-dark">#{{ $order['id'] }}</span>
                        <span class="text-sm font-bold text-gray-700">{{ $order['menu_name'] }}</span>
                        <span class="text-xs font-bold text-middo-orange">Qty {{ $order['quantity'] }}</span>
                    </div>
                    <p class="text-sm text-gray-600">
                        <span class="font-semibold">{{ $order['kitchen_name'] }}</span>
                        · {{ $order['date_label'] }} · {{ $order['delivery_time'] }}
                    </p>
                    <p class="text-sm text-gray-500">
                        Consumer: <span class="font-medium text-gray-700">{{ $order['customer_name'] }}</span>
                        · {{ $order['address'] }}
                    </p>
                    @if(count($order['box_codes']) > 0)
                        <p class="text-xs font-mono text-gray-400">
                            Boxes: {{ implode(', ', $order['box_codes']) }}
                        </p>
                    @endif
                </div>

                <div class="shrink-0">
                    @if($order['awaiting_accept'])
                        <button
                            type="button"
                            wire:click="acceptOrder({{ $order['id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="acceptOrder({{ $order['id'] }})"
                            wire:confirm="Accept this kitchen dispatch? You will pick up the boxes and deliver to the consumer."
                            class="inline-flex items-center px-4 py-2 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="acceptOrder({{ $order['id'] }})">Accept order</span>
                            <span wire:loading wire:target="acceptOrder({{ $order['id'] }})">Accepting...</span>
                        </button>
                    @elseif($order['accepted_by_me'])
                        <button
                            type="button"
                            wire:click="deliverToConsumer({{ $order['id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="deliverToConsumer({{ $order['id'] }})"
                            wire:confirm="Confirm delivery to the consumer?"
                            class="inline-flex items-center px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="deliverToConsumer({{ $order['id'] }})">Deliver to consumer</span>
                            <span wire:loading wire:target="deliverToConsumer({{ $order['id'] }})">Delivering...</span>
                        </button>
                    @elseif($order['accepted_by_other'])
                        <span class="inline-flex px-3 py-1.5 rounded-xl text-xs font-bold bg-gray-100 text-gray-600 border border-gray-200">
                            Accepted by {{ $order['rider_name'] ?? 'another rider' }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="bg-white border border-gray-200 rounded-2xl p-12 text-center shadow-sm">
            <p class="text-sm font-semibold text-gray-400 italic">No kitchen dispatches waiting right now.</p>
        </div>
    @endforelse

    @if($orders->hasPages())
        <div class="mt-4 px-1">
            {{ $orders->links() }}
        </div>
    @endif
</div>
