<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-3xl font-bold text-middo-dark">Order History</h1>
            <p class="text-sm font-semibold text-gray-500">
                Past deliveries grouped by date and order group. Showing {{ $orders->count() }} of {{ $orders->total() }} orders.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <select
                wire:model.live="packageFilter"
                class="text-sm border border-gray-200 rounded-xl px-3 py-2 font-semibold text-gray-700 focus:ring-middo-orange focus:border-middo-orange">
                <option value="all">All sources</option>
                <option value="package">Package only</option>
                <option value="alacarte">À la carte only</option>
            </select>
            <x-orders.view-mode-toggle :view-mode="$viewMode" :exportable="true" />
        </div>
    </div>

    @if($viewMode === 'list')
        <x-operation.orders.table :orders="$flatOrders" :show-group="true" empty-message="No past orders found." />
        <div>{{ $orders->links() }}</div>
    @else
    @forelse($dateSections as $section)
        @php $isExpanded = in_array($section['date'], $expandedDates, true); @endphp

        <div
            wire:key="history-date-{{ $section['date'] }}"
            class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">

            <button
                type="button"
                wire:click="toggleDate('{{ $section['date'] }}')"
                class="w-full flex items-center justify-between gap-4 px-5 py-4 text-left hover:bg-gray-50 transition">
                <span class="text-base font-black text-middo-dark">
                    {{ $section['label'] }} ({{ $section['count'] }} {{ str('order')->plural($section['count']) }} · Qty {{ $section['total_quantity'] }})
                </span>
                <svg
                    @class([
                        'w-5 h-5 text-gray-500 shrink-0 transition-transform',
                        'rotate-180' => $isExpanded,
                    ])
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            @if($isExpanded)
                <div class="border-t border-gray-100 px-5 py-4 space-y-4">
                    @foreach($section['groups'] as $group)
                        <div
                            wire:key="history-group-{{ $section['date'] }}-{{ $group['id'] }}"
                            class="rounded-xl border {{ $group['color'] }} overflow-hidden">
                            <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-inherit bg-white/40">
                                <span class="text-sm font-black text-middo-dark">{{ $group['name'] }}</span>
                                <button
                                    type="button"
                                    @click="$dispatch('open-assign-kitchen-modal', { orderGroupId: {{ $group['id'] }} })"
                                    class="inline-flex px-2 py-0.5 rounded text-xs font-bold bg-white/80 border border-gray-300 text-gray-700 hover:border-middo-orange hover:text-middo-orange transition cursor-pointer">
                                    Kitchen: {{ $group['kitchen_label'] }}
                                </button>
                                <span class="text-xs font-semibold text-gray-600">{{ $group['menu_name'] }}</span>
                                <span class="text-xs font-bold text-middo-orange">Qty {{ $group['total_quantity'] }}</span>
                                <span class="text-xs text-gray-500">{{ count($group['orders']) }} order(s)</span>
                            </div>

                            <ul class="divide-y divide-black/5">
                                @foreach($group['orders'] as $order)
                                    <li
                                        wire:key="history-group-order-{{ $group['id'] }}-{{ $order['id'] }}"
                                        class="flex items-center gap-3 px-4 py-3 hover:bg-white/60 transition">
                                        <span class="shrink-0 w-6 text-center text-gray-400 font-mono text-xs select-none">└</span>
                                        <div class="flex-1 min-w-0 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-1 text-sm">
                                            <span class="font-mono font-bold text-middo-dark">#{{ $order['id'] }}</span>
                                            <span class="font-medium truncate">{{ $order['customer_name'] }}</span>
                                            <span class="truncate text-gray-700">{{ $order['menu_name'] }}</span>
                                            <span class="text-gray-500">Qty <strong class="text-middo-orange">{{ $order['quantity'] }}</strong> · {{ $order['delivery_time'] }}</span>
                                        </div>
                                        <a
                                            href="{{ \App\Support\StaffOrderRoutes::show($order['id']) }}"
                                            class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-lg border border-gray-200 bg-white text-[11px] font-bold text-middo-dark hover:border-middo-orange hover:text-middo-orange transition">
                                            View
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach

                    @if(count($section['ungrouped']) > 0)
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/50 overflow-hidden">
                            <div class="px-4 py-2 border-b border-gray-200 bg-gray-100/80">
                                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Ungrouped</span>
                            </div>
                            <ul class="divide-y divide-gray-200">
                                @foreach($section['ungrouped'] as $order)
                                    <li
                                        wire:key="history-ungrouped-{{ $section['date'] }}-{{ $order['id'] }}"
                                        class="flex items-center gap-3 px-4 py-3 hover:bg-white transition">
                                        <span class="shrink-0 w-6 text-center text-gray-400 font-mono text-xs select-none">└</span>
                                        <div class="flex-1 min-w-0 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-1 text-sm">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-mono font-bold text-middo-dark">#{{ $order['id'] }}</span>
                                                <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 border border-gray-200 text-gray-500">
                                                    Ungrouped
                                                </span>
                                            </div>
                                            <span class="font-medium truncate">{{ $order['customer_name'] }}</span>
                                            <span class="truncate text-gray-700">{{ $order['menu_name'] }}</span>
                                            <span class="text-gray-500">Qty <strong class="text-middo-orange">{{ $order['quantity'] }}</strong> · {{ $order['delivery_time'] }}</span>
                                        </div>
                                        <a
                                            href="{{ \App\Support\StaffOrderRoutes::show($order['id']) }}"
                                            class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-lg border border-gray-200 bg-white text-[11px] font-bold text-middo-dark hover:border-middo-orange hover:text-middo-orange transition">
                                            View
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @empty
        <div class="bg-white border border-gray-200 rounded-2xl p-12 text-center shadow-sm">
            <p class="text-sm font-semibold text-gray-400 italic">No order history found.</p>
        </div>
    @endforelse

    @if($orders->hasPages())
        <div class="mt-4 px-1">
            {{ $orders->links() }}
        </div>
    @endif
    @endif
</div>
