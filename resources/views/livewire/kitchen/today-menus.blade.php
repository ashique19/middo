<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-3xl font-bold text-middo-dark">Today's Menus</h1>
        <p class="text-sm font-semibold text-gray-500">
            Menus ordered for the selected day, including package-sourced meals. Your kitchen has
            <span class="text-middo-orange">{{ $assignedGroupCount }}</span> assigned group(s) on this date.
            <a href="{{ route('kitchen.prep.shopping-list') }}" class="ml-2 text-middo-orange hover:underline">Prep shopping list →</a>
        </p>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Delivery date</label>
        <input type="date" wire:model.live="deliveryDate" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-left min-w-[640px]">
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
                    <tr wire:key="today-menu-{{ $row->menu_item_id }}">
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
