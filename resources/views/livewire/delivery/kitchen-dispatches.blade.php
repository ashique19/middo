<div class="max-w-7xl mx-auto py-6 sm:py-10 px-4 sm:px-6 space-y-5 sm:space-y-6">
    <div class="space-y-1">
        <a href="{{ route('delivery.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-2xl sm:text-3xl font-bold text-middo-dark">Kitchen dispatches</h1>
        <p class="text-sm font-semibold text-gray-500">
            Accept a ready order → wait for kitchen pack → pick up → deliver. Each order is its own run.
        </p>
    </div>

    @if($statusMessage || $errorMessage)
        <div class="sticky top-20 z-30 space-y-3">
            @if($statusMessage)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 shadow-sm">
                    {{ $statusMessage }}
                </div>
            @endif

            @if($errorMessage)
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800 shadow-sm">
                    {{ $errorMessage }}
                </div>
            @endif
        </div>
    @endif

    @forelse($nodes as $order)
        <div
            wire:key="delivery-dispatch-{{ $order['id'] }}"
            class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="flex flex-col gap-4 px-4 sm:px-5 py-4 border-b border-gray-100 bg-gray-50/50 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                <div class="space-y-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono font-black text-middo-dark">#{{ $order['id'] }}</span>
                        <span class="text-sm font-bold text-gray-700 break-words">{{ $order['menu_name'] }}</span>
                        <span class="text-xs font-bold text-middo-orange">Qty {{ $order['quantity'] }}</span>
                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200">
                            {{ $order['status_label'] }}
                        </span>
                        @if(!empty($order['area_name']))
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-bold bg-violet-50 text-violet-800 border border-violet-200">
                                {{ $order['area_name'] }}
                            </span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-600">
                        <span class="font-semibold">{{ $order['kitchen_name'] }}</span>
                        · {{ $order['date_label'] }} · {{ $order['delivery_time'] }}
                    </p>
                    @if(!empty($order['kitchen_mobile']) || !empty($order['kitchen_address']))
                        <p class="text-xs text-gray-500 break-words">
                            @if(!empty($order['kitchen_mobile'])) {{ $order['kitchen_mobile'] }} @endif
                            @if(!empty($order['kitchen_mobile']) && !empty($order['kitchen_address'])) · @endif
                            @if(!empty($order['kitchen_address'])) {{ $order['kitchen_address'] }} @endif
                        </p>
                    @endif
                    <p class="text-sm text-gray-500 break-words">
                        Consumer: <span class="font-medium text-gray-700">{{ $order['customer_name'] }}</span>
                        · {{ $order['address'] }}
                    </p>
                    @if(!empty($order['show_commission']))
                        <p class="text-xs font-semibold text-emerald-700">
                            Commission ৳{{ number_format($order['commission_amount']) }}
                        </p>
                    @endif
                    @if(count($order['box_codes']) > 0)
                        <p class="text-xs font-mono text-gray-400 break-all">
                            Boxes: {{ implode(', ', $order['box_codes']) }}
                        </p>
                    @endif
                </div>

                <div class="w-full sm:w-auto sm:shrink-0">
                    @if($order['awaiting_accept'])
                        <button
                            type="button"
                            wire:click="acceptOrder({{ $order['id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="acceptOrder({{ $order['id'] }})"
                            wire:confirm="Accept this order run? Kitchen will pack for you, then you confirm pickup."
                            class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2.5 sm:py-2 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="acceptOrder({{ $order['id'] }})">Accept run</span>
                            <span wire:loading wire:target="acceptOrder({{ $order['id'] }})">Accepting...</span>
                        </button>
                    @elseif(!empty($order['awaiting_kitchen_pack']))
                        <span class="inline-flex px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-50 text-amber-900 border border-amber-200">
                            Waiting for kitchen to pack
                        </span>
                    @elseif(!empty($order['can_pick_up']))
                        <button
                            type="button"
                            wire:click="pickUpOrder({{ $order['id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="pickUpOrder({{ $order['id'] }})"
                            wire:confirm="Confirm pickup? Boxes move to your custody and status becomes On the way."
                            class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2.5 sm:py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-sm font-bold transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="pickUpOrder({{ $order['id'] }})">Picked up</span>
                            <span wire:loading wire:target="pickUpOrder({{ $order['id'] }})">Saving...</span>
                        </button>
                    @elseif($order['can_mark_delivered'])
                        <button
                            type="button"
                            wire:click="deliverToConsumer({{ $order['id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="deliverToConsumer({{ $order['id'] }})"
                            wire:confirm="Mark as Delivered? Boxes will transfer to the customer."
                            class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2.5 sm:py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="deliverToConsumer({{ $order['id'] }})">Delivered</span>
                            <span wire:loading wire:target="deliverToConsumer({{ $order['id'] }})">Saving...</span>
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
        <div class="bg-white border border-gray-200 rounded-2xl p-10 sm:p-12 text-center shadow-sm">
            <p class="text-sm font-semibold text-gray-400 italic">No kitchen runs waiting right now.</p>
        </div>
    @endforelse

    @if($orders->hasPages())
        <div class="mt-4 px-1 overflow-x-auto">
            {{ $orders->links() }}
        </div>
    @endif
</div>
