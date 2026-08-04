<div class="max-w-7xl mx-auto py-5 md:py-10 px-4 sm:px-6 space-y-5 md:space-y-6">
    <div class="space-y-1">
        <a href="{{ route('kitchen.dashboard') }}" class="hidden md:inline text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="hidden md:block text-3xl font-bold text-middo-dark">Middo Order Groups</h1>
        <p class="text-sm font-semibold text-[#635347] md:text-gray-500">
            Unassigned groups you can claim for your kitchen.
        </p>
        <div class="inline-flex items-center gap-2 rounded-full border border-[#E5DCC8] bg-[#FDFBF7] px-3 py-1.5 text-xs font-bold text-[#635347]">
            Capacity {{ $openGroupCount }} / {{ $allowedOpenGroups }}
            <span class="text-middo-orange">· {{ $remainingSlots }} left</span>
        </div>
    </div>

    @if($atCapacity)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">
            You are at capacity. Finish packing/dispatch (or release a group) before accepting another.
        </div>
    @endif

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

    @if($declineGroupId)
        <div class="rounded-[1.35rem] border border-[#E5DCC8] bg-[#FDFBF7] p-4 md:p-5 shadow-sm space-y-3">
            <h2 class="text-lg font-bold text-middo-dark">Decline order group</h2>
            <p class="text-sm text-[#635347]">Provide a reason. The group stays in the Middo pool for other kitchens.</p>
            <textarea wire:model="declineReason" rows="3"
                      class="w-full rounded-2xl border border-[#E5DCC8] px-3 py-3 text-sm focus:border-middo-orange focus:ring-middo-orange"
                      placeholder="Reason for declining…"></textarea>
            @error('declineReason') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <button type="button" wire:click="confirmDecline"
                        class="inline-flex justify-center px-4 py-3 rounded-2xl bg-red-600 text-white text-sm font-bold hover:bg-red-700 min-h-[48px]">
                    Confirm decline
                </button>
                <button type="button" wire:click="cancelDecline"
                        class="inline-flex justify-center px-4 py-3 rounded-2xl border border-[#E5DCC8] text-sm font-bold text-[#2B1A11] hover:bg-[#F4EFE4] min-h-[48px]">
                    Cancel
                </button>
            </div>
        </div>
    @endif

    @forelse($groupNodes as $group)
        <article
            wire:key="middo-group-{{ $group['id'] }}"
            class="rounded-[1.35rem] border border-[#E5DCC8] {{ $group['color'] }} overflow-hidden bg-[#FDFBF7] shadow-[0_8px_24px_rgba(43,26,17,0.06)]">
            <div class="px-4 pt-4 pb-3 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-base font-black text-[#2B1A11]">{{ $group['name'] }}</p>
                        <p class="text-sm font-bold text-[#635347] truncate">{{ $group['menu_name'] }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-lg font-black text-middo-orange">Qty {{ $group['total_quantity'] }}</p>
                        <p class="text-[11px] font-semibold text-[#8A735C]">{{ count($group['orders']) }} order(s)</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if(($group['package_source'] ?? null) === 'package')
                        <x-package-badge />
                    @elseif(($group['package_source'] ?? null) === 'mixed')
                        <x-package-badge label="Mixed" title="Includes package and à la carte orders" />
                    @endif
                    <span class="text-xs font-medium text-[#635347]">{{ $group['date_label'] }}</span>
                    <span @class([
                        'text-[11px] font-bold uppercase tracking-wide px-2 py-1 rounded-lg border',
                        'bg-rose-50 text-rose-800 border-rose-200' => !empty($group['accept_window']['closing_soon']),
                        'bg-emerald-50 text-emerald-800 border-emerald-200' => ($group['accept_window']['state'] ?? '') === 'open' && empty($group['accept_window']['closing_soon']),
                        'bg-amber-50 text-amber-800 border-amber-200' => ($group['accept_window']['state'] ?? '') === 'not_yet',
                        'bg-gray-100 text-gray-600 border-gray-200' => ($group['accept_window']['state'] ?? '') === 'closed',
                    ])>
                        {{ $group['accept_window']['label'] ?? '—' }}
                    </span>
                    @if(!empty($group['accept_window']['closing_soon']))
                        <span class="text-[11px] font-bold uppercase tracking-wide px-2 py-1 rounded-lg border bg-rose-100 text-rose-900 border-rose-300">
                            Closing soon
                        </span>
                    @endif
                    @if(!empty($group['had_shortage']))
                        <span class="text-[11px] font-bold uppercase tracking-wide px-2 py-1 rounded-lg border bg-rose-50 text-rose-800 border-rose-200"
                              title="{{ $group['shortage_reason'] ?? 'Prior shortage' }}">
                            Prior shortage
                        </span>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                    <button
                        type="button"
                        wire:click="acceptOrder({{ $group['id'] }})"
                        wire:loading.attr="disabled"
                        wire:target="acceptOrder({{ $group['id'] }})"
                        @disabled(empty($group['can_accept']))
                        wire:confirm="Accept this order group?"
                        class="inline-flex justify-center items-center order-1 sm:order-2 px-4 py-3.5 rounded-2xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition disabled:opacity-60 disabled:cursor-not-allowed min-h-[52px]">
                        <span wire:loading.remove wire:target="acceptOrder({{ $group['id'] }})">
                            @if($atCapacity)
                                At capacity
                            @elseif(($group['accept_window']['state'] ?? '') === 'not_yet')
                                Window not open
                            @elseif(($group['accept_window']['state'] ?? '') === 'closed')
                                Window closed
                            @else
                                Accept group
                            @endif
                        </span>
                        <span wire:loading wire:target="acceptOrder({{ $group['id'] }})">Accepting…</span>
                    </button>
                    <button
                        type="button"
                        wire:click="openDecline({{ $group['id'] }})"
                        class="inline-flex justify-center items-center order-2 sm:order-1 px-4 py-3.5 rounded-2xl border border-[#D9CFBB] text-[#2B1A11] text-sm font-bold hover:bg-[#F4EFE4] transition min-h-[52px]">
                        Decline
                    </button>
                </div>
            </div>

            @if(count($group['orders']) > 0)
                <ul class="border-t border-[#EFE7D8] divide-y divide-[#EFE7D8] bg-white/50">
                    @foreach($group['orders'] as $order)
                        <li
                            wire:key="middo-order-{{ $group['id'] }}-{{ $order['id'] }}"
                            class="px-4 py-3 flex items-start justify-between gap-3">
                            <div class="min-w-0 space-y-0.5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono font-bold text-[#2B1A11]">#{{ $order['id'] }}</span>
                                    @if(!empty($order['is_package']))
                                        <x-package-badge :title="$order['package_name'] ?? 'Meal package'" />
                                    @endif
                                </div>
                                <p class="text-sm font-semibold text-[#2B1A11] truncate">{{ $order['customer_name'] }}</p>
                                <p class="text-xs text-[#635347]">
                                    Qty <strong class="text-middo-orange">{{ $order['quantity'] }}</strong>
                                    · {{ $order['delivery_time'] }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="px-4 py-6 text-sm text-[#8A735C] italic text-center border-t border-[#EFE7D8]">No orders in this group.</p>
            @endif
        </article>
    @empty
        <div class="bg-[#FDFBF7] border border-[#E5DCC8] rounded-[1.35rem] p-10 text-center shadow-sm">
            <p class="text-sm font-semibold text-[#8A735C] italic">No unassigned Middo order groups right now.</p>
        </div>
    @endforelse

    @if($groups->hasPages())
        <div class="mt-4 px-1">
            {{ $groups->links() }}
        </div>
    @endif
</div>
