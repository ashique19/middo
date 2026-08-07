<div class="max-w-7xl mx-auto py-5 md:py-10 px-4 sm:px-6 space-y-5 md:space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('kitchen.dashboard') }}" class="hidden md:inline text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
            <h1 class="hidden md:block text-3xl font-bold text-middo-dark">My Active Orders</h1>
            <p class="text-sm font-semibold text-[#635347] md:text-gray-500">
                Mark each order Ready, wait for a rider claim, then Dispatch that order alone (parcels can go to different areas).
            </p>
            <div class="pt-2">
                <x-orders.view-mode-toggle :view-mode="$viewMode" :exportable="true" />
            </div>
        </div>

        <div class="w-full md:w-auto rounded-[1.35rem] border border-[#E5DCC8] bg-[#FDFBF7] px-4 py-3 md:px-5 md:py-4 shadow-sm md:min-w-[220px]">
            <p class="text-[11px] font-bold uppercase tracking-wider text-[#8A735C] mb-1">Dispatch deadline</p>
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
                    class="text-2xl font-black text-[#2B1A11] font-mono"
                    x-text="label">
                </div>
                <p class="text-xs text-[#8A735C] mt-1">Until next dispatch window</p>
            @else
                <p class="text-sm font-semibold text-[#8A735C]">No upcoming deadlines</p>
            @endif
            <p class="text-xs font-semibold text-[#635347] mt-2">
                Boxes at kitchen: <span class="text-middo-orange font-black">{{ $boxInventoryCount }}</span>
            </p>
        </div>
    </div>

    @if($statusMessage)
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            {{ $statusMessage }}
        </div>
    @endif

    @if($errorMessage)
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
            {{ $errorMessage }}
        </div>
    @endif

    @if($shortageGroupId)
        <div class="rounded-[1.35rem] border border-[#E5DCC8] bg-[#FDFBF7] p-4 md:p-5 shadow-sm space-y-3">
            <h2 class="text-lg font-bold text-middo-dark">Report shortage</h2>
            <p class="text-sm text-[#635347]">This releases the group back to the Middo pool for reassignment.</p>
            <textarea wire:model="shortageReason" rows="3"
                      class="w-full rounded-2xl border border-[#E5DCC8] px-3 py-3 text-sm focus:border-middo-orange focus:ring-middo-orange"
                      placeholder="What is short / unavailable…"></textarea>
            @error('shortageReason') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <button type="button" wire:click="confirmShortage"
                        class="inline-flex justify-center px-4 py-3 rounded-2xl bg-red-600 text-white text-sm font-bold hover:bg-red-700 min-h-[48px]">
                    Report & release
                </button>
                <button type="button" wire:click="cancelShortage"
                        class="inline-flex justify-center px-4 py-3 rounded-2xl border border-[#E5DCC8] text-sm font-bold text-[#2B1A11] hover:bg-[#F4EFE4] min-h-[48px]">
                    Cancel
                </button>
            </div>
        </div>
    @endif

    <livewire:kitchen.dispatch-order-modal />

    @if($viewMode === 'list')
        <x-operation.orders.table :orders="$flatOrders" :show-group="true" :hide-customer-pii="true" empty-message="No active order groups assigned to you." />
        @if($groups->hasPages())
            <div class="mt-4 px-1">{{ $groups->links() }}</div>
        @endif
    @else
    @forelse($groupNodes as $group)
        <article
            wire:key="kitchen-active-group-{{ $group['id'] }}"
            class="rounded-[1.35rem] border border-[#E5DCC8] {{ $group['color'] }} overflow-hidden bg-[#FDFBF7] shadow-[0_8px_24px_rgba(43,26,17,0.06)]">
            <div class="px-4 pt-4 pb-3 space-y-3 border-b border-[#EFE7D8]">
                <div class="flex flex-wrap items-start gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-base font-black text-[#2B1A11]">{{ $group['name'] }}</p>
                        <a href="{{ route('kitchen.menus.show', $group['menu_id']) }}"
                           class="text-sm font-bold text-middo-orange hover:underline">
                            {{ $group['menu_name'] }}
                        </a>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-lg font-black text-middo-orange">Qty {{ $group['total_quantity'] }}</p>
                        <p class="text-[11px] font-semibold text-[#8A735C]">{{ count($group['orders']) }} order(s)</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    @if(($group['package_source'] ?? null) === 'package')
                        <x-package-badge />
                    @elseif(($group['package_source'] ?? null) === 'mixed')
                        <x-package-badge label="Mixed" title="Includes package and à la carte orders" />
                    @endif
                    <span class="font-medium text-[#635347]">{{ $group['date_label'] }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    @if(!empty($group['can_mark_group_ready']))
                        <button type="button"
                                wire:click="markGroupReady({{ $group['id'] }})"
                                wire:confirm="Mark all processing orders in this group ready?"
                                class="inline-flex justify-center items-center px-3 py-3 rounded-2xl bg-sky-700 text-white text-sm font-bold hover:bg-sky-800 min-h-[48px]">
                            Mark group ready
                        </button>
                    @endif
                    @if(!empty($group['can_report_shortage']))
                        <button type="button"
                                wire:click="openShortage({{ $group['id'] }})"
                                class="inline-flex justify-center items-center px-3 py-3 rounded-2xl border border-amber-300 text-amber-900 text-sm font-bold hover:bg-amber-50 min-h-[48px]">
                            Shortage
                        </button>
                    @endif
                    @if(!empty($group['can_release']))
                        <button type="button"
                                wire:click="releaseGroup({{ $group['id'] }})"
                                wire:confirm="Release this group back to the Middo pool?"
                                class="inline-flex justify-center items-center px-3 py-3 rounded-2xl border border-[#D9CFBB] text-[#2B1A11] text-sm font-bold hover:bg-[#F4EFE4] min-h-[48px]">
                            Release
                        </button>
                    @endif
                </div>
            </div>

            @if(count($group['orders']) > 0)
                <ul class="divide-y divide-[#EFE7D8]">
                    @foreach($group['orders'] as $order)
                        <li
                            wire:key="kitchen-active-order-{{ $group['id'] }}-{{ $order['id'] }}"
                            class="px-4 py-4 space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 space-y-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-mono font-black text-[#2B1A11]">#{{ $order['id'] }}</span>
                                        @if(!empty($order['is_package']))
                                            <x-package-badge :title="$order['package_name'] ?? 'Meal package'" />
                                        @endif
                                        @if($order['box_low'])
                                            <span class="inline-flex px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wide bg-amber-100 text-amber-800 border border-amber-300">
                                                Box Low
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-sm font-semibold text-[#2B1A11] truncate">{{ $order['area_name'] ?? '—' }}</p>
                                    <p class="text-xs font-medium text-[#635347]">
                                        Qty <strong class="text-middo-orange">{{ $order['quantity'] }}</strong>
                                        · {{ $order['delivery_time'] }}
                                        · {{ ucfirst(str_replace('_', ' ', $order['order_status'])) }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-2">
                                @if($order['dispatched'])
                                    <span class="inline-flex justify-center px-3 py-3 rounded-2xl text-sm font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 min-h-[48px] items-center">
                                        Dispatched
                                        @if(!empty($order['rider_name']))
                                            · {{ $order['rider_name'] }}
                                        @endif
                                    </span>
                                @elseif(!empty($order['can_mark_ready']))
                                    <button
                                        type="button"
                                        wire:click="markReady({{ $order['id'] }})"
                                        class="inline-flex justify-center items-center px-3 py-3 rounded-2xl border border-sky-300 text-sky-900 text-sm font-bold hover:bg-sky-50 min-h-[48px]">
                                        Mark ready
                                    </button>
                                @elseif(!empty($order['can_dispatch']))
                                    <button
                                        type="button"
                                        wire:click="$dispatch('open-dispatch-order-modal', { orderId: {{ $order['id'] }} })"
                                        class="inline-flex justify-center items-center px-3 py-3 rounded-2xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold min-h-[48px]">
                                        Dispatch to {{ $order['rider_name'] ?? 'rider' }}
                                    </button>
                                @elseif(!empty($order['awaiting_rider_claim']))
                                    <span class="inline-flex justify-center px-3 py-3 rounded-2xl text-sm font-bold bg-amber-50 text-amber-900 border border-amber-200 min-h-[48px] items-center">
                                        Waiting for rider to accept
                                    </span>
                                @elseif(!empty($order['is_rider_assigned']))
                                    <span class="inline-flex justify-center px-3 py-3 rounded-2xl text-sm font-bold bg-sky-50 text-sky-800 border border-sky-200 min-h-[48px] items-center">
                                        Claimed by {{ $order['rider_name'] ?? 'rider' }}
                                    </span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="px-4 py-8 text-sm text-[#8A735C] italic text-center">No active orders in this group.</p>
            @endif
        </article>
    @empty
        <div class="bg-[#FDFBF7] border border-[#E5DCC8] rounded-[1.35rem] p-10 text-center shadow-sm">
            <p class="text-sm font-semibold text-[#8A735C] italic">No active order groups assigned to you.</p>
        </div>
    @endforelse

    @if($groups->hasPages())
        <div class="mt-4 px-1">
            {{ $groups->links() }}
        </div>
    @endif
    @endif
</div>
