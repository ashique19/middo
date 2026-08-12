<div class="max-w-7xl mx-auto py-6 sm:py-10 px-4 sm:px-6 space-y-5 sm:space-y-6">
    <div class="space-y-1">
        <a href="{{ route('delivery.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-2xl sm:text-3xl font-bold text-middo-dark">Middo boxes pending run</h1>
        <p class="text-sm font-semibold text-gray-500">
            Grouped by box run. Warehouse pickups assigned to you and boxes already in your custody.
            Showing {{ $boxes->count() }} of {{ $boxes->total() }}.
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

    <div class="md:hidden space-y-4">
        @forelse($runGroups as $group)
            <div wire:key="pending-run-m-{{ $group['key'] }}" class="rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/80 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-bold text-middo-dark">{{ $group['title'] }}</p>
                        <p class="text-xs font-semibold text-gray-500">
                            {{ $group['box_count'] }} {{ str('box')->plural($group['box_count']) }}
                            @if($group['kitchen_name']) · {{ $group['kitchen_name'] }} @endif
                        </p>
                    </div>
                    @if(count($group['accept_all_ids']) > 1 && $group['request_id'])
                        <button
                            type="button"
                            wire:click="acceptRunPickup({{ $group['request_id'] }})"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center px-3 py-1.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                            Accept all ({{ count($group['accept_all_ids']) }})
                        </button>
                    @endif
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($group['nodes'] as $box)
                        <div wire:key="pending-box-m-{{ $box['id'] }}" class="p-4 space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 space-y-1">
                                    <p class="font-mono text-base font-bold text-middo-dark break-all">{{ $box['qr_code_id'] }}</p>
                                    <p class="text-sm text-gray-600">{{ $box['model'] }}</p>
                                </div>
                                <span class="shrink-0 inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200">
                                    {{ $box['run_label'] }}
                                </span>
                            </div>

                            @if($box['kitchen_name'])
                                <div class="text-sm border-t border-gray-100 pt-2 space-y-0.5">
                                    <p class="font-semibold text-middo-dark">{{ $box['kitchen_name'] }}</p>
                                    @if($box['kitchen_mobile'])
                                        <p class="text-xs text-gray-500">{{ $box['kitchen_mobile'] }}</p>
                                    @endif
                                    @if($box['kitchen_address'])
                                        <p class="text-xs text-gray-500 break-words">{{ $box['kitchen_address'] }}</p>
                                    @endif
                                </div>
                            @endif

                            <p class="text-xs text-gray-600">
                                @if($box['order_id'])
                                    Order #{{ $box['order_id'] }}
                                    @if($box['menu_name']) · {{ $box['menu_name'] }} @endif
                                    @if($box['customer_name']) · {{ $box['customer_name'] }} @endif
                                @else
                                    Warehouse transfer
                                @endif
                            </p>

                            @if($box['can_deliver_to_warehouse'] ?? false)
                                <button
                                    type="button"
                                    wire:click="deliverToWarehouse({{ $box['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="deliverToWarehouse({{ $box['id'] }})"
                                    wire:confirm="Mark this empty box as handed over to Middo ops?"
                                    class="w-full inline-flex justify-center items-center px-3 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                                    Hand to Middo ops
                                </button>
                            @elseif($box['can_claim_kitchen_return'] ?? false)
                                <button
                                    type="button"
                                    wire:click="claimKitchenReturn({{ $box['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="claimKitchenReturn({{ $box['id'] }})"
                                    wire:confirm="Claim this kitchen→ops warehouse run?"
                                    class="w-full inline-flex justify-center items-center px-3 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                                    Claim run
                                </button>
                            @elseif($box['can_accept_pickup'] ?? false)
                                <button
                                    type="button"
                                    wire:click="acceptWarehouseStock({{ $box['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="acceptWarehouseStock({{ $box['id'] }})"
                                    class="w-full inline-flex justify-center items-center px-3 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                                    Accept
                                </button>
                            @elseif($box['can_accept_kitchen_return'] ?? false)
                                <button
                                    type="button"
                                    wire:click="acceptKitchenReturn({{ $box['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="acceptKitchenReturn({{ $box['id'] }})"
                                    wire:confirm="Accept this empty box at the kitchen and start the warehouse run?"
                                    class="w-full inline-flex justify-center items-center px-3 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                                    Accept box & start run
                                </button>
                            @elseif($box['can_hand_warehouse_stock'] ?? false)
                                <button
                                    type="button"
                                    wire:click="handWarehouseStock({{ $box['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="handWarehouseStock({{ $box['id'] }})"
                                    class="w-full inline-flex justify-center items-center px-3 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                                    Hand to kitchen
                                </button>
                            @elseif($box['can_hand_to_kitchen'])
                                <button
                                    type="button"
                                    wire:click="handToKitchen({{ $box['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="handToKitchen({{ $box['id'] }})"
                                    class="w-full inline-flex justify-center items-center px-3 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                                    Hand to kitchen
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-sm font-semibold text-gray-400 italic">
                No Middo boxes in your pending runs. When Ops stages stock for you, it appears here as Ready for pickup.
            </div>
        @endforelse
    </div>

    <div class="hidden md:block space-y-4">
        @forelse($runGroups as $group)
            <div wire:key="pending-run-d-{{ $group['key'] }}" class="bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-bold text-middo-dark">{{ $group['title'] }}</p>
                        <p class="text-xs font-semibold text-gray-500">
                            {{ $group['box_count'] }} {{ str('box')->plural($group['box_count']) }}
                            @if($group['kitchen_name']) · {{ $group['kitchen_name'] }} @endif
                        </p>
                    </div>
                    @if(count($group['accept_all_ids']) > 1 && $group['request_id'])
                        <button
                            type="button"
                            wire:click="acceptRunPickup({{ $group['request_id'] }})"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center px-3 py-1.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                            Accept all ({{ count($group['accept_all_ids']) }})
                        </button>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[720px]">
                        <thead>
                            <tr class="bg-white border-b border-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                                <th class="p-4">QR Code</th>
                                <th class="p-4">Model</th>
                                <th class="p-4">Status</th>
                                <th class="p-4">Destination</th>
                                <th class="p-4">Details</th>
                                <th class="p-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @foreach($group['nodes'] as $box)
                                <tr wire:key="pending-box-{{ $box['id'] }}" class="hover:bg-gray-50/70 transition">
                                    <td class="p-4 font-mono font-bold text-middo-dark">{{ $box['qr_code_id'] }}</td>
                                    <td class="p-4 text-gray-700">{{ $box['model'] }}</td>
                                    <td class="p-4">
                                        <span class="inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200">
                                            {{ $box['run_label'] }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-gray-700">
                                        @if($box['kitchen_name'])
                                            <div class="font-semibold text-middo-dark">{{ $box['kitchen_name'] }}</div>
                                            @if($box['kitchen_mobile'])
                                                <div class="text-xs text-gray-500">{{ $box['kitchen_mobile'] }}</div>
                                            @endif
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-4 text-gray-600 text-xs">
                                        @if($box['order_id'])
                                            Order #{{ $box['order_id'] }}
                                            @if($box['menu_name']) · {{ $box['menu_name'] }} @endif
                                        @else
                                            Warehouse transfer
                                        @endif
                                    </td>
                                    <td class="p-4 text-right whitespace-nowrap">
                                        @if($box['can_deliver_to_warehouse'] ?? false)
                                            <button type="button" wire:click="deliverToWarehouse({{ $box['id'] }})" wire:loading.attr="disabled"
                                                    wire:confirm="Mark this empty box as handed over to Middo ops?"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                                                Hand to Middo ops
                                            </button>
                                        @elseif($box['can_claim_kitchen_return'] ?? false)
                                            <button type="button" wire:click="claimKitchenReturn({{ $box['id'] }})" wire:loading.attr="disabled"
                                                    wire:confirm="Claim this kitchen→ops warehouse run?"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                                                Claim run
                                            </button>
                                        @elseif($box['can_accept_pickup'] ?? false)
                                            <button type="button" wire:click="acceptWarehouseStock({{ $box['id'] }})" wire:loading.attr="disabled"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                                                Accept
                                            </button>
                                        @elseif($box['can_accept_kitchen_return'] ?? false)
                                            <button type="button" wire:click="acceptKitchenReturn({{ $box['id'] }})" wire:loading.attr="disabled"
                                                    wire:confirm="Accept this empty box at the kitchen and start the warehouse run?"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                                                Accept box & start run
                                            </button>
                                        @elseif($box['can_hand_warehouse_stock'] ?? false)
                                            <button type="button" wire:click="handWarehouseStock({{ $box['id'] }})" wire:loading.attr="disabled"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                                                Hand to kitchen
                                            </button>
                                        @elseif($box['can_hand_to_kitchen'])
                                            <button type="button" wire:click="handToKitchen({{ $box['id'] }})" wire:loading.attr="disabled"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                                                Hand to kitchen
                                            </button>
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="bg-white shadow-md border border-gray-100 rounded-2xl p-12 text-center text-sm font-semibold text-gray-400 italic">
                No Middo boxes in your pending runs. When Ops stages stock for you, it appears here as Ready for pickup.
            </div>
        @endforelse
    </div>

    @if($boxes->hasPages())
        <div class="mt-2 px-1">
            {{ $boxes->links() }}
        </div>
    @endif
</div>
