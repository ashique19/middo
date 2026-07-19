<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="space-y-1">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-3xl font-bold text-middo-dark">Middo Order Groups</h1>
        <p class="text-sm font-semibold text-gray-500">
            Unassigned menu order groups. Accepting assigns the group to your kitchen permanently.
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
                <div class="ml-auto">
                    <button
                        type="button"
                        wire:click="acceptOrder({{ $group['id'] }})"
                        wire:loading.attr="disabled"
                        wire:target="acceptOrder({{ $group['id'] }})"
                        wire:confirm="Accept this order group? You cannot undo this."
                        class="inline-flex items-center px-4 py-2 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition disabled:opacity-60">
                        <span wire:loading.remove wire:target="acceptOrder({{ $group['id'] }})">Accept order</span>
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
