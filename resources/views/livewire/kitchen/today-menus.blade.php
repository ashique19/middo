<div class="max-w-5xl mx-auto py-6 sm:py-8 px-4 sm:px-6 space-y-5 sm:space-y-6">
    <div class="space-y-2">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-2xl sm:text-3xl font-bold text-middo-dark">Today's Menus</h1>
        <p class="text-sm font-semibold text-gray-500">
            Menus ordered for the selected day, including package-sourced meals. Your kitchen has
            <span class="text-middo-orange">{{ $assignedGroupCount }}</span> assigned group(s) on this date.
        </p>
        <a href="{{ route('kitchen.prep.shopping-list') }}" class="inline-block text-sm font-bold text-middo-orange hover:underline">Prep shopping list →</a>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Delivery date</label>
        <input type="date" wire:model.live="deliveryDate"
               class="w-full sm:w-auto rounded-xl border border-gray-200 px-3 py-2.5 sm:py-2 text-sm">
    </div>

    {{-- Mobile cards --}}
    <div class="md:hidden space-y-3">
        @forelse($menus as $row)
            <div wire:key="today-menu-m-{{ $row->menu_item_id ?? 'custom' }}"
                 class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <p class="font-semibold text-gray-800 break-words">
                            {{ $row->menuItem?->name ?? 'Custom Selection' }}
                        </p>
                        @if((int) $row->package_qty > 0)
                            <x-package-badge />
                        @endif
                    </div>
                    <p class="shrink-0 text-xl font-black text-middo-orange tabular-nums">
                        {{ $row->total_qty }}
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm border-t border-gray-100 pt-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Orders</p>
                        <p class="font-semibold text-gray-800 tabular-nums">{{ $row->order_count }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Package qty</p>
                        <p class="font-bold text-sky-800 tabular-nums">{{ $row->package_qty }}</p>
                    </div>
                </div>
                @if($row->menu_item_id)
                    <a href="{{ route('kitchen.menus.show', $row->menu_item_id) }}"
                       class="w-full inline-flex justify-center items-center px-3 py-2.5 rounded-xl border border-orange-200 bg-orange-50 text-xs font-bold text-middo-orange hover:bg-orange-100 transition">
                        Open menu
                    </a>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-sm text-gray-400 italic">
                No ordered menus for this date.
            </div>
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="hidden md:block bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[560px]">
                <thead>
                    <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="p-4">Menu</th>
                        <th class="p-4 text-center">Orders</th>
                        <th class="p-4 text-center">Package qty</th>
                        <th class="p-4 text-center">Total qty</th>
                        <th class="p-4 text-right">Recipe</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($menus as $row)
                        <tr wire:key="today-menu-{{ $row->menu_item_id ?? 'custom' }}">
                            <td class="p-4 font-semibold text-gray-800">
                                <div class="flex items-center gap-2 flex-wrap">
                                    {{ $row->menuItem?->name ?? 'Custom Selection' }}
                                    @if((int) $row->package_qty > 0)
                                        <x-package-badge />
                                    @endif
                                </div>
                            </td>
                            <td class="p-4 text-center">{{ $row->order_count }}</td>
                            <td class="p-4 text-center font-bold text-sky-800">{{ $row->package_qty }}</td>
                            <td class="p-4 text-center font-black text-middo-orange">{{ $row->total_qty }}</td>
                            <td class="p-4 text-right">
                                @if($row->menu_item_id)
                                    <a href="{{ route('kitchen.menus.show', $row->menu_item_id) }}"
                                       class="text-xs font-bold text-middo-orange hover:underline">
                                        Open menu
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-10 text-center text-sm text-gray-400 italic">No ordered menus for this date.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
