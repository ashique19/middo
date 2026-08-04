<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="space-y-1">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-3xl font-bold text-middo-dark">Middo Order Groups</h1>
        <p class="text-sm font-semibold text-gray-500">
            Unassigned menu order groups. Accepting assigns the group to your kitchen.
        </p>
        <p class="text-xs font-semibold text-gray-500">
            Capacity: {{ $openGroupCount }} / {{ $allowedOpenGroups }} open groups
            · {{ $remainingSlots }} slot(s) remaining
        </p>
    </div>

    @if($atCapacity)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">
            You are at capacity. Finish packing/dispatch (or release a group) before accepting another.
        </div>
    @endif

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

    @if($declineGroupId)
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm space-y-3">
            <h2 class="text-lg font-bold text-middo-dark">Decline order group</h2>
            <p class="text-sm text-gray-500">Provide a reason. The group stays in the Middo pool for other kitchens.</p>
            <textarea wire:model="declineReason" rows="3"
                      class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange"
                      placeholder="Reason for declining…"></textarea>
            @error('declineReason') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="confirmDecline"
                        class="inline-flex px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-bold hover:bg-red-700">
                    Confirm decline
                </button>
                <button type="button" wire:click="cancelDecline"
                        class="inline-flex px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
            </div>
        </div>
    @endif

    @forelse($groupNodes as $group)
        <div
            wire:key="middo-group-{{ $group['id'] }}"
            class="rounded-xl border {{ $group['color'] }} overflow-hidden bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-inherit bg-white/40">
                <span class="text-sm font-black text-middo-dark">{{ $group['name'] }}</span>
                <span class="text-xs font-semibold text-gray-600">{{ $group['menu_name'] }}</span>
                @if(($group['package_source'] ?? null) === 'package')
                    <x-package-badge />
                @elseif(($group['package_source'] ?? null) === 'mixed')
                    <x-package-badge label="Mixed" title="Includes package and à la carte orders" />
                @endif
                <span class="text-xs font-medium text-gray-500">{{ $group['date_label'] }}</span>
                <span class="text-xs font-bold text-middo-orange">Qty {{ $group['total_quantity'] }}</span>
                <span class="text-xs text-gray-500">{{ count($group['orders']) }} order(s)</span>
                <span @class([
                    'text-[11px] font-bold uppercase tracking-wide px-2 py-0.5 rounded border',
                    'bg-emerald-50 text-emerald-800 border-emerald-200' => ($group['accept_window']['state'] ?? '') === 'open',
                    'bg-amber-50 text-amber-800 border-amber-200' => ($group['accept_window']['state'] ?? '') === 'not_yet',
                    'bg-gray-100 text-gray-600 border-gray-200' => ($group['accept_window']['state'] ?? '') === 'closed',
                ])>
                    {{ $group['accept_window']['label'] ?? '—' }}
                </span>
                @if(!empty($group['had_shortage']))
                    <span class="text-[11px] font-bold uppercase tracking-wide px-2 py-0.5 rounded border bg-rose-50 text-rose-800 border-rose-200"
                          title="{{ $group['shortage_reason'] ?? 'Prior shortage' }}">
                        Prior shortage
                    </span>
                @endif
                <div class="ml-auto flex flex-wrap gap-2">
                    <button
                        type="button"
                        wire:click="openDecline({{ $group['id'] }})"
                        class="inline-flex items-center px-3 py-2 rounded-xl border border-gray-300 text-gray-700 text-sm font-bold hover:bg-gray-50 transition">
                        Decline
                    </button>
                    <button
                        type="button"
                        wire:click="acceptOrder({{ $group['id'] }})"
                        wire:loading.attr="disabled"
                        wire:target="acceptOrder({{ $group['id'] }})"
                        @disabled(empty($group['can_accept']))
                        wire:confirm="Accept this order group?"
                        class="inline-flex items-center px-4 py-2 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="acceptOrder({{ $group['id'] }})">
                            @if($atCapacity)
                                At capacity
                            @elseif(($group['accept_window']['state'] ?? '') === 'not_yet')
                                Window not open
                            @elseif(($group['accept_window']['state'] ?? '') === 'closed')
                                Window closed
                            @else
                                Accept order
                            @endif
                        </span>
                        <span wire:loading wire:target="acceptOrder({{ $group['id'] }})">Accepting...</span>
                    </button>
                </div>
            </div>

            @if(count($group['orders']) > 0)
                <ul class="divide-y divide-black/5">
                    @foreach($group['orders'] as $order)
                        <li
                            wire:key="middo-order-{{ $group['id'] }}-{{ $order['id'] }}"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-white/60 transition">
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
            <p class="text-sm font-semibold text-gray-400 italic">No unassigned Middo order groups right now.</p>
        </div>
    @endforelse

    @if($groups->hasPages())
        <div class="mt-4 px-1">
            {{ $groups->links() }}
        </div>
    @endif
</div>
