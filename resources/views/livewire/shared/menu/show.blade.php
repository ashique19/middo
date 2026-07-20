<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <nav class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-gray-400">
            <a href="{{ $this->indexRoute() }}" class="text-middo-orange hover:underline">Menu items</a>
            <span>/</span>
            <span class="text-gray-600">{{ $item->name }}</span>
        </nav>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4 min-w-0">
                @if($item->thumbnail)
                    <img src="{{ asset($item->thumbnail) }}" alt="{{ $item->name }}"
                         class="w-20 h-20 rounded-2xl object-cover border border-gray-100 shrink-0">
                @else
                    <div class="w-20 h-20 rounded-2xl bg-gray-50 border border-dashed border-gray-200 flex items-center justify-center text-[10px] text-gray-400 shrink-0">
                        No Img
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Menu item</p>
                    <h1 class="text-3xl font-bold text-middo-dark truncate">{{ $item->name }}</h1>
                    @if($item->summary)
                        <p class="text-sm text-gray-500 mt-1">{{ $item->summary }}</p>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($item->is_featured)
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">Featured</span>
                @endif
                @if($item->is_homepage)
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase bg-sky-100 text-sky-800 border border-sky-200">Homepage</span>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Price</p>
            <p class="text-2xl font-black text-middo-dark font-mono">৳{{ number_format((int) $item->price) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Meals cost</p>
            <p class="text-2xl font-black text-middo-dark font-mono">৳{{ number_format((int) $item->meals_cost) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Other cost</p>
            <p class="text-2xl font-black text-middo-dark font-mono">৳{{ number_format((int) $item->other_cost) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Kitchen commission</p>
            <p class="text-2xl font-black text-middo-dark font-mono">৳{{ number_format((int) $item->kitchen_commission) }}</p>
            <p class="text-[11px] font-semibold text-gray-400 mt-1">{{ $kitchenCommissionPct }}%</p>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
        <h2 class="text-lg font-bold text-middo-dark">Details</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Display order</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $item->display_order }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Attached meals</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $item->mealItems->count() }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Note</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $item->note ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-bold text-middo-dark">Meal items</h2>
                <p class="text-xs font-semibold text-gray-400 mt-0.5">Open a meal item to view its recipes</p>
            </div>
            @if($canManage)
                <button type="button"
                    wire:click="$dispatch('open-attach-meal-items-modal', { menuItemId: {{ $item->id }} })"
                    class="inline-flex px-3 py-1.5 rounded-xl text-xs font-bold border border-gray-200 text-middo-dark hover:border-middo-orange hover:text-middo-orange transition">
                    Manage meal items
                </button>
            @endif
        </div>
        <ul class="divide-y divide-gray-100">
            @forelse($item->mealItems as $meal)
                <li class="px-5 py-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <div class="min-w-0">
                        <a href="{{ $this->mealItemShowRoute($meal->id) }}"
                           class="font-bold text-middo-dark hover:text-middo-orange transition truncate block">
                            {{ $meal->name }}
                        </a>
                        <p class="text-[11px] font-semibold text-gray-400 mt-0.5">
                            Sort {{ $meal->pivot->sort_order ?? '—' }}
                            · ৳{{ number_format((int) $meal->total_cost) }}
                        </p>
                    </div>
                    <a href="{{ $this->mealItemShowRoute($meal->id) }}"
                       class="shrink-0 text-xs font-bold text-middo-orange hover:underline">
                        Recipes →
                    </a>
                </li>
            @empty
                <li class="px-5 py-10 text-center text-sm font-semibold text-gray-400 italic">
                    No meal items attached.
                </li>
            @endforelse
        </ul>
    </div>

    @if($canManage)
        <livewire:shared.attach-meal-items-modal />
    @endif
</div>
