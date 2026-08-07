<div class="max-w-5xl mx-auto py-6 sm:py-8 px-4 sm:px-6 space-y-5 sm:space-y-6">
    <div class="space-y-2">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-2xl sm:text-3xl font-bold text-middo-dark">Prep shopping list</h1>
        <p class="text-sm font-semibold text-gray-500">
            Ingredients needed for your accepted order groups on the selected day.
            Scaled from active recipes × plate quantity.
        </p>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between sm:gap-4">
            <div class="w-full sm:max-w-xs min-w-0">
                <label for="prep-shopping-delivery-date" class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1.5">
                    Delivery date
                </label>
                <input
                    id="prep-shopping-delivery-date"
                    type="date"
                    wire:model.live="deliveryDate"
                    class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-middo-dark shadow-none focus:border-middo-orange focus:outline-none focus:ring-2 focus:ring-middo-orange/20"
                >
            </div>
            <a href="{{ route('kitchen.menus.today') }}"
               class="inline-flex items-center text-xs font-bold text-middo-orange hover:underline sm:pb-2.5">
                Today’s menus →
            </a>
        </div>
        <div class="grid grid-cols-2 gap-3 border-t border-gray-100 pt-3">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Groups</p>
                <p class="mt-0.5 text-lg font-black text-middo-orange tabular-nums">{{ $rollup['group_count'] }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Plates</p>
                <p class="mt-0.5 text-lg font-black text-middo-orange tabular-nums">{{ $rollup['plate_count'] }}</p>
            </div>
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
        <div class="space-y-2">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500 px-1">Menus in scope</h2>

            <div class="md:hidden space-y-3">
                @foreach($rollup['menus'] as $menu)
                    <div wire:key="prep-menu-m-{{ $menu['menu_id'] }}"
                         class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-3">
                        <div class="space-y-1">
                            <p class="font-semibold text-gray-800 break-words">{{ $menu['menu_name'] }}</p>
                            @if(! empty($menu['missing_recipes']))
                                <span class="text-xs font-bold text-amber-700">recipe missing</span>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-sm border-t border-gray-100 pt-3">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Groups</p>
                                <p class="font-semibold text-gray-800">{{ count($menu['group_ids']) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Plates</p>
                                <p class="font-black text-middo-orange">{{ $menu['total_qty'] }}</p>
                            </div>
                        </div>
                        <a href="{{ route('kitchen.menus.show', $menu['menu_id']) }}"
                           class="w-full inline-flex justify-center items-center px-3 py-2.5 rounded-xl border border-orange-200 bg-orange-50 text-xs font-bold text-middo-orange">
                            Open menu
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="hidden md:block bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[520px]">
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
            </div>
        </div>
    @endif

    <div class="space-y-2">
        <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500 px-1">Ingredients</h2>

        <div class="md:hidden space-y-3">
            @forelse($rollup['ingredients'] as $row)
                <div wire:key="prep-ing-m-{{ $row['key'] }}"
                     class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-semibold text-gray-800 break-words min-w-0">{{ $row['name'] }}</p>
                        <p class="shrink-0 text-lg font-black text-middo-orange tabular-nums">
                            {{ rtrim(rtrim(number_format($row['quantity'], 4, '.', ''), '0'), '.') }}
                            <span class="text-xs font-bold text-gray-500">{{ $row['unit'] !== '' ? $row['unit'] : '' }}</span>
                        </p>
                    </div>
                    <div class="text-xs text-gray-500 space-y-0.5 border-t border-gray-100 pt-2">
                        @foreach($row['sources'] as $source)
                            <p>
                                {{ $source['menu_name'] }} · {{ $source['meal_item'] }}
                                ({{ rtrim(rtrim(number_format($source['per_plate'], 4, '.', ''), '0'), '.') }} × {{ $source['plates'] }})
                            </p>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-sm text-gray-400 italic">
                    No accepted groups with recipes for this date.
                </div>
            @endforelse
        </div>

        <div class="hidden md:block bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[560px]">
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
    </div>
</div>
