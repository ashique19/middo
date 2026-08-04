<div class="max-w-7xl mx-auto py-5 md:py-10 px-4 sm:px-6 space-y-5 md:space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('kitchen.dashboard') }}" class="hidden md:inline text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
            <h1 class="hidden md:block text-3xl font-bold text-middo-dark">My Active Orders</h1>
            <p class="text-sm font-semibold text-[#635347] md:text-gray-500">
                Mark prep Ready, then Dispatch to hand off to a rider. Release returns a group to Middo before ready.
            </p>
            <div class="pt-2">
                <x-orders.view-mode-toggle :view-mode="$viewMode" :exportable="true" />
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-sm min-w-[220px]">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Dispatch deadline</p>
            @if($nextDispatchDeadlineIso)
                <div
                    x-data="{
                        deadline: new Date('{{ $nextDispatchDeadlineIso }}'),
                        label: '—',
                        tick() {
                            const diff = this.deadline - new Date();
                            if (diff <= 0) { this.label = 'Past deadline'; return; }
                            const total = Math.floor(diff / 1000);
                            const h = Math.floor(total / 3600);
                            const m = Math.floor((total % 3600) / 60);
                            const s = total % 60;
                            this.label = String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
                        }
                    }"
                    x-init="tick(); setInterval(() => tick(), 1000)"
                    class="text-2xl font-black text-middo-dark font-mono"
                    x-text="label">
                </div>
                <p class="text-xs text-gray-500 mt-1">Until next dispatch window</p>
            @else
                <p class="text-sm font-semibold text-gray-400">No upcoming deadlines</p>
            @endif
            <p class="text-xs font-semibold text-gray-500 mt-2">
                Boxes at kitchen: <span class="text-middo-orange">{{ $boxInventoryCount }}</span>
            </p>
        </div>
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

    @if($shortageGroupId)
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm space-y-3">
            <h2 class="text-lg font-bold text-middo-dark">Report shortage</h2>
            <p class="text-sm text-gray-500">This releases the group back to the Middo pool for reassignment.</p>
            <textarea wire:model="shortageReason" rows="3"
                      class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange"
                      placeholder="What is short / unavailable…"></textarea>
            @error('shortageReason') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="confirmShortage"
                        class="inline-flex px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-bold hover:bg-red-700">
                    Report & release
                </button>
                <button type="button" wire:click="cancelShortage"
                        class="inline-flex px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
            </div>
        </div>
    @endif

    <livewire:kitchen.dispatch-order-modal />

    @if($viewMode === 'list')
        <x-operation.orders.table :orders="$flatOrders" :show-group="true" empty-message="No active order groups assigned to you." />
        @if($groups->hasPages())
            <div class="mt-4 px-1">{{ $groups->links() }}</div>
        @endif
    @else
    @forelse($groupNodes as $group)
        <div
            wire:key="kitchen-active-group-{{ $group['id'] }}"
            class="rounded-xl border {{ $group['color'] }} overflow-hidden bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-inherit bg-white/40">
                <span class="text-sm font-black text-middo-dark">{{ $group['name'] }}</span>
                <a
                    href="{{ route('kitchen.menus.show', $group['menu_id']) }}"
                    class="inline-flex px-2 py-0.5 rounded text-xs font-bold bg-white/80 border border-gray-300 text-middo-orange hover:border-middo-orange transition">
                    Menu: {{ $group['menu_name'] }}
                </a>
                @if(($group['package_source'] ?? null) === 'package')
                    <x-package-badge />
                @elseif(($group['package_source'] ?? null) === 'mixed')
                    <x-package-badge label="Mixed" title="Includes package and à la carte orders" />
                @endif
                <span class="text-xs font-medium text-gray-500">{{ $group['date_label'] }}</span>
                <span class="text-xs font-bold text-middo-orange">Qty {{ $group['total_quantity'] }}</span>
                <span class="text-xs text-gray-500">{{ count($group['orders']) }} order(s)</span>
                <div class="ml-auto flex flex-wrap gap-2">
                    @if(!empty($group['can_mark_group_ready']))
                        <button type="button"
                                wire:click="markGroupReady({{ $group['id'] }})"
                                wire:confirm="Mark all processing orders in this group ready?"
                                class="inline-flex px-3 py-1.5 rounded-xl border border-sky-200 text-sky-800 text-xs font-bold hover:bg-sky-50">
                            Mark group ready
                        </button>
                    @endif
                    @if(!empty($group['can_report_shortage']))
                        <button type="button"
                                wire:click="openShortage({{ $group['id'] }})"
                                class="inline-flex px-3 py-1.5 rounded-xl border border-amber-200 text-amber-800 text-xs font-bold hover:bg-amber-50">
                            Shortage
                        </button>
                    @endif
                    @if(!empty($group['can_release']))
                        <button type="button"
                                wire:click="releaseGroup({{ $group['id'] }})"
                                wire:confirm="Release this group back to the Middo pool?"
                                class="inline-flex px-3 py-1.5 rounded-xl border border-gray-300 text-gray-700 text-xs font-bold hover:bg-gray-50">
                            Release
                        </button>
                    @endif
                </div>
            </div>

            @if(count($group['orders']) > 0)
                <ul class="divide-y divide-black/5">
                    @foreach($group['orders'] as $order)
                        <li
                            wire:key="kitchen-active-order-{{ $group['id'] }}-{{ $order['id'] }}"
                            class="flex flex-wrap items-center gap-3 px-4 py-3 hover:bg-white/60 transition">
                            <span class="shrink-0 w-6 text-center text-gray-400 font-mono text-xs select-none">└</span>
                            <div class="flex-1 min-w-0 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-1 text-sm">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono font-bold text-middo-dark">#{{ $order['id'] }}</span>
                                    @if(!empty($order['is_package']))
                                        <x-package-badge :title="$order['package_name'] ?? 'Meal package'" />
                                    @endif
                                </div>
                                <span class="font-medium truncate">{{ $order['customer_name'] }}</span>
                                <span class="truncate text-gray-700">{{ $order['menu_name'] }}</span>
                                <span class="text-gray-500">
                                    Qty <strong class="text-middo-orange">{{ $order['quantity'] }}</strong>
                                    · {{ $order['delivery_time'] }}
                                    · {{ ucfirst($order['order_status']) }}
                                    @if(!empty($order['payment_method_label']))
                                        · <span class="text-sky-800 font-semibold">{{ $order['payment_method_label'] }}</span>
                                    @endif
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0 ml-auto">
                                @if($order['box_low'])
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-black uppercase tracking-wide bg-amber-100 text-amber-800 border border-amber-300">
                                        Box Low
                                    </span>
                                @endif

                                @if($order['dispatched'])
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        Dispatched
                                    </span>
                                @elseif(!empty($order['can_mark_ready']))
                                    <button
                                        type="button"
                                        wire:click="markReady({{ $order['id'] }})"
                                        class="inline-flex items-center px-3 py-1.5 rounded-xl border border-sky-300 text-sky-800 text-xs font-bold hover:bg-sky-50 transition">
                                        Mark ready
                                    </button>
                                @elseif(!empty($order['can_dispatch']))
                                    <button
                                        type="button"
                                        wire:click="$dispatch('open-dispatch-order-modal', { orderId: {{ $order['id'] }} })"
                                        class="inline-flex items-center px-3 py-1.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition">
                                        Dispatch
                                    </button>
                                @elseif(!empty($order['is_ready']))
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200">
                                        Ready
                                    </span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="px-4 py-6 text-sm text-gray-400 italic text-center">No active orders in this group.</p>
            @endif
        </div>
    @empty
        <div class="bg-white border border-gray-200 rounded-2xl p-12 text-center shadow-sm">
            <p class="text-sm font-semibold text-gray-400 italic">No active order groups assigned to you.</p>
        </div>
    @endforelse

    @if($groups->hasPages())
        <div class="mt-4 px-1">
            {{ $groups->links() }}
        </div>
    @endif
    @endif
</div>
