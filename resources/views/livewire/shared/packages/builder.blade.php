<div class="block w-full max-w-6xl mx-auto py-8 px-4 sm:px-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <a href="{{ $this->indexRoute() }}"
               class="text-xs font-bold text-middo-orange hover:underline">← Back to packages</a>
            <h1 class="text-3xl font-bold text-gray-800 mt-1">
                {{ $packageId ? ($canManage ? 'Edit Package' : 'View Package') : 'Create Package' }}
            </h1>
            <p class="text-sm text-gray-500 mt-1">Define a prepaid rate plan (৳/day). Corporates pick menus and day counts; operations assigns exact dates per subscription.</p>
        </div>
        @if($canManage)
            <button type="button" wire:click="save" wire:loading.attr="disabled"
                    class="bg-middo-orange hover:bg-[#733614] text-white font-bold text-sm px-5 py-2.5 rounded-xl shadow-sm transition">
                Save package
            </button>
        @endif
    </div>

    @if($errorMessage)
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-800 text-sm px-4 py-3">{{ $errorMessage }}</div>
    @endif
    @if($successMessage)
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm px-4 py-3">{{ $successMessage }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-4 space-y-4">
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 space-y-4">
                <h2 class="text-xs font-black uppercase tracking-wider text-gray-400">Package details</h2>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Name</label>
                    <input type="text" wire:model="name" @disabled(! $canManage)
                           class="w-full rounded-xl border-gray-200 text-sm focus:ring-middo-orange focus:border-middo-orange" placeholder="৳79 / day · Classic">
                    @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Summary</label>
                    <textarea wire:model="summary" rows="3" @disabled(! $canManage)
                              class="w-full rounded-xl border-gray-200 text-sm focus:ring-middo-orange focus:border-middo-orange"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">৳ / day</label>
                        <input type="number" wire:model="price_per_day" min="1" @disabled(! $canManage)
                               class="w-full rounded-xl border-gray-200 text-sm focus:ring-middo-orange focus:border-middo-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Duration</label>
                        <input type="number" wire:model.live="duration_days" min="1" max="60" @disabled(! $canManage)
                               class="w-full rounded-xl border-gray-200 text-sm focus:ring-middo-orange focus:border-middo-orange">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Diet tag</label>
                    <select wire:model="diet_tag" @disabled(! $canManage)
                            class="w-full rounded-xl border-gray-200 text-sm focus:ring-middo-orange focus:border-middo-orange">
                        @foreach(\App\Models\MealPackage::DIET_TAGS as $tag)
                            <option value="{{ $tag }}">{{ ucfirst($tag) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Start date</label>
                    <input type="date" wire:model.live="start_date" @disabled(! $canManage)
                           class="w-full rounded-xl border-gray-200 text-sm focus:ring-middo-orange focus:border-middo-orange">
                    <p class="text-[11px] text-gray-400 mt-1">Ends {{ $end_date ?: '—' }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Display order</label>
                        <input type="number" wire:model="display_order" @disabled(! $canManage)
                               class="w-full rounded-xl border-gray-200 text-sm focus:ring-middo-orange focus:border-middo-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Status</label>
                        <select wire:model="status" @disabled(! $canPublish)
                                class="w-full rounded-xl border-gray-200 text-sm focus:ring-middo-orange focus:border-middo-orange">
                            <option value="draft">Draft</option>
                            @if($canPublish)
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            @endif
                        </select>
                        @unless($canPublish)
                            <p class="text-[11px] text-gray-400 mt-1">Only admins can publish.</p>
                        @endunless
                    </div>
                </div>

                @if($daysSoftLocked)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-900 text-xs px-3 py-2 font-semibold">
                        Active subscriptions soft-lock existing day menus. You can still fill empty days; use subscription day swap to change sold days.
                    </div>
                @endif

                @if($canManage)
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Thumbnail</label>
                        <input type="file" wire:model="thumbnail" accept="image/*"
                               class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-amber-50 file:text-amber-900 file:font-bold">
                        @if($existingThumbnail)
                            <img src="{{ asset($existingThumbnail) }}" alt="" class="mt-2 w-full h-32 object-cover rounded-xl border border-gray-100">
                        @endif
                    </div>
                @elseif($existingThumbnail)
                    <img src="{{ asset($existingThumbnail) }}" alt="" class="w-full h-32 object-cover rounded-xl border border-gray-100">
                @endif
            </div>

            @if($canManage)
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 space-y-3">
                    <h2 class="text-xs font-black uppercase tracking-wider text-gray-400">Bulk assign</h2>
                    <select wire:model="bulkMenuItemId" class="w-full rounded-xl border-gray-200 text-sm">
                        <option value="">Select menu item…</option>
                        @foreach($menuItems as $item)
                            <option value="{{ $item->id }}">{{ $item->name }} (৳{{ $item->price }})</option>
                        @endforeach
                    </select>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="fillWeekdays" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-[#1E4630] text-white">Fill weekdays</button>
                        <button type="button" wire:click="fillAll" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-gray-800 text-white">Fill all</button>
                        <button type="button" wire:click="clearWeekends" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-gray-200">Clear Fri/Sat</button>
                    </div>
                </div>
            @endif
        </div>

        <div class="lg:col-span-8">
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xs font-black uppercase tracking-wider text-gray-400">30-day menu calendar</h2>
                    @php $filled = collect($dayAssignments)->filter()->count(); @endphp
                    <span class="text-xs font-bold text-middo-orange">{{ $filled }} / {{ count($dayAssignments) }} assigned</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 max-h-[640px] overflow-y-auto">
                    @foreach($dayAssignments as $date => $menuItemId)
                        @php
                            $carbon = \Carbon\Carbon::parse($date);
                            $item = $menuItemId ? $menuLookup->get($menuItemId) : null;
                            $isWeekend = in_array($carbon->dayOfWeek, [5, 6], true);
                        @endphp
                        <div wire:key="day-{{ $date }}"
                             class="rounded-xl border p-3 min-h-[110px] flex flex-col {{ $menuItemId ? 'border-emerald-200 bg-emerald-50/40' : ($isWeekend ? 'border-gray-100 bg-gray-50' : 'border-amber-100 bg-amber-50/30') }}">
                            <div class="flex items-start justify-between gap-1">
                                <div>
                                    <div class="text-[10px] font-bold uppercase text-gray-400">{{ $carbon->format('D') }}</div>
                                    <div class="text-sm font-black text-gray-800">{{ $carbon->format('M d') }}</div>
                                </div>
                                @if($canManage && $menuItemId)
                                    <button type="button" wire:click="clearDay('{{ $date }}')" class="text-[10px] text-red-500 font-bold">✕</button>
                                @endif
                            </div>
                            <div class="mt-2 text-[11px] font-semibold text-gray-700 leading-snug flex-1">
                                {{ $item?->name ?? 'Unassigned' }}
                            </div>
                            @if($canManage)
                                <button type="button" wire:click="openAssign('{{ $date }}')"
                                        class="mt-2 w-full text-[11px] font-bold py-1.5 rounded-lg border border-gray-200 hover:bg-white">
                                    {{ $menuItemId ? 'Change' : 'Assign' }}
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if($assignDate && $canManage)
        <div class="fixed inset-0 z-50 bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-5">
                <h3 class="text-lg font-black text-gray-800">Assign menu · {{ \Carbon\Carbon::parse($assignDate)->format('D, M d') }}</h3>
                <select wire:model="assignMenuItemId" class="mt-4 w-full rounded-xl border-gray-200 text-sm">
                    <option value="">Select…</option>
                    @foreach($menuItems as $item)
                        <option value="{{ $item->id }}">{{ $item->name }} (৳{{ $item->price }})</option>
                    @endforeach
                </select>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeAssign" class="px-4 py-2 text-sm font-bold rounded-xl border border-gray-200">Cancel</button>
                    <button type="button" wire:click="confirmAssign" class="px-4 py-2 text-sm font-bold rounded-xl bg-middo-orange text-white">Assign</button>
                </div>
            </div>
        </div>
    @endif
</div>
