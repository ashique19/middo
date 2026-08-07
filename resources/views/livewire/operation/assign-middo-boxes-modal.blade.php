<div>
    @if($showModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Send Boxes to Kitchen</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            Assigning <span class="font-semibold text-middo-dark">{{ count($boxIds) }}</span>
                            {{ str('box')->plural(count($boxIds)) }} from warehouse inventory.
                            Only kitchens with a pending box request can receive stock — up to the requested quantity.
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="text-gray-400 hover:text-gray-600 text-xl leading-none">
                        ✕
                    </button>
                </div>

                <form wire:submit="save" class="space-y-5">
                    <div
                        x-data="{
                            open: false,
                            search: '',
                            items: @js($kitchens),
                            selectedId: @entangle('selectedKitchenId').live,
                            get selected() {
                                return this.items.find(i => String(i.id) === String(this.selectedId)) || null;
                            },
                            get filtered() {
                                const q = this.search.trim().toLowerCase();
                                if (!q) return this.items;
                                return this.items.filter(i => (i.search || i.name.toLowerCase()).includes(q));
                            },
                            pick(item) {
                                this.selectedId = item.id;
                                this.search = '';
                                this.open = false;
                            },
                            clear() {
                                this.selectedId = null;
                                this.search = '';
                                this.open = true;
                            }
                        }"
                        @keydown.escape.window="open = false"
                        class="relative"
                    >
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Destination kitchen
                        </label>
                        <div class="relative">
                            <button
                                type="button"
                                @click="open = !open; if (open) $nextTick(() => $refs.kitchenSearch.focus())"
                                class="w-full border border-gray-300 rounded-xl shadow-sm p-3 text-sm text-left hover:border-middo-orange focus:outline-none focus:ring-2 focus:ring-middo-orange/30 focus:border-middo-orange"
                            >
                                <template x-if="selected">
                                    <div>
                                        <div class="font-semibold text-gray-800" x-text="selected.name"></div>
                                        <div class="text-xs text-gray-400 mt-0.5" x-show="selected.subtitle" x-text="selected.subtitle"></div>
                                    </div>
                                </template>
                                <template x-if="!selected">
                                    <span class="text-gray-400">Search kitchen…</span>
                                </template>
                            </button>
                            <button
                                type="button"
                                x-show="selected"
                                @click.stop="clear()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs font-bold"
                            >Clear</button>
                        </div>

                        <div
                            x-show="open"
                            x-cloak
                            @click.outside="open = false"
                            class="absolute z-20 mt-2 w-full rounded-xl border border-gray-200 bg-white shadow-xl overflow-hidden"
                        >
                            <div class="p-2 border-b border-gray-100">
                                <input
                                    x-ref="kitchenSearch"
                                    type="search"
                                    x-model="search"
                                    placeholder="Type to search…"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange"
                                >
                            </div>
                            <ul class="max-h-52 overflow-y-auto">
                                <template x-for="item in filtered" :key="item.id">
                                    <li>
                                        <button
                                            type="button"
                                            @click="pick(item)"
                                            class="w-full text-left px-3 py-2.5 hover:bg-orange-50 transition"
                                            :class="String(selectedId) === String(item.id) ? 'bg-orange-50' : ''"
                                        >
                                            <div class="text-sm font-semibold text-gray-800" x-text="item.name"></div>
                                            <div class="text-[11px] text-gray-400 mt-0.5" x-show="item.subtitle" x-text="item.subtitle"></div>
                                        </button>
                                    </li>
                                </template>
                                <li x-show="filtered.length === 0" class="px-3 py-3 text-sm text-gray-400">No kitchens match.</li>
                            </ul>
                        </div>
                        @if($selectedKitchenId && $selectedKitchenPendingQty > 0)
                            <p class="text-xs font-semibold text-emerald-700 mt-1.5">
                                Pending request: {{ $selectedKitchenPendingQty }}
                                {{ str('box')->plural($selectedKitchenPendingQty) }}
                                @if(count($boxIds) > $selectedKitchenPendingQty)
                                    <span class="text-red-600">· selected {{ count($boxIds) }} exceeds request</span>
                                @endif
                            </p>
                        @endif
                        @error('selectedKitchenId')
                            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div
                        wire:key="send-boxes-rider-picker-{{ $selectedKitchenId ?: 'none' }}-{{ count($riders) }}"
                        x-data="{
                            open: false,
                            search: '',
                            items: @js($riders),
                            selectedId: @entangle('selectedRiderId'),
                            get selected() {
                                return this.items.find(i => String(i.id) === String(this.selectedId)) || null;
                            },
                            get filtered() {
                                const q = this.search.trim().toLowerCase();
                                if (!q) return this.items;
                                return this.items.filter(i => (i.search || i.name.toLowerCase()).includes(q));
                            },
                            pick(item) {
                                this.selectedId = item.id;
                                this.search = '';
                                this.open = false;
                            },
                            clear() {
                                this.selectedId = null;
                                this.search = '';
                                this.open = true;
                            }
                        }"
                        @keydown.escape.window="open = false"
                        class="relative"
                    >
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Rider
                        </label>
                        <div class="relative">
                            <button
                                type="button"
                                @click="open = !open; if (open) $nextTick(() => $refs.riderSearch.focus())"
                                class="w-full border border-gray-300 rounded-xl shadow-sm p-3 text-sm text-left hover:border-middo-orange focus:outline-none focus:ring-2 focus:ring-middo-orange/30 focus:border-middo-orange"
                            >
                                <template x-if="selected">
                                    <div>
                                        <div class="font-semibold text-gray-800" x-text="selected.name"></div>
                                        <div class="text-[11px] text-gray-400 mt-0.5 leading-snug" x-text="selected.areas_label"></div>
                                    </div>
                                </template>
                                <template x-if="!selected">
                                    <span class="text-gray-400">Search rider…</span>
                                </template>
                            </button>
                            <button
                                type="button"
                                x-show="selected"
                                @click.stop="clear()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs font-bold"
                            >Clear</button>
                        </div>

                        <div
                            x-show="open"
                            x-cloak
                            @click.outside="open = false"
                            class="absolute z-20 mt-2 w-full rounded-xl border border-gray-200 bg-white shadow-xl overflow-hidden"
                        >
                            <div class="p-2 border-b border-gray-100">
                                <input
                                    x-ref="riderSearch"
                                    type="search"
                                    x-model="search"
                                    placeholder="Type name or area…"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange"
                                >
                            </div>
                            <ul class="max-h-56 overflow-y-auto">
                                <template x-for="item in filtered" :key="item.id">
                                    <li>
                                        <button
                                            type="button"
                                            @click="pick(item)"
                                            class="w-full text-left px-3 py-2.5 hover:bg-orange-50 transition"
                                            :class="String(selectedId) === String(item.id) ? 'bg-orange-50' : ''"
                                        >
                                            <div class="text-sm font-semibold text-gray-800" x-text="item.name"></div>
                                            <div class="text-[11px] text-gray-400 mt-0.5 leading-snug" x-text="item.areas_label"></div>
                                        </button>
                                    </li>
                                </template>
                                <li x-show="filtered.length === 0" class="px-3 py-3 text-sm text-gray-400">No riders match.</li>
                            </ul>
                        </div>
                        @error('selectedRiderId')
                            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    @if($kitchens === [] || $riders === [])
                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
                            @if($kitchens === [])
                                No kitchens with a pending box request. Ask the kitchen to Request box first.
                            @else
                                No active delivery riders found. Activate a delivery user first.
                            @endif
                        </p>
                    @endif

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-bold text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            @disabled($kitchens === [] || $riders === [] || ($selectedKitchenPendingQty > 0 && count($boxIds) > $selectedKitchenPendingQty))
                            class="px-4 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Send to kitchen</span>
                            <span wire:loading wire:target="save">Sending...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
