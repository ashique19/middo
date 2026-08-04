<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-3xl font-bold text-middo-dark">Prep shopping list</h1>
        <p class="text-sm font-semibold text-gray-500">
            Ingredients needed for your accepted order groups on the selected day.
            Scaled from active recipes × plate quantity.
        </p>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Delivery date</label>
            <input type="date" wire:model.live="deliveryDate" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
        </div>
        <div class="text-sm text-gray-600">
            <span class="font-bold text-middo-orange">{{ $rollup['group_count'] }}</span> group(s) ·
            <span class="font-bold text-middo-orange">{{ $rollup['plate_count'] }}</span> plate(s)
        </div>
        <div class="ml-auto">
            <a href="{{ route('kitchen.menus.today') }}" class="text-xs font-bold text-middo-orange hover:underline">Today’s menus →</a>
        </div>
    </div>

    @if(! empty($rollup['warnings']))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 space-y-1">
            <p class="font-bold">Missing recipe data</p>
            @foreach($rollup['warnings'] as $warning)
                <p>{{ $warning }}</p>
            @endforeach
        </div>
    @endif

    @if(! empty($rollup['menus']))
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
                <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500">Menus in scope</h2>
            </div>
            <table class="w-full text-left min-w-[560px]">
                <thead>
                    <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="p-4">Menu</th>
                        <th class="p-4 text-center">Groups</th>
                        <th class="p-4 text-center">Plates</th>
                        <th class="p-4 text-right">Open</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @foreach($rollup['menus'] as $menu)
                        <tr wire:key="prep-menu-{{ $menu['menu_id'] }}">
                            <td class="p-4 font-semibold text-gray-800">
                                {{ $menu['menu_name'] }}
                                @if(! empty($menu['missing_recipes']))
                                    <span class="ml-2 text-xs font-bold text-amber-700">recipe missing</span>
                                @endif
                            </td>
                            <td class="p-4 text-center">{{ count($menu['group_ids']) }}</td>
                            <td class="p-4 text-center font-black text-middo-orange">{{ $menu['total_qty'] }}</td>
                            <td class="p-4 text-right">
                                <a href="{{ route('kitchen.menus.show', $menu['menu_id']) }}"
                                   class="text-xs font-bold text-middo-orange hover:underline">Menu</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500">Ingredients</h2>
        </div>
        <table class="w-full text-left min-w-[640px]">
            <thead>
                <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
                    <th class="p-4">Ingredient</th>
                    <th class="p-4 text-center">Qty</th>
                    <th class="p-4">Unit</th>
                    <th class="p-4">From</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                @forelse($rollup['ingredients'] as $row)
                    <tr wire:key="prep-ing-{{ $row['key'] }}">
                        <td class="p-4 font-semibold text-gray-800">{{ $row['name'] }}</td>
                        <td class="p-4 text-center font-black text-middo-orange">{{ rtrim(rtrim(number_format($row['quantity'], 4, '.', ''), '0'), '.') }}</td>
                        <td class="p-4 text-gray-600">{{ $row['unit'] !== '' ? $row['unit'] : '—' }}</td>
                        <td class="p-4 text-xs text-gray-500">
                            @foreach($row['sources'] as $source)
                                <div>
                                    {{ $source['menu_name'] }} · {{ $source['meal_item'] }}
                                    ({{ rtrim(rtrim(number_format($source['per_plate'], 4, '.', ''), '0'), '.') }} × {{ $source['plates'] }})
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="p-10 text-center text-sm text-gray-400 italic">
                            No accepted groups with recipes for this date.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
