<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-3xl font-bold text-middo-dark">Active Orders</h1>
            <p class="text-sm font-semibold text-gray-500">
                @if(\App\Support\StaffPortal::isDayOps())
                    Drag orders between groups or onto other orders. Drop on the ungrouped zone to remove from a group.
                @else
                    Browse and export active orders. Kitchen assignment is managed by ops.
                @endif
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
            <select
                wire:model.live="statusFilter"
                class="text-sm border border-gray-200 rounded-xl px-3 py-2 font-semibold text-gray-700 focus:ring-middo-orange focus:border-middo-orange">
                <option value="all">All statuses</option>
                <option value="pending">Pending</option>
                <option value="processing">Processing</option>
                <option value="ready">Ready</option>
                <option value="rider_assigned">Rider assigned</option>
                <option value="packed">Packed</option>
                <option value="on_the_way_to_delivery">On the way</option>
            </select>
            <select
                wire:model.live="kitchenFilter"
                class="text-sm border border-gray-200 rounded-xl px-3 py-2 font-semibold text-gray-700 focus:ring-middo-orange focus:border-middo-orange">
                <option value="all">All kitchens</option>
                <option value="unassigned">Unassigned kitchen</option>
                @foreach($kitchenOptions as $kitchen)
                    <option value="{{ $kitchen['id'] }}">{{ $kitchen['name'] }}</option>
                @endforeach
            </select>
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                <input type="checkbox" wire:model.live="awaitingRiderOnly" class="rounded border-gray-300 text-middo-orange focus:ring-middo-orange" />
                Awaiting rider
            </label>
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                <input type="checkbox" wire:model.live="lateOnly" class="rounded border-gray-300 text-middo-orange focus:ring-middo-orange" />
                Late to pack
            </label>
            <x-orders.view-mode-toggle :view-mode="$viewMode" :exportable="true" />
        </div>
    </div>

    @if($hasUnassignedGroups && \App\Support\StaffPortal::isDayOps())
        <div class="rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 flex flex-wrap items-center gap-3">
            <p class="text-sm font-semibold text-amber-900 flex-1 min-w-[12rem]">
                Bulk assign unassigned groups in the current view
            </p>
            <select
                wire:model="bulkKitchenId"
                class="text-sm border border-amber-200 rounded-xl px-3 py-2 font-semibold text-gray-700 focus:ring-middo-orange focus:border-middo-orange bg-white">
                <option value="">Select kitchen…</option>
                @foreach($kitchenOptions as $kitchen)
                    <option value="{{ $kitchen['id'] }}">{{ $kitchen['name'] }}</option>
                @endforeach
            </select>
            <button
                type="button"
                wire:click="bulkAssignUnassignedKitchen"
                wire:confirm="Assign all visible unassigned groups to the selected kitchen?"
                class="inline-flex items-center px-4 py-2 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition">
                Assign all unassigned
            </button>
        </div>
    @endif

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            {{ $statusMessage }}
        </div>
    @endif

    @if($viewMode === 'list')
        <x-operation.orders.table :orders="$this->flatOrders" :show-group="true" empty-message="No active orders right now." />
    @else
    @forelse($dateSections as $section)
        @php $isExpanded = in_array($section['date'], $expandedDates, true); @endphp

        <div
            wire:key="active-orders-date-{{ $section['date'] }}"
            class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">

            <button
                type="button"
                wire:click="toggleDate('{{ $section['date'] }}')"
                class="w-full flex items-center justify-between gap-4 px-5 py-4 text-left hover:bg-gray-50 transition">
                <span class="text-base font-black text-middo-dark inline-flex items-center gap-2 flex-wrap">
                    {{ $section['label'] }} ({{ $section['count'] }} {{ str('order')->plural($section['count']) }} · Qty {{ $section['total_quantity'] }})
                    @if(!empty($section['has_package_orders']))
                        <x-package-badge />
                    @endif
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
                    <div class="flex justify-end">
                        <button
                            type="button"
                            wire:click="autoGroupForDate('{{ $section['date'] }}')"
                            wire:loading.attr="disabled"
                            wire:target="autoGroupForDate"
                            class="inline-flex items-center px-4 py-2 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="autoGroupForDate">Auto Group</span>
                            <span wire:loading wire:target="autoGroupForDate">Grouping...</span>
                        </button>
                    </div>

                    {{-- Grouped orders tree --}}
                    @foreach($section['groups'] as $group)
                        <div
                            wire:key="order-group-tree-{{ $group['id'] }}"
                            class="rounded-xl border {{ $group['color'] }} overflow-hidden"
                            x-data
                            @dragover.prevent
                            @drop.prevent="(() => { const id = parseInt($event.dataTransfer.getData('orderId')); if (id) $wire.handleOrderDrop(id, 'group', {{ $group['id'] }}); })()">
                            <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-inherit bg-white/40">
                                <span class="text-sm font-black text-middo-dark">
                                    <x-orders.group-link :group-id="$group['id']" :name="$group['name']" class="text-sm font-black text-middo-dark" />
                                </span>
                                <button
                                    type="button"
                                    @click="$dispatch('open-assign-kitchen-modal', { orderGroupId: {{ $group['id'] }} })"
                                    class="inline-flex px-2 py-0.5 rounded text-xs font-bold bg-white/80 border border-gray-300 text-gray-700 hover:border-middo-orange hover:text-middo-orange transition cursor-pointer">
                                    Kitchen: {{ $group['kitchen_label'] }}
                                </button>
                                <span class="text-xs font-semibold text-gray-600">{{ $group['menu_name'] }}</span>
                                @if(($group['package_source'] ?? null) === 'package')
                                    <x-package-badge />
                                @elseif(($group['package_source'] ?? null) === 'mixed')
                                    <x-package-badge label="Mixed" title="Includes package and à la carte orders" />
                                @endif
                                <span class="text-xs font-bold text-middo-orange">Qty {{ $group['total_quantity'] }}</span>
                                <span class="text-xs text-gray-500">{{ count($group['orders']) }} order(s)</span>
                            </div>

                            <ul class="divide-y divide-black/5">
                                @foreach($group['orders'] as $order)
                                    <li
                                        wire:key="group-order-{{ $group['id'] }}-{{ $order['id'] }}"
                                        draggable="true"
                                        @dragstart="$event.dataTransfer.setData('orderId', '{{ $order['id'] }}')"
                                        @dragover.prevent
                                        @drop.prevent="(() => { const id = parseInt($event.dataTransfer.getData('orderId')); if (id && id !== {{ $order['id'] }}) $wire.handleOrderDrop(id, 'order', {{ $order['id'] }}); })()"
                                        class="flex items-center gap-3 px-4 py-3 hover:bg-white/60 transition cursor-grab active:cursor-grabbing">
                                        <span class="shrink-0 w-6 text-center text-gray-400 font-mono text-xs select-none">└</span>
                                        <div class="flex-1 min-w-0 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-1 text-sm">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <x-orders.id-link :order-id="$order['id']" @click.stop />
                                                @if(!empty($order['is_package']))
                                                    <x-package-badge :title="$order['package_name'] ?? 'Meal package'" />
                                                    @if(!empty($order['package_subscription_id']))
                                                        <a href="{{ auth()->user()?->role?->name === 'admin' ? route('admin.subscriptions.show', $order['package_subscription_id']) : route('operation.subscriptions.show', $order['package_subscription_id']) }}"
                                                           class="text-[10px] font-bold text-sky-700 hover:underline"
                                                           @click.stop>
                                                            Sub #{{ $order['package_subscription_id'] }}
                                                        </a>
                                                    @endif
                                                @endif
                                            </div>
                                            <span class="font-medium truncate">{{ $order['customer_name'] }}</span>
                                            <span class="truncate text-gray-700">{{ $order['menu_name'] }}</span>
                                            <span class="text-gray-500">Qty <strong class="text-middo-orange">{{ $order['quantity'] }}</strong> · {{ $order['delivery_time'] }}</span>
                                        </div>
                                        <x-orders.view-link :order-id="$order['id']" compact @click.stop />
                                        <button
                                            type="button"
                                            @click.stop="$wire.ungroupOrder({{ $order['id'] }})"
                                            title="Remove from group"
                                            class="shrink-0 w-7 h-7 flex items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 font-black text-sm hover:bg-red-100 transition">
                                            −
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach

                    {{-- Ungrouped orders --}}
                    <div
                        class="rounded-xl border border-dashed border-gray-300 bg-gray-50/50"
                        x-data
                        @dragover.prevent
                        @drop.prevent="(() => { const id = parseInt($event.dataTransfer.getData('orderId')); if (id) $wire.handleOrderDrop(id, 'ungrouped'); })()">
                        <div class="px-4 py-2 border-b border-gray-200 bg-gray-100/80">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Ungrouped</span>
                            <span class="text-xs text-gray-400 ml-2">— drop here to ungroup</span>
                        </div>

                        @if(count($section['ungrouped']) > 0)
                            <ul class="divide-y divide-gray-200">
                                @foreach($section['ungrouped'] as $order)
                                    <li
                                        wire:key="ungrouped-order-{{ $order['id'] }}"
                                        draggable="true"
                                        @dragstart="$event.dataTransfer.setData('orderId', '{{ $order['id'] }}')"
                                        @dragover.prevent
                                        @drop.prevent="(() => { const id = parseInt($event.dataTransfer.getData('orderId')); if (id && id !== {{ $order['id'] }}) $wire.handleOrderDrop(id, 'order', {{ $order['id'] }}); })()"
                                        class="flex items-center gap-3 px-4 py-3 hover:bg-white transition cursor-grab active:cursor-grabbing">
                                        <div class="flex-1 min-w-0 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-1 text-sm">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <x-orders.id-link :order-id="$order['id']" @click.stop />
                                                <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-gray-200 text-gray-600">Ungrouped</span>
                                                @if(!empty($order['is_package']))
                                                    <x-package-badge :title="$order['package_name'] ?? 'Meal package'" />
                                                @endif
                                            </div>
                                            <span class="font-medium truncate">{{ $order['customer_name'] }}</span>
                                            <span class="truncate text-gray-700">{{ $order['menu_name'] }}</span>
                                            <span class="text-gray-500">Qty <strong class="text-middo-orange">{{ $order['quantity'] }}</strong> · {{ $order['delivery_time'] }}</span>
                                        </div>
                                        <x-orders.view-link :order-id="$order['id']" compact @click.stop />
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="px-4 py-6 text-sm text-gray-400 italic text-center">All orders are grouped.</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @empty
        <div class="bg-white border border-gray-200 rounded-2xl p-12 text-center shadow-sm">
            <p class="text-sm font-semibold text-gray-400 italic">No active orders right now.</p>
        </div>
    @endforelse
    @endif
</div>
