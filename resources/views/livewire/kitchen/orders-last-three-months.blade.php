<div class="max-w-7xl mx-auto py-6 sm:py-10 px-4 sm:px-6 space-y-5 sm:space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
            <h1 class="text-2xl sm:text-3xl font-bold text-middo-dark">Last 3 months</h1>
            <p class="text-sm font-semibold text-gray-500">
                Order groups assigned to your kitchen from {{ $rangeLabel }}. Showing {{ $groups->count() }} of {{ $groups->total() }}.
            </p>
        </div>
        <x-orders.view-mode-toggle :view-mode="$viewMode" :exportable="true" />
    </div>

    @if($viewMode === 'list')
        <x-operation.orders.table :orders="$flatOrders" :show-group="true" :hide-customer-pii="true" empty-message="No orders in this range." />
        @if($groups->hasPages())
            <div class="mt-4 px-1">{{ $groups->links() }}</div>
        @endif
    @else
    @forelse($groupNodes as $group)
        <div
            wire:key="kitchen-3m-group-{{ $group['id'] }}"
            class="rounded-xl border {{ $group['color'] }} overflow-hidden bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-inherit bg-white/40">
                <span class="text-sm font-black text-middo-dark">{{ $group['name'] }}</span>
                <a
                    href="{{ route('kitchen.menus.show', $group['menu_id']) }}"
                    class="inline-flex px-2 py-0.5 rounded text-xs font-bold bg-white/80 border border-gray-300 text-middo-orange hover:border-middo-orange transition">
                    Menu: {{ $group['menu_name'] }}
                </a>
                <span class="text-xs font-medium text-gray-500">{{ $group['date_label'] }}</span>
                <span class="text-xs font-bold text-middo-orange">Qty {{ $group['total_quantity'] }}</span>
                <span class="text-xs text-gray-500">{{ count($group['orders']) }} order(s)</span>
            </div>

            @if(count($group['orders']) > 0)
                <ul class="divide-y divide-black/5">
                    @foreach($group['orders'] as $order)
                        <li
                            wire:key="kitchen-3m-order-{{ $group['id'] }}-{{ $order['id'] }}"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-white/60 transition">
                            <span class="shrink-0 w-6 text-center text-gray-400 font-mono text-xs select-none">└</span>
                            <div class="flex-1 min-w-0 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-1 text-sm">
                                <span class="font-mono font-bold text-middo-dark">#{{ $order['id'] }}</span>
                                <span class="font-medium truncate">{{ $order['area_name'] ?? '—' }}</span>
                                <span class="truncate text-gray-700">{{ $order['menu_name'] }}</span>
                                <span class="text-gray-500">
                                    Qty <strong class="text-middo-orange">{{ $order['quantity'] }}</strong>
                                    · {{ $order['delivery_time'] }}
                                    · {{ ucfirst($order['order_status']) }}
                                </span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="px-4 py-6 text-sm text-gray-400 italic text-center">No orders in this group.</p>
            @endif
        </div>
    @empty
        <div class="bg-white border border-gray-200 rounded-2xl p-12 text-center shadow-sm">
            <p class="text-sm font-semibold text-gray-400 italic">No order groups in the last 3 months.</p>
        </div>
    @endforelse

    @if($groups->hasPages())
        <div class="mt-4 px-1">
            {{ $groups->links() }}
        </div>
    @endif
    @endif
</div>
