<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-3xl font-bold text-middo-dark">Kitchens</h1>
            <p class="text-sm font-semibold text-gray-500">
                Active orders grouped by kitchen.
            </p>
        </div>

        @php
            $routePrefix = auth()->user()?->role?->name === 'admin' ? 'admin' : 'operation';
        @endphp

        <a
            href="{{ route($routePrefix.'.orders.history') }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-300 bg-white text-sm font-bold text-middo-dark hover:border-middo-orange hover:text-middo-orange transition">
            Order history
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
        </a>
    </div>

    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        @forelse($kitchenSections as $section)
            @php $isExpanded = in_array($section['key'], $expandedKitchens, true); @endphp

            <div wire:key="kitchen-section-{{ $section['key'] }}" class="border-b border-gray-100 last:border-b-0">
                <div class="flex items-center gap-3 px-5 py-4 hover:bg-gray-50 transition">
                    <button
                        type="button"
                        wire:click="toggleKitchen('{{ $section['key'] }}')"
                        class="flex flex-1 items-center gap-4 min-w-0 text-left">
                        <span class="flex-1 min-w-0 text-base font-black text-middo-dark truncate">
                            {{ $section['name'] }}
                        </span>

                        <span class="hidden sm:inline shrink-0 text-sm text-gray-500 whitespace-nowrap">
                            <span class="font-semibold text-middo-orange">{{ $section['active_count'] }}</span>
                            active · {{ $section['date_label'] }}
                        </span>

                        <span class="sm:hidden shrink-0 text-sm font-semibold text-middo-orange whitespace-nowrap">
                            {{ $section['active_count'] }}
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

                    <a
                        href="{{ route($routePrefix.'.kitchens.orders', $section['key']) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-bold text-middo-dark hover:border-middo-orange hover:text-middo-orange transition whitespace-nowrap">
                        All orders
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </a>
                </div>

                @if($isExpanded)
                    <div class="border-t border-gray-100 px-5 py-4 space-y-4 bg-gray-50/40">
                        @if($section['active_count'] === 0)
                            <p class="text-sm text-gray-400 italic text-center py-4">No active orders for this kitchen.</p>
                        @else
                            @foreach($section['groups'] as $group)
                                <div
                                    wire:key="kitchen-group-{{ $section['key'] }}-{{ $group['id'] }}"
                                    class="rounded-xl border {{ $group['color'] }} overflow-hidden">
                                    <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-inherit bg-white/40">
                                        <span class="text-sm font-black text-middo-dark">{{ $group['name'] }}</span>
                                        <span class="text-xs font-semibold text-gray-600">{{ $group['menu_name'] }}</span>
                                        <span class="text-xs font-bold text-middo-orange">Qty {{ $group['total_quantity'] }}</span>
                                        <span class="text-xs text-gray-500">{{ count($group['orders']) }} order(s)</span>
                                    </div>

                                    <ul class="divide-y divide-black/5">
                                        @foreach($group['orders'] as $order)
                                            <li
                                                wire:key="kitchen-order-{{ $section['key'] }}-{{ $group['id'] }}-{{ $order['id'] }}"
                                                class="flex items-center gap-3 px-4 py-3 hover:bg-white/60 transition">
                                                <span class="shrink-0 w-6 text-center text-gray-400 font-mono text-xs select-none">└</span>
                                                <div class="flex-1 min-w-0 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-1 text-sm">
                                                    <span class="font-mono font-bold text-middo-dark">#{{ $order['id'] }}</span>
                                                    <span class="font-medium truncate">{{ $order['customer_name'] }}</span>
                                                    <span class="truncate text-gray-700">{{ $order['menu_name'] }}</span>
                                                    <span class="text-gray-500">Qty <strong class="text-middo-orange">{{ $order['quantity'] }}</strong> · {{ $order['delivery_time'] }}</span>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="p-12 text-center">
                <p class="text-sm font-semibold text-gray-400 italic">No kitchens found.</p>
            </div>
        @endforelse
    </div>
</div>
